/* assets/js/support_chat.js - Rule-Based Support Assistant Logic */

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

// Main logic for processing responses
function getBotResponse(userText) {
    const text = userText.toLowerCase().trim();

    // Security check first: Password safety rule
    if (text.includes('password') || text.includes('passcode') || text.includes('credential')) {
        return "For security reasons, never share your password. If you need help with your password or your account is locked, please use the Request Unlock page or contact the Super Administrator directly.";
    }

    // Question matchers
    if (text.includes('register') || text.includes('sign up') || text.includes('signup') || text.includes('create account') || text.includes('registration')) {
        return "To register, fill out the registration form, select your requested role, agree to the Terms of Use and Privacy Policy, then submit your account for Super Admin approval.";
    }
    
    if (text.includes('pending') || text.includes('approve') || text.includes('status') || text.includes('wait') || text.includes('review')) {
        return "Your account is pending because the Super Administrator still needs to review and approve your registration.";
    }
    
    if (text.includes('upload') || text.includes('fingerprint') || text.includes('webcam') || text.includes('image') || text.includes('submit trial') || text.includes('trial')) {
        return "Go to the Student Dashboard, open Upload Fingerprint Images or Submit Trial Data, then upload a fingerprint image or use the webcam capture feature.";
    }
    
    if (text.includes('faculty') || text.includes('validation') || text.includes('validate') || text.includes('score') || text.includes('remarks') || text.includes('evaluat')) {
        return "After a student submits a fingerprint trial, the Faculty Researcher reviews the AI preliminary scores, enters the final evaluation, adds remarks, and approves, rejects, or marks the trial as Needs Revision.";
    }
    
    if (text.includes('lock') || text.includes('failed login') || text.includes('attempts') || text.includes('locked') || text.includes('unlock')) {
        return "If your account is locked after multiple failed login attempts, wait 15 minutes or use the Request Unlock page for Super Admin review.";
    }
    
    if (text.includes('contact') || text.includes('admin') || text.includes('support') || text.includes('super admin') || text.includes('help')) {
        return "Please contact the Super Administrator or submit a request through the system support form.";
    }

    // Default Fallback
    return "Sorry, I may not have an answer for that yet. Please contact the Super Administrator for further assistance.";
}

// Fetch response from Gemini API backend
function getBotResponseAPI(text, callback) {
    const prefix = typeof supportChatPrefix !== 'undefined' ? supportChatPrefix : '';
    const url = prefix + 'ajax_support_chat.php';

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
        if (data.status === 'success' && typeof data.reply !== 'undefined') {
            callback(data.reply);
        } else {
            // Fallback if status is fallback or error
            callback(getBotResponse(text));
        }
    })
    .catch(error => {
        console.error('Support Chat API error, falling back to rule-based logic:', error);
        callback(getBotResponse(text));
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
