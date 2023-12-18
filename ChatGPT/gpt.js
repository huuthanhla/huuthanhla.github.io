const msgerForm = get(".msger-inputarea");
const msgerInput = get(".msger-input");
const msgerChat = get(".msger-chat");
const msgerClear = get(".msger-clear-area");

// Icons made by Freepik from www.flaticon.com
const BOT_IMG = "https://upload.wikimedia.org/wikipedia/commons/0/04/ChatGPT_logo.svg";
const PERSON_IMG = "https://picsum.photos/200?blur=3";
const BOT_NAME = "BOT";
const PERSON_NAME = "GUEST";

var isLoading = false;

showFirstMsg();

// ChatGPT
function sendMessage(msgText) {
	console.log(msgText);
	insertLoading();

	$.ajax({
		url: "https://api.openai.com/v1/chat/completions",
		headers: {
			"Content-Type": "application/json",
			"Authorization": "Bearer your_key"
		},
		type: "POST",
		dataType: "json",
		data: JSON.stringify({
			"model": "gpt-3.5-turbo",
			"messages": [
				{"role": "system", "content": "You are a helpful assistant."},
				{"role": "user", "content": msgText}
			]
		}),
		success: function(data) {
			var response = data.choices[0].message.content;
			removeLoading();
			appendMessage(BOT_NAME, BOT_IMG, "left", response);
		},
		error: function (jqXHR, exception) {
			console.error(jqXHR.status);

			var msg = '';
			if (jqXHR.status === 0) {
				msg = 'Not connect.\n Verify Network.';
			} else if (jqXHR.status == 404) {
				msg = 'Requested page not found. [404]';
			} else if (jqXHR.status == 500) {
				msg = 'Internal Server Error [500].';
			} else if (exception === 'parsererror') {
				msg = 'Requested JSON parse failed.';
			} else if (exception === 'timeout') {
				msg = 'Time out error.';
			} else if (exception === 'abort') {
				msg = 'Ajax request aborted.';
			} else {
				msg = jqXHR.responseText;
			}

			console.error(msg);

			removeLoading();
			setTimeout(function() {
				appendMessage('ERROR [' + jqXHR.status + ']', BOT_IMG, "left", msg);
			}, 200);
			
		}
	});
}

msgerForm.addEventListener("submit", event => {
  event.preventDefault();

  const msgText = msgerInput.value;
  if (!msgText) return;
  if (isLoading) {
	msgerInput.value = "";
	return;
  }

  appendMessage(PERSON_NAME, PERSON_IMG, "right", msgText);
  msgerInput.value = "";

  sendMessage(msgText);
});

function appendMessage(name, img, side, text) {
  const msgHTML = `
	<div class="msg ${side}-msg">
	  <!-- <div class="msg-img" style="background-image: url(${img})"></div> -->

	  <div class="msg-bubble">
		<div class="msg-info">
		  <div class="msg-info-name">${name}</div>
		  <div class="msg-info-time">${formatDate(new Date())}</div>
		</div>

		<div class="msg-text">${text}</div>
	  </div>
	</div>
  `;

  msgerChat.insertAdjacentHTML("beforeend", msgHTML);
  msgerChat.scrollTop += 500;
}

function insertLoading() {
  isLoading = true;

  const msgHTML = `
	<div id="loading" class="msg left-msg">
	  <div class="msg-bubble">
		<div class="msg-info">
			<div class="msg-text loading">
				<div class="typing typing-1"></div>
				<div class="typing typing-2"></div>
				<div class="typing typing-3"></div>
			</div>
	  </div>
	</div>
  `;

  msgerChat.insertAdjacentHTML("beforeend", msgHTML);
  msgerChat.scrollTop += 500;
}

function removeLoading() {
	const loading = document.getElementById("loading");
	loading.remove();
	
	setTimeout(function() {
		isLoading = false;
	}, 200);
}

msgerClear.addEventListener("submit", event => {
  event.preventDefault();
  msgerChat.innerHTML = '';

  setTimeout(function() {
	showFirstMsg();
  }, 500);
});

function showFirstMsg() {
	appendMessage(BOT_NAME, BOT_IMG, "left", 'Xin chào! Tôi là trợ lý ảo GPT. Bạn cần giải đáp vấn đề gì?');
}

// Utils
function get(selector, root = document) {
  return root.querySelector(selector);
}

function formatDate(date) {
  const h = "0" + date.getHours();
  const m = "0" + date.getMinutes();

  return `${h.slice(-2)}:${m.slice(-2)}`;
}