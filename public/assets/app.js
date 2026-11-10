// DokuChat – Browser-Oberfläche.
// Schickt die Frage an /api/ask und zeigt Antwort und Quellen an.
// Wichtig: Text vom Server kommt nur über textContent ins HTML, nie über innerHTML (Schutz vor XSS).

const form = document.getElementById('form');
const input = document.getElementById('question');
const sendButton = document.getElementById('send');
const chat = document.getElementById('chat');
const welcome = document.getElementById('welcome');
const statusBadge = document.getElementById('status');
const newChatButton = document.getElementById('new-chat');
const homeLink = document.getElementById('home-link');

let busy = false;

form.addEventListener('submit', (event) => {
	event.preventDefault();
	ask(input.value);
});

// Enter sendet, Shift + Enter macht eine neue Zeile
input.addEventListener('keydown', (event) => {
	if (event.key === 'Enter' && !event.shiftKey) {
		event.preventDefault();
		form.requestSubmit();
	}
});

// Textfeld wächst mit dem Text
input.addEventListener('input', () => {
	input.style.height = 'auto';
	input.style.height = input.scrollHeight + 'px';
});

// Zurück zur Startseite: Verlauf löschen, Beispiele wieder zeigen
function newChat() {
	chat.querySelectorAll('.msg').forEach((message) => message.remove());
	welcome.hidden = false;
	newChatButton.hidden = true;
	input.value = '';
	input.style.height = 'auto';
	input.focus();
}

newChatButton.addEventListener('click', newChat);

homeLink.addEventListener('click', (event) => {
	event.preventDefault();
	newChat();
});

document.querySelectorAll('.example').forEach((button) => {
	button.addEventListener('click', () => ask(button.textContent));
});

async function ask(rawQuestion) {
	const question = rawQuestion.trim();
	if (question === '' || busy) {
		return;
	}

	busy = true;
	sendButton.disabled = true;
	welcome.hidden = true;
	newChatButton.hidden = false;
	input.value = '';
	input.style.height = 'auto';

	addQuestion(question);
	const answer = addAnswerPlaceholder();
	const started = performance.now();

	try {
		const response = await fetch('/api/ask?q=' + encodeURIComponent(question), {
			headers: { Accept: 'application/json' },
		});
		const data = await response.json();

		if (!response.ok) {
			showError(answer, data.error ?? 'Unbekannter Fehler');
			return;
		}

		const seconds = ((performance.now() - started) / 1000).toFixed(1);
		showAnswer(answer, data.answer, data.sources, seconds);
	} catch (error) {
		showError(answer, 'Keine Verbindung zum Server.');
	} finally {
		busy = false;
		sendButton.disabled = false;
		input.focus();
	}
}

function addQuestion(text) {
	const node = document.getElementById('tpl-question').content.cloneNode(true);
	node.querySelector('.bubble').textContent = text;
	chat.append(node);
	scrollDown();
}

function addAnswerPlaceholder() {
	const node = document.getElementById('tpl-answer').content.cloneNode(true);
	const message = node.querySelector('.msg');
	const bubble = node.querySelector('.bubble');

	bubble.innerHTML = '<span class="typing"><span class="dots"><i></i><i></i><i></i></span>Suche in den Dokumenten …</span>';
	chat.append(node);
	scrollDown();

	return message;
}

function showAnswer(message, answer, sources, seconds) {
	const bubble = message.querySelector('.bubble');
	bubble.replaceChildren(...withCitations(answer));

	const list = message.querySelector('.sources');
	sources.forEach((source, index) => list.append(sourceCard(source, index + 1)));

	message.querySelector('.meta').textContent =
		sources.length === 0
			? `Keine passende Quelle · ${seconds} s`
			: `${sources.length} Quelle(n) · ${seconds} s`;

	scrollDown();
}

function showError(message, text) {
	message.classList.add('msg-error');
	message.querySelector('.bubble').textContent = text;
	scrollDown();
}

// Macht aus "[1]" im Antworttext kleine Markierungen – ohne innerHTML
function withCitations(text) {
	const parts = text.split(/(\[\d+\])/);

	return parts.map((part) => {
		if (/^\[\d+\]$/.test(part)) {
			const mark = document.createElement('span');
			mark.className = 'cite';
			mark.textContent = part.slice(1, -1);
			return mark;
		}
		return document.createTextNode(part);
	});
}

function sourceCard(source, number) {
	const card = document.createElement('details');
	card.className = 'source';

	const summary = document.createElement('summary');

	const num = document.createElement('span');
	num.className = 'cite';
	num.textContent = number;

	const file = document.createElement('span');
	file.className = 'file';
	file.textContent = source.filename;

	const section = document.createElement('span');
	section.className = 'section';
	section.textContent = 'Abschnitt ' + source.section;

	const score = document.createElement('span');
	score.className = 'score';
	score.title = 'Ähnlichkeit (Kosinus)';
	const bar = document.createElement('span');
	bar.className = 'bar';
	const fill = document.createElement('span');
	fill.style.width = Math.round(Math.max(0, Math.min(1, source.score)) * 100) + '%';
	bar.append(fill);
	score.append(bar, source.score.toFixed(2));

	summary.append(num, file, section, score);

	const excerpt = document.createElement('p');
	excerpt.className = 'excerpt';
	excerpt.textContent = source.excerpt;

	card.append(summary, excerpt);
	return card;
}

function scrollDown() {
	chat.scrollTop = chat.scrollHeight;
}

async function checkHealth() {
	try {
		const response = await fetch('/health');
		statusBadge.textContent = response.ok ? 'Bereit' : 'Datenbank nicht erreichbar';
		statusBadge.className = 'status ' + (response.ok ? 'ok' : 'down');
	} catch (error) {
		statusBadge.textContent = 'Server nicht erreichbar';
		statusBadge.className = 'status down';
	}
}

checkHealth();
input.focus();