/* support-assistant/support_chat.js - Support Assistant Interactivity Logic */

// Global Toggle State
function toggleSupportChat() {
    const panel = document.getElementById('supportChatPanel');
    if (!panel) return;

    if (panel.classList.contains('open')) {
        panel.classList.remove('open');
        setTimeout(() => {
            if (!panel.classList.contains('open')) {
                panel.style.display = 'none';
            }
        }, 250); // Match CSS transition duration
    } else {
        panel.style.display = 'flex';
        // Force reflow
        panel.offsetHeight;
        panel.classList.add('open');
        scrollToBottom();
    }
}

function closeSupportChat() {
    const panel = document.getElementById('supportChatPanel');
    if (!panel) return;
    panel.classList.remove('open');
    setTimeout(() => {
        if (!panel.classList.contains('open')) {
            panel.style.display = 'none';
        }
    }, 250);
}

// Scroll to bottom helper
function scrollToBottom() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

// Append message helper
function appendMessage(text, isUser = false) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;

    const messageDiv = document.createElement('div');
    messageDiv.classList.add('chat-message');
    messageDiv.classList.add(isUser ? 'user-message' : 'bot-message');
    messageDiv.textContent = text;

    chatMessages.appendChild(messageDiv);
    scrollToBottom();
}

// Simulated typing indicator (adds premium feel)
function showTypingIndicator() {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return null;

    const indicator = document.createElement('div');
    indicator.id = 'typingIndicator';
    indicator.classList.add('chat-message', 'bot-message');
    indicator.style.color = '#999';
    indicator.style.fontStyle = 'italic';
    indicator.textContent = 'Assistant is typing...';

    chatMessages.appendChild(indicator);
    scrollToBottom();
    return indicator;
}

// Fetch response from Gemini API backend
function getBotResponseAPI(text, callback) {
    const baseUrl = typeof window.GREEN_FORENSICS_BASE_URL !== 'undefined' ? window.GREEN_FORENSICS_BASE_URL : '';
    const url = `${baseUrl}/support-assistant/support_chat_api.php`;

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message: text })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success === true && typeof data.reply !== 'undefined') {
            callback(data.reply);
        } else {
            callback(data.reply || "Sorry, I cannot connect to the support assistant right now. Please contact the Super Administrator.");
        }
    })
    .catch(error => {
        console.error('Support Chat API error:', error);
        callback("Sorry, I cannot connect to the support assistant right now. Please contact the Super Administrator.");
    });
}

// Handle Form Submit
function handleChatSubmit(event) {
    if (event) event.preventDefault();

    const input = document.getElementById('chatInput');
    if (!input) return;

    const text = input.value.trim();
    if (text === '') return;

    // Send user message
    appendMessage(text, true);
    input.value = '';

    // Trigger Bot response with typing indicator
    const typing = showTypingIndicator();

    getBotResponseAPI(text, (reply) => {
        if (typing && typing.parentNode) {
            typing.parentNode.removeChild(typing);
        }
        appendMessage(reply, false);
    });
}

// Handle Suggestion Click
function sendSuggestion(questionText) {
    // Send user action
    appendMessage(questionText, true);

    // Bot response with typing indicator
    const typing = showTypingIndicator();

    getBotResponseAPI(questionText, (reply) => {
        if (typing && typing.parentNode) {
            typing.parentNode.removeChild(typing);
        }
        appendMessage(reply, false);
    });
}
