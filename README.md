# DokuChat

Ask questions about your own documents and get short answers **with sources**.
DokuChat is a small retrieval-augmented generation (RAG) application written in
plain PHP 8.5, without a framework, to understand every step of the pipeline.

It talks to language models through the **OpenAI-compatible API**, so the same
code works with a local [Ollama](https://ollama.com) server and with the
[GWDG SAIA API](https://docs.hpc.gwdg.de/services/ai-services/saia/index.html)
behind Chat AI and Arcana. Only `.env` changes.

## How it works

```
Loading (once per document)
  text file → Chunker (whole sentences, ~800 chars, overlap) → MariaDB
            → embedding model (bge-m3) → vector per chunk, stored as JSON

Asking (every question)
  question → embedding → cosine similarity with every chunk → top-k chunks
           → prompt with numbered sources [1], [2] → language model
           → answer with source references
```

| Step | Class | Notes |
| --- | --- | --- |
| Split text | `Chunker` | Packs whole sentences; only a sentence longer than the limit is cut between words |
| Store | `DocumentRepository` | Document and its chunks in one transaction: all or nothing |
| Embed | `LlmClient::embed()` | `/v1/embeddings`, multilingual model `bge-m3` |
| Search | `Retriever`, `VectorMath` | Cosine similarity in PHP, top-k by score |
| Prompt | `PromptBuilder` | Numbered sources; the model must answer only from them or say *"Das steht nicht in den Dokumenten."* |
| Answer | `LlmClient::chat()`, `RagService` | `temperature: 0` |

## Quick start

Requirements: Docker with Docker Compose.

```bash
cp .env.example .env                  # then set your own DB_PASSWORD
docker compose up -d --build --wait
docker compose exec php composer install
docker compose exec php php bin/migrate.php

docker compose exec ollama ollama pull llama3.2:3b
docker compose exec ollama ollama pull bge-m3

docker compose exec php php bin/ingest.php data/museum.txt
docker compose exec php php bin/ingest.php data/library.txt
docker compose exec php php bin/embed-chunks.php
```

Ask a question:

```bash
curl -sS --get "http://localhost:8080/ask" --data-urlencode "q=Wie alt ist der Höhlenbär?"
```

The two documents in `data/` describe a **fictional** museum and library, so the
model cannot know the answers from its training data: every correct answer has
to come from retrieval.

## Commands

| Command | What it does |
| --- | --- |
| `bin/migrate.php` | Creates the tables; safe to run again |
| `bin/ingest.php <file>` | Splits a UTF-8 text file into chunks and stores them |
| `bin/embed-chunks.php` | Computes embeddings for all chunks that have none yet; resumable |
| `bin/search.php "<question>"` | Shows the top 3 chunks and their scores, without the language model |
| `bin/similarity.php "<a>" "<b>"` | Cosine similarity of two texts |
| `bin/eval.php` | Runs a fixed set of questions with known answers and prints the score |
| `GET /ask?q=...` | Answer with sources (HTML) |
| `GET /health` | `200` if the database answers, `503` if not (JSON) |

Run any command as `docker compose exec php php bin/<name>`.
Settings can be overridden for one run, for example
`docker compose exec -e RAG_TOP_K=1 php php bin/eval.php`.

## Configuration (`.env`)

| Variable | Example | Meaning |
| --- | --- | --- |
| `LLM_BASE_URL` | `http://ollama:11434/v1` | OpenAI-compatible endpoint |
| `LLM_MODEL` | `llama3.2:3b` | Chat model |
| `EMBEDDING_MODEL` | `bge-m3` | Embedding model |
| `LLM_API_KEY` | `not-needed-for-ollama` | Sent as `Authorization: Bearer` |
| `LLM_TIMEOUT` | `50` | Seconds; below nginx's 60 s, so PHP fails first with a clear error |
| `RAG_TOP_K` | `2` | Number of chunks in the prompt |

**Switching to GWDG SAIA:** set `LLM_BASE_URL=https://chat-ai.academiccloud.de/v1`,
your API key, and models from the SAIA model list. After changing the
**embedding** model, delete the stored vectors and run `bin/embed-chunks.php`
again: vectors from different models cannot be compared (`VectorMath` rejects
vectors of different length). Only send documents to an external service if
data protection allows it.

## Evaluation

`bin/eval.php` asks 9 questions with known answers. A case passes if the answer
contains the expected words (`any` = one of them, `all` = every one). Cases
include a question whose answer is in **two** documents and one whose answer is
in **no** document, so the model must not invent anything.

Results on a laptop CPU (temperature 0):

| Chunking | Model | k | Result | Notes |
| --- | --- | --- | --- | --- |
| by words | llama3.2:3b | 1 | 8/9 | Misses the question that needs two documents |
| by words | llama3.2:3b | 2 | 8/9 | Says the museum costs **4 Euro** (correct: 8 Euro) |
| by words | llama3.2:3b | 3 | 3/6 * | Too much context for a small model: answers "not in the documents" |
| by words | qwen2.5:7b | 3 | 6/9 | Confuses the museum and the library opening days |
| by sentences | llama3.2:3b | 2 | _fill in_ | _fill in_ |

\* measured with the first 6 questions only, before the evaluation had 9.

**What the evaluation found.** The wrong price came from chunking: with
word-based chunks, a chunk started in the middle of a sentence with
*"ermäßigt 4 Euro."*, and the sentence with the real price stayed in another
chunk. `bin/search.php` confirmed that the model received exactly that fragment.
The `Chunker` now packs whole sentences, so every chunk starts at the beginning
of a sentence. A bigger model did not fix the problem: with poor context it made
other mistakes.

**Limits of this evaluation:** 9 questions are few, and checking for keywords
cannot judge the quality of an answer or its source references. Larger projects
use a second model as a judge.

## Tests

```bash
docker compose exec php vendor/bin/phpunit --testdox
```

Unit tests for `Chunker`, `Config`, `VectorMath`, `Retriever`, `PromptBuilder`
and the response parsing in `LlmClient`; integration tests for
`DocumentRepository` against the real MariaDB (including the transaction
rollback).

## Design decisions

- **No framework.** Front controller, PSR-4 autoloading with Composer, PDO.
  The goal was to understand every layer.
- **Settings only from environment variables.** `.env` is not in Git;
  `.env.example` documents every setting.
- **Database is not reachable from outside.** Only the PHP container can
  connect to MariaDB; no port is published.
- **Real prepared statements** (`ATTR_EMULATE_PREPARES = false`), `utf8mb4`,
  foreign keys with `ON DELETE CASCADE`.
- **Errors go to the log, not to the user.** `/ask` returns `502` if the
  language model fails and `503` if the database fails.
- **Cosine similarity in PHP** to understand retrieval. For many thousands of
  chunks, MariaDB 11.7+ offers a native `VECTOR` type with
  `VEC_DISTANCE_COSINE()` and a vector index.

## Known limitations

- Only UTF-8 text files; no PDF yet (planned: conversion with Docling, as in the
  GWDG `/documents` endpoint).
- No web upload and no login: documents are loaded from the command line.
- Sentence detection is a regular expression: abbreviations like *"z. B."*
  are treated as sentence ends.
- Text in documents goes into the prompt, so a document could try a
  **prompt injection**. Only trusted people should be able to add documents.
- When an answer is only partly in the sources, the model sometimes answers
  "not in the documents" instead of giving the part it knows.

## Stack

PHP 8.5 (PHP-FPM), nginx 1.30, MariaDB 11.8, Ollama, Docker Compose, Composer,
PHPUnit 13.