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

4. **Add Groq AI Support Assistant Key**
   Obtain an API key from Groq Console and paste it inside the `GROQ_API_KEY` placeholder:
   ```ini
   GROQ_API_KEY="gsk_..."
   GROQ_MODEL="llama-3.3-70b-versatile"
   ```
   *Note: If no API key is specified, the assistant will automatically fall back to Pollinations AI or its internal offline rule-based logic.*

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
  * `GROQ_API_KEY`
  * `GROQ_MODEL`
  * `SESSION_TIMEOUT_MINUTES`
  * `LOGIN_MAX_ATTEMPTS`
  * `LOGIN_LOCKOUT_MINUTES`
* The application will natively read these variables from the server environment.

### AWS RDS and Railway database credentials

Use `DB_USERNAME` and `DB_PASSWORD` for every database host, including RDS.
Legacy/provider aliases are supported in this order after the canonical variables:
`DB_USER`, `MYSQLUSER`, `RDS_USERNAME` for the username; and
`DB_PASS`, `MYSQLPASSWORD`, `RDS_PASSWORD` for the password.
An explicitly empty password remains empty. There is no hardcoded RDS password.

### First administrator setup

Automatic schema setup and `database.sql` create no default accounts or sample
research records. On a fresh installation, run this once from a trusted server
terminal with the application's database environment configured (Bash example):

```bash
read -r -s -p 'New administrator password: ' ADMIN_PASSWORD
printf '\n'
printf '%s\n' "$ADMIN_PASSWORD" | php scripts/create_admin.php admin@example.com "System Administrator"
unset ADMIN_PASSWORD
```

Replace the email and name. Use a unique password with 12–72 bytes, uppercase,
lowercase, a number and a symbol, without surrounding whitespace. The command
accepts the password through standard input and refuses to create another account
if a super administrator already exists. It is blocked from HTTP access.

For existing installations, this update does not delete accounts or change their
passwords. Change passwords for previously seeded accounts, and disable any unused
demo accounts through the existing administration tools. If the old hardcoded RDS
password was used, rotate it in AWS and set the replacement in `DB_PASSWORD`.

Schema migrations preserve saved system settings and update only `schema_version`.
Failed registrations remove their new proof upload; previously orphaned uploads
are not deleted automatically.
