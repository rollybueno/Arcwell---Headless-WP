# Arcwell configuration reference

## Set up without code

1. Open **Settings → Arcwell → Connect your website** as a WordPress administrator.
2. Enter the public frontend **Website address**, for example `https://www.example.com`.
3. Click **Generate ID** for the website identity, then **Generate key** for each security key. Each key is generated independently using the browser's cryptographically secure random generator. No terminal is needed.
4. Click **Save connection settings**. Blank key fields keep previously saved keys. Invalid values are rejected without saving any of the submitted connection settings.
5. Use **Copy ID** and **Copy key** to transfer the values to the matching settings in the frontend hosting dashboard, or share them privately with whoever manages that website. This WordPress form does not configure the frontend automatically. **Show key / Hide key** lets you inspect or manually copy a saved key if clipboard access is unavailable.
6. Follow the WPGraphQL installation and homepage selection links if either still needs attention. Then queue a connection test and refresh after the worker runs.

Replacing an existing identity or key prompts for confirmation. Save only when you are ready to update the frontend too. Changing identity prevents old-source jobs from being replayed; changing the preview key invalidates old preview sessions. Technical constant names and command-line instructions are available under **Advanced hosting setup** for hosting teams.

### Storage and hosting overrides

Form values are stored in the WordPress `arcwell_connection` option with autoload disabled. Secrets are stored in the database (not encrypted by this plugin), so protect database access and backups. They are not registered as public REST settings, included in page HTML on load, or returned in diagnostics. Administrator-only Show/Copy requests use a nonce-protected POST and non-cacheable responses. Generation does not save anything until the form is submitted.

Precedence is **PHP constant → environment variable → saved form value**. Hosting-managed fields are locked and cannot be overwritten or revealed by this form. Empty or invalid hosting overrides still take precedence; ask the host to correct or remove them. Optional `ARCWELL_ENVIRONMENT` remains a hosting-only diagnostic setting. The form uses the saved fallback after a host override is removed.

## Exact names

Each name below works as either a PHP constant in `wp-config.php` or an environment variable available to PHP. A defined constant takes precedence, even if it is empty or invalid. Hosting values override saved form values.

| Name | Required | Value |
| --- | --- | --- |
| `ARCWELL_FRONTEND_URL` | Yes | Frontend origin, e.g. `https://www.example.com`. No path, query, fragment or credentials. Production requires HTTPS. |
| `ARCWELL_SOURCE_ID` | Yes | Stable identity for this CMS: 8–100 ASCII letters, numbers, underscores or hyphens. Example: `arcwell-production`. Use a different ID for each environment. |
| `ARCWELL_PREVIEW_SECRET` | Yes | Independent random secret of at least 32 bytes for signed previews. Must match the frontend preview secret. |
| `ARCWELL_WEBHOOK_SECRET` | Yes | A different random secret of at least 32 bytes for publishing webhooks. Must match the frontend webhook secret. |
| `ARCWELL_ENVIRONMENT` | No | Diagnostic label, e.g. `production`, `staging` or `local`. Defaults to an empty label. Does not change WordPress environment behavior or permit HTTP. |

## Configure using wp-config.php

Generate two secrets by running this command twice on a trusted machine. Each result is a fresh 64-character hexadecimal secret:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Insert the following before the “stop editing” comment in `wp-config.php`. Replace the URL, identity and both secret placeholders. Do not reuse the literal example secrets.

```php
define('ARCWELL_FRONTEND_URL', 'https://www.example.com');
define('ARCWELL_SOURCE_ID', 'arcwell-production');
define('ARCWELL_PREVIEW_SECRET', 'REPLACE_WITH_FIRST_GENERATED_SECRET');
define('ARCWELL_WEBHOOK_SECRET', 'REPLACE_WITH_SECOND_GENERATED_SECRET');
define('ARCWELL_ENVIRONMENT', 'production'); // Optional label.
```

Keep secrets out of source control, screenshots, support tickets and browser bundles. Use separate secrets in staging. When rotating a secret, update WordPress and the frontend together; rotating the preview secret invalidates active launch links and sessions.

## Configure using environment variables

Set the same five names in your hosting provider's environment settings, with the values described above. Do not add the PHP constants in this case: the plugin reads the environment directly. A `.env` file alone does not load variables into WordPress. Ensure they reach the PHP / PHP-FPM worker and the CLI cron process; restart services if your host requires it.

## WordPress prerequisites and local development

Activate WPGraphQL 2.22.3 or later. Publish a Page and select it under **Settings → Reading → A static page**. These are separate checklist items, not Arcwell constants.

HTTP origins are allowed only when WordPress reports `local` or `development`. For local development, add:

```php
define('WP_ENVIRONMENT_TYPE', 'local');
```

Setting `ARCWELL_ENVIRONMENT` to `local` does not enable HTTP. Keep HTTPS on production and staging installations.

## Frontend and worker

The frontend must implement `/api/arcwell/preview`, `/api/arcwell/preview/exit` and `/api/arcwell/revalidate`. Match the source, origin and two secrets to the server helper's `source`, `frontendOrigin`, `previewSecret` and `webhookSecret` configuration. The frontend-only `WP_PREVIEW_USERNAME` and `WP_PREVIEW_APP_PASSWORD` are credentials for authenticated WordPress preview reads; they are not Arcwell plugin constants and must never enter a browser bundle. See [frontend.md](frontend.md) for authorization, cookies and cache invalidation.

Schedule the following once per minute using the hosting scheduler, the correct installation path and operating-system user:

```sh
wp cron event run --due-now --path=/path/to/wordpress
```

After configuring system cron, add `define('DISABLE_WP_CRON', true);` to `wp-config.php`. Without a reliable worker, queued events remain pending.

## Verify and troubleshoot

1. Reload Settings → Arcwell and complete each checklist item. A ready checklist validates configuration, not frontend reachability.
2. Select **Queue connection test**, wait for the scheduled worker, then **Refresh**.
3. Confirm the test is `sent` with an HTTP 2xx response. An empty activity list means no events have been queued; it does not demonstrate a successful connection.
4. If a value still appears missing, check spelling, constant precedence, the source ID format and whether PHP receives the environment variables. Secret length is measured after trimming surrounding whitespace.
5. For delivery failures, verify the frontend handler, matching source and webhook secret, HTTPS/network access and clock synchronization. Fix the cause before retrying the event.

If the worker has never run, configure the scheduler. If the dashboard cannot load activity, confirm the signed-in user has administrator permissions and reload to renew the WordPress REST nonce.
