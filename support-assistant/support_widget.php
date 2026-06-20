<?php
// support-assistant/support_widget.php - HTML Layout for Green Forensics Support Assistant

$prefix = '';
if (file_exists('support-assistant/support_chat.css')) {
    $prefix = '';
} elseif (file_exists('../support-assistant/support_chat.css')) {
    $prefix = '../';
}

// Dynamically determine the base URL directory path for AJAX requests
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$scriptDir = str_replace('\\', '/', $scriptDir);
if ($prefix === '../') {
    $baseUrl = dirname($scriptDir);
} else {
    $baseUrl = $scriptDir;
}
$baseUrl = rtrim(str_replace('\\', '/', $baseUrl), '/') . '/';
$rootUrl = rtrim($baseUrl, '/');
?>
<!-- Support Chat Widget Stylesheet -->
<link rel="stylesheet" href="<?php echo $rootUrl; ?>/support-assistant/support_chat.css">

<!-- Floating Support Chat Button -->
<button id="supportChatBtn" class="support-chat-btn" aria-label="Open support assistant" onclick="toggleSupportChat()">
    <!-- Chat bubble icon (SVG) -->
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
    </svg>
</button>

<!-- Support Chat Panel -->
<div id="supportChatPanel" class="support-chat-panel">
    <!-- Header -->
    <div class="chat-header">
        <div class="chat-header-info">
            <span class="chat-header-dot"></span>
            <h4>Green Forensics Support Team</h4>
        </div>
        <div class="chat-header-controls">
            <button class="chat-control-btn" onclick="toggleSupportChat()" title="Minimize" aria-label="Minimize chat">
                <!-- Minimize (minus) icon -->
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </button>
            <button class="chat-control-btn" onclick="closeSupportChat()" title="Close" aria-label="Close chat">
                <!-- Close (x) icon -->
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>

    <!-- Body -->
    <div class="chat-body" id="chatBody">
        <div class="chat-intro">
            <p class="intro-desc">Chat with our support assistant for help with your questions and system issues.</p>
        </div>
        
        <!-- Messages container -->
        <div class="chat-messages" id="chatMessages">
            <div class="chat-message bot-message">
                Hi! What can I help you with?
            </div>
        </div>

        <!-- Quick Help Toggle Button Wrapper -->
        <div class="chat-suggestions-toggle-wrapper">
            <button type="button" class="chat-suggestions-toggle" onclick="toggleSuggestions()">
                <!-- Help icon (SVG) -->
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span>Quick Help</span>
            </button>
        </div>

        <!-- Suggested quick help questions -->
        <div id="chatSuggestions" class="chat-suggestions">
            <button class="suggestion-btn" onclick="sendSuggestion('How to register?')">How to register?</button>
            <button class="suggestion-btn" onclick="sendSuggestion('Why is my account pending?')">Why is my account pending?</button>
            <button class="suggestion-btn" onclick="sendSuggestion('How to upload fingerprint image?')">How to upload fingerprint image?</button>
            <button class="suggestion-btn" onclick="sendSuggestion('How does faculty validation work?')">How does faculty validation work?</button>
            <button class="suggestion-btn" onclick="sendSuggestion('How to request account unlock?')">How to request account unlock?</button>
            <button class="suggestion-btn" onclick="sendSuggestion('Terms of Use')">Terms of Use</button>
            <button class="suggestion-btn" onclick="sendSuggestion('Privacy Policy')">Privacy Policy</button>
            <button class="suggestion-btn" onclick="sendSuggestion('Contact Super Admin')">Contact Super Admin</button>
            <button class="suggestion-btn" onclick="sendSuggestion('Request Account Unlock')">Request Account Unlock</button>
        </div>
    </div>

    <!-- Input Area -->
    <div class="chat-footer">
        <form id="chatForm" onsubmit="handleChatSubmit(event)">
            <input type="text" id="chatInput" placeholder="Ask a question..." autocomplete="off">
            <button type="submit" class="chat-send-btn" aria-label="Send message">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </form>
    </div>
</div>

<!-- Support Chat Javascript -->
<script>
    window.GREEN_FORENSICS_BASE_URL = "<?php echo $rootUrl; ?>";
</script>
<script src="<?php echo $rootUrl; ?>/support-assistant/support_chat.js"></script>
