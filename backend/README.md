# ChickIntel PHP API (local XAMPP)

This API is for local development with the repository's Angular app and XAMPP. It uses PHP sessions, PDO prepared statements, per-user inventory ownership, and MariaDB/MySQL.

## Code layout

- `public/index.php` bootstraps configuration, HTTP headers, the session, and API dispatch.
- `src/router.php` maps API routes to their handlers.
- `src/auth.php` owns registration, login, and session/CSRF helpers.
- `src/inventory-data.php` formats and retrieves inventory records and writes activity history.
- `src/request-validation.php` validates JSON request bodies and inventory fields.
- `src/database.php` creates the PDO connection; `src/http.php` handles CORS, JSON responses, and API errors.

## Local setup

1. Import `database/schema.sql` into the running XAMPP MySQL/MariaDB server. The schema creates the `chickintel_inventory` database and its tables.
2. Add this include to `C:\xampp\apache\conf\httpd.conf`:

   ```apache
   Include "C:/Users/Admin/ChickIntel-Inventory/backend/apache-alias.conf"
   ```

   Add it before the SSL include near the bottom of the file. The alias file serves only `backend/public`; database configuration and SQL remain outside Apache's document root.
3. Validate the Apache configuration in an Administrator PowerShell:

   ```powershell
   & 'C:\xampp\apache\bin\httpd.exe' -t
   ```

   If it reports `Syntax OK`, restart Apache from the XAMPP Control Panel.
4. Confirm the API at `http://127.0.0.1/chickintel-api/index.php?route=health`.
5. Start the Angular app with `ionic serve --host 127.0.0.1 --port 4200`. Use the exact host in this URL so the browser session cookie and configured CORS origin match.

The checked-in `config.php` contains the standard local XAMPP defaults (`root` with a blank password). For custom local settings, copy `config.local.example.php` to `config.local.php` and edit that ignored local-only file. Never commit database credentials. Use a dedicated restricted database account and HTTPS before exposing the API outside this development machine.

## Routes

- `GET auth/csrf`, `POST auth/register`, `POST auth/login`, `GET auth/me`, `POST auth/logout`
- `GET/POST inventory`, `PATCH inventory/{id}`, `POST inventory/{id}/restock`, `DELETE inventory/{id}`
- `GET history`

Mutating requests require the session's `X-CSRF-Token`. The API returns `401` for signed-out requests and scopes all inventory/history queries to the signed-in user.

## Live updates

While the Inventory or Inventory History screen is open, the Angular app checks the authenticated inventory and history endpoints every five seconds. Other open sessions signed in to the same account see additions, edits, restocks, deletions, and history changes on the next check. Polling pauses while the browser tab is hidden and resumes when it becomes visible. The pages show the last successful sync time and an error message if the API cannot be reached.

This is near-real-time HTTP polling, not a persistent push connection such as WebSockets or Firebase. It requires no additional XAMPP service or database changes.

Registration is open for local testing. There is no email verification, password reset, role management, account approval, or production deployment configuration in this stage.
