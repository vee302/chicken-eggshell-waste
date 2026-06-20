# Green Forensics Evaluating System

An innovative, sustainable fingerprint powder evaluation platform leveraging chicken eggshell waste.

---

## Environment Setup Instructions

To secure credentials and prevent private secrets from being committed to version control, this project uses an environment variable configuration system.

### Local Installation & Setup

1. **Copy the Environment Template**
   Duplicate `.env.example` and name the new file `.env` in the root directory:
   ```bash
   cp .env.example .env
   ```

2. **Configure Database Credentials**
   Open your newly created `.env` file and fill in your local MySQL/MariaDB database credentials:
   ```ini
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=green_forensics
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Configure Application Keys**
   Generate a long, random secret value and assign it to the `APP_KEY` variable:
   ```ini
   APP_KEY="your-strong-random-key-here"
   ```

4. **Add Gemini Support Assistant Key (Optional)**
   Obtain an API key from Google AI Studio and paste it inside the `GEMINI_API_KEY` placeholder:
   ```ini
   GEMINI_API_KEY="your-gemini-api-key"
   ```
   *Note: If no API key is specified, the assistant will automatically fall back to its internal, offline rule-based logic.*

5. **Local Rules**
   * **Do not commit `.env` to GitHub**. It is excluded automatically by `.gitignore`.
   * Only commit configuration placeholders inside `.env.example`.

---

## Production Deployment (Railway)

When deploying this application on Railway or any cloud provider:
* **Do not upload the `.env` file.**
* Configure the required variables directly inside the **Railway Environment Variables (Variables tab)** dashboard:
  * `APP_NAME`
  * `APP_ENV` (set to `production`)
  * `APP_URL`
  * `APP_KEY`
  * `DB_HOST`
  * `DB_PORT`
  * `DB_DATABASE`
  * `DB_USERNAME`
  * `DB_PASSWORD`
  * `GEMINI_API_KEY`
  * `SESSION_TIMEOUT_MINUTES`
  * `LOGIN_MAX_ATTEMPTS`
  * `LOGIN_LOCKOUT_MINUTES`
* The application will natively read these variables from the server environment.
