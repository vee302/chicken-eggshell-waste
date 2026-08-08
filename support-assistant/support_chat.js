// Global State & Dual Storage (localStorage + sessionStorage) Persistence
let activeChatHistory = [];

function saveSupportChatHistory() {
    try {
        const data = JSON.stringify(activeChatHistory);
        localStorage.setItem('gf_support_chat_history', data);
        sessionStorage.setItem('gf_support_chat_history', data);
    } catch (e) {}
}

function saveSupportChatOpenState(isOpen) {
    try {
        const val = isOpen ? 'true' : 'false';
        localStorage.setItem('gf_support_chat_is_open', val);
        sessionStorage.setItem('gf_support_chat_is_open', val);
    } catch (e) {}
}

function restoreSupportChatState() {
    try {
        const savedHistory = localStorage.getItem('gf_support_chat_history') || sessionStorage.getItem('gf_support_chat_history');
        const rawIsOpen = localStorage.getItem('gf_support_chat_is_open') || sessionStorage.getItem('gf_support_chat_is_open');
        const isOpen = rawIsOpen === 'true';

        if (savedHistory) {
            const parsed = JSON.parse(savedHistory);
            if (Array.isArray(parsed) && parsed.length > 0) {
                const chatMessages = document.getElementById('chatMessages');
                if (chatMessages) {
                    chatMessages.innerHTML = '';
                    activeChatHistory = [...parsed]; // Retain restored messages in-memory

                    parsed.forEach(msg => {
                        if (msg.useUnlockButton) {
                            appendBotMessageWithUnlock(msg.text, true, false);
                        } else if (!msg.isUser && msg.text && msg.text.includes('request_unlock.php')) {
                            appendBotMessageWithUnlock(msg.text, false, false);
                        } else {
                            appendMessage(msg.text, msg.isUser, msg.imageBase64, false);
                        }
                    });
                }
            }
        }

        if (isOpen) {
            const panel = document.getElementById('supportChatPanel');
            if (panel) {
                panel.style.display = 'flex';
                panel.offsetHeight;
                panel.classList.add('open');
                scrollToBottom();
            }
        }
    } catch (e) {}
}

// Run immediately and on DOM/Window load events
restoreSupportChatState();
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', restoreSupportChatState);
} else {
    setTimeout(restoreSupportChatState, 50);
}
window.addEventListener('load', restoreSupportChatState);

// Global Toggle State
function toggleSupportChat() {
    const panel = document.getElementById('supportChatPanel');
    if (!panel) return;

    if (panel.classList.contains('open')) {
        panel.classList.remove('open');
        saveSupportChatOpenState(false);
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
        saveSupportChatOpenState(true);
        scrollToBottom();
    }
}

function closeSupportChat() {
    const panel = document.getElementById('supportChatPanel');
    if (!panel) return;
    panel.classList.remove('open');
    saveSupportChatOpenState(false);
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

let selectedChatImageBase64 = null;

// Handle image selection from file input with automatic canvas optimization
function handleChatImageSelected(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        alert('Please select a valid image file (PNG, JPG, WEBP).');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            // Resize image to max 800px for fast AI Vision processing
            const maxDim = 800;
            let w = img.width;
            let h = img.height;

            if (w > maxDim || h > maxDim) {
                if (w > h) {
                    h = Math.round((h * maxDim) / w);
                    w = maxDim;
                } else {
                    w = Math.round((w * maxDim) / h);
                    h = maxDim;
                }
            }

            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);

            selectedChatImageBase64 = canvas.toDataURL('image/jpeg', 0.85);

            const previewBar = document.getElementById('chatImagePreviewBar');
            const previewThumb = document.getElementById('chatImagePreviewThumb');

            if (previewBar && previewThumb) {
                previewThumb.src = selectedChatImageBase64;
                previewBar.style.display = 'flex';
            }
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

// Clear selected image
function clearSelectedChatImage() {
    selectedChatImageBase64 = null;
    const fileInput = document.getElementById('chatImageInput');
    const previewBar = document.getElementById('chatImagePreviewBar');
    const previewThumb = document.getElementById('chatImagePreviewThumb');
    
    if (fileInput) fileInput.value = '';
    if (previewThumb) previewThumb.src = '';
    if (previewBar) previewBar.style.display = 'none';
}

// Append message helper
function appendMessage(text, isUser = false, imageBase64 = null, shouldSave = true) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;

    const messageDiv = document.createElement('div');
    messageDiv.classList.add('chat-message');
    messageDiv.classList.add(isUser ? 'user-message' : 'bot-message');

    if (text) {
        const textDiv = document.createElement('div');
        textDiv.textContent = text;
        messageDiv.appendChild(textDiv);
    }

    if (imageBase64) {
        const img = document.createElement('img');
        img.src = imageBase64;
        img.alt = 'Uploaded image';
        img.classList.add('chat-message-image');
        messageDiv.appendChild(img);
    }

    chatMessages.appendChild(messageDiv);
    scrollToBottom();

    if (shouldSave) {
        activeChatHistory.push({ text: text, isUser: isUser, imageBase64: imageBase64, useUnlockButton: false });
        saveSupportChatHistory();
    }
}

// Safe DOM-based message renderer that appends bot messages with links/buttons
function appendBotMessageWithUnlock(text, useButton = false, shouldSave = true) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;

    const messageDiv = document.createElement('div');
    messageDiv.classList.add('chat-message', 'bot-message');

    const baseUrl = typeof window.GREEN_FORENSICS_BASE_URL !== 'undefined' ? window.GREEN_FORENSICS_BASE_URL : '';
    const unlockUrl = baseUrl + '/request_unlock.php';

    if (useButton) {
        // Appends explanation text safely
        messageDiv.appendChild(document.createTextNode(text));

        // Appends styled button inside container safely
        const btnContainer = document.createElement('div');
        btnContainer.style.marginTop = '10px';
        
        const btn = document.createElement('a');
        btn.href = unlockUrl;
        btn.className = 'chat-unlock-btn';
        btn.textContent = 'Open Request Unlock Page';
        
        btnContainer.appendChild(btn);
        messageDiv.appendChild(btnContainer);
    } else {
        // Replace request_unlock.php with a link element safely
        const keyword = 'request_unlock.php';
        const parts = text.split(keyword);
        
        for (let i = 0; i < parts.length; i++) {
            if (parts[i]) {
                messageDiv.appendChild(document.createTextNode(parts[i]));
            }
            if (i < parts.length - 1) {
                const link = document.createElement('a');
                link.href = unlockUrl;
                link.className = 'chat-inline-link';
                link.textContent = keyword;
                messageDiv.appendChild(link);
            }
        }
    }

    chatMessages.appendChild(messageDiv);
    scrollToBottom();

    if (shouldSave) {
        activeChatHistory.push({ text: text, isUser: false, imageBase64: null, useUnlockButton: useButton });
        saveSupportChatHistory();
    }
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

let cooldownInterval = null;

// Enable chat input UI after block/cooldown ends
function enableSupportChat() {
    if (cooldownInterval) {
        clearInterval(cooldownInterval);
        cooldownInterval = null;
    }
    const input = document.getElementById('chatInput');
    const sendBtn = document.querySelector('.chat-send-btn');
    const suggestionBtns = document.querySelectorAll('.suggestion-btn');
    const toggleBtn = document.querySelector('.chat-suggestions-toggle');

    if (input) {
        input.disabled = false;
        input.value = '';
        input.placeholder = 'Ask a question...';
        input.style.backgroundColor = '';
        input.style.color = '';
        input.style.cursor = '';
    }
    if (sendBtn) {
        sendBtn.disabled = false;
        sendBtn.style.opacity = '1';
        sendBtn.style.cursor = '';
    }
    suggestionBtns.forEach(btn => {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = '';
    });
    if (toggleBtn) {
        toggleBtn.disabled = false;
    }
}

// Format seconds into MM:SS string
function formatCooldownTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
}

// Disable chat input UI if user is blocked with optional countdown timer
function disableSupportChat(remainingSeconds) {
    if (cooldownInterval) {
        clearInterval(cooldownInterval);
        cooldownInterval = null;
    }

    const input = document.getElementById('chatInput');
    const sendBtn = document.querySelector('.chat-send-btn');
    const suggestionBtns = document.querySelectorAll('.suggestion-btn');
    const toggleBtn = document.querySelector('.chat-suggestions-toggle');

    const updateUI = (text) => {
        if (input) {
            input.disabled = true;
            input.value = '';
            input.placeholder = text;
            input.style.backgroundColor = '#f8d7da';
            input.style.color = '#721c24';
            input.style.cursor = 'not-allowed';
        }
    };

    if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.style.opacity = '0.5';
        sendBtn.style.cursor = 'not-allowed';
    }
    suggestionBtns.forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';
    });
    if (toggleBtn) {
        toggleBtn.disabled = true;
    }

    let secs = parseInt(remainingSeconds, 10);
    if (!isNaN(secs) && secs > 0) {
        updateUI(`Access Blocked (Cooldown: ${formatCooldownTime(secs)})`);
        cooldownInterval = setInterval(() => {
            secs--;
            if (secs <= 0) {
                clearInterval(cooldownInterval);
                cooldownInterval = null;
                enableSupportChat();
                appendMessage("Ang 5-minute cooldown timer ay tapos na. Na-unlock na muli ang iyong chat access.", false);
            } else {
                updateUI(`Access Blocked (Cooldown: ${formatCooldownTime(secs)})`);
            }
        }, 1000);
    } else {
        updateUI('Access Blocked (Inappropriate Language)');
    }
}

// Fetch response from Groq AI backend
function getBotResponseAPI(text, imageBase64, callback) {
    const baseUrl = typeof window.GREEN_FORENSICS_BASE_URL !== 'undefined' ? window.GREEN_FORENSICS_BASE_URL : '';
    const url = `${baseUrl}/support-assistant/support_chat_api.php`;

    // Handle optional callback parameter shifting
    if (typeof imageBase64 === 'function') {
        callback = imageBase64;
        imageBase64 = null;
    }

    const payload = { message: text };
    if (imageBase64) {
        payload.image = imageBase64;
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.source === 'blocked' || (data.reply && data.reply.includes('na-block'))) {
            disableSupportChat(data.remaining_seconds);
        }
        if (data.success === true && typeof data.reply !== 'undefined') {
            callback(data.reply, data.source);
        } else {
            callback(data.reply || "Sorry, I cannot connect to the support assistant right now. Please contact the Super Administrator.", data.source);
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
    const imageBase64 = selectedChatImageBase64;

    if (text === '' && !imageBase64) return;

    // Send user message
    appendMessage(text, true, imageBase64);
    input.value = '';
    clearSelectedChatImage();

    // Trigger Bot response with typing indicator
    const typing = showTypingIndicator();

    getBotResponseAPI(text, imageBase64, (reply) => {
        if (typing && typing.parentNode) {
            typing.parentNode.removeChild(typing);
        }
        if (reply && reply.includes('request_unlock.php')) {
            appendBotMessageWithUnlock(reply, false);
        } else {
            appendMessage(reply, false);
        }
    });
}

// Handle Suggestion Click
function sendSuggestion(questionText) {
    // Collapse suggestions panel automatically
    collapseSuggestions();

    // Send user action
    appendMessage(questionText, true);

    // Bot response with typing indicator
    const typing = showTypingIndicator();

    if (questionText === 'Request Account Unlock') {
        setTimeout(() => {
            if (typing && typing.parentNode) {
                typing.parentNode.removeChild(typing);
            }
            appendBotMessageWithUnlock(
                'If your account is locked after multiple failed login attempts, you can request an unlock for Super Admin review. Click the button below to open the Request Unlock page.',
                true
            );
        }, 500);
        return;
    }

    getBotResponseAPI(questionText, (reply) => {
        if (typing && typing.parentNode) {
            typing.parentNode.removeChild(typing);
        }
        if (reply.includes('request_unlock.php')) {
            appendBotMessageWithUnlock(reply, false);
        } else {
            appendMessage(reply, false);
        }
    });
}

// Toggle collapsible suggestions panel
function toggleSuggestions() {
    const suggestions = document.getElementById('chatSuggestions');
    const toggleBtn = document.querySelector('.chat-suggestions-toggle');
    if (!suggestions || !toggleBtn) return;

    if (suggestions.classList.contains('open')) {
        suggestions.classList.remove('open');
        toggleBtn.classList.remove('active');
    } else {
        suggestions.classList.add('open');
        toggleBtn.classList.add('active');
        // Small timeout to allow expand transition before scrolling
        setTimeout(scrollToBottom, 100);
    }
}

// Collapse suggestions panel
function collapseSuggestions() {
    const suggestions = document.getElementById('chatSuggestions');
    const toggleBtn = document.querySelector('.chat-suggestions-toggle');
    if (suggestions) {
        suggestions.classList.remove('open');
    }
    if (toggleBtn) {
        toggleBtn.classList.remove('active');
    }
}
