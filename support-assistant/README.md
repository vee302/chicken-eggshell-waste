# Green Forensics Support Assistant

A dynamic, AI-powered floating support widget integrated into the Green Forensics Evaluating System. It queries the Google Gemini API to assist users with system guidance, registrations, lockouts, fingerprint uploads, and validation procedures.

## Folder Structure

```text
support-assistant/
├── support_widget.php   # HTML structure of the widget
├── support_chat.css     # CSS styles (positioned bottom-right)
├── support_chat.js      # Frontend interaction logic (stateless)
├── support_chat_api.php # Backend Gemini API gateway
└── README.md            # This documentation file
```

## How to Include the Widget

Add the PHP include statement just before the closing `</body>` tag on any page where the support chat should appear:

### For Root Pages (e.g. `login.php`, `register.php`):
```php
<?php include __DIR__ . '/support-assistant/support_widget.php'; ?>
```

### For Pages inside Subfolders (e.g. `student/`, `faculty/`, `admin/`, `police-partner/`):
```php
<?php include dirname(__DIR__) . '/support-assistant/support_widget.php'; ?>
```

## Required `.env` Variables

Ensure these environment variables are set in your local `.env` file (or Railway Environment Variables in production):

```ini
GEMINI_API_KEY="your-google-ai-studio-api-key"
GEMINI_MODEL="gemini-3.5-flash"
APP_ENV="local" # Set to "production" in Railway deployment
```

## How to Test

1. Launch your local webserver (Apache/MySQL via XAMPP) or deploy to Railway.
2. Load any page containing the widget (like `login.php`).
3. Click the floating chat button in the bottom-right corner to open the panel.
4. Type `"hi"` or click on any quick help buttons.
5. Verify the AI generates a warm, helpful response without throwing console errors.
