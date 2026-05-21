# Workplace Task Manager (Laravel)

Workplace Task Manager is a Laravel 13 application for handling workplace issue reports received from WhatsApp (webhook integration to be added later), dispatching them as tasks, assigning tasks to employees, and tracking completion.

This step delivers project foundation only:
- Laravel 13 setup (PHP 8.3 compatible)
- Filament admin panel setup
- Laravel Sanctum setup for future mobile API tokens
- Spatie Permission setup for RBAC
- Seeders for roles, permissions, and initial admin user

## Tech Stack

- PHP 8.3+
- Laravel 13
- MySQL
- Filament 5
- Laravel Sanctum
- Spatie Laravel Permission
- Database queue driver

## Roles

- `super_admin`
- `admin`
- `dispatcher`
- `supervisor`
- `employee`

## Permissions Seeded

- `dashboard.view`
- `inbox.view`
- `inbox.convert_to_task`
- `tasks.view`
- `tasks.create`
- `tasks.assign`
- `tasks.update_status`
- `tasks.complete`
- `users.view`
- `users.manage`
- `roles.view`
- `roles.manage`
- `reports.view`
- `settings.api.manage`

## Local Setup

1. Install dependencies:

```bash
php C:\laragon\bin\composer\composer.phar install
```

2. Create environment file:

```bash
copy .env.example .env
```

3. Generate app key:

```bash
php artisan key:generate
```

4. Configure database in `.env` (MySQL):

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=workplace_task_manager
DB_USERNAME=root
DB_PASSWORD=
```

5. Run migrations and seeders:

```bash
php artisan migrate
php artisan db:seed
```

6. Start development server:

```bash
php artisan serve
```

## Dashboard Access

- URL: `http://127.0.0.1:8000/admin`
- Login email from `.env`: `ADMIN_USER_EMAIL` (default `admin@taskmanager.local`)
- Login password from `.env`: `ADMIN_USER_PASSWORD` (default `ChangeMe123!`)

## ngrok Setup

For exposing the app publicly (webhook testing), set:

```dotenv
APP_URL=https://your-ngrok-domain.ngrok-free.dev
ASSET_URL=https://your-ngrok-domain.ngrok-free.dev
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,localhost:8000,127.0.0.1:8000,your-ngrok-domain.ngrok-free.dev
TRUSTED_PROXIES=*
SESSION_SECURE_COOKIE=true
```

Then run:

```bash
php artisan optimize:clear
```

## Queue Notes

Queue driver is set to `database`. Start a worker when testing background jobs:

```bash
php artisan queue:work
```

## WhatsApp Outbound (Meta Cloud API)

Outbound messaging is controlled by:

- `WHATSAPP_OUTBOUND_ENABLED=false`
- `WHATSAPP_ACCESS_TOKEN=...`
- `WHATSAPP_PHONE_NUMBER_ID=...`
- `WHATSAPP_API_BASE_URL=https://graph.facebook.com`
- `WHATSAPP_GRAPH_VERSION=v23.0`

When outbound is disabled, the system logs outbound records in `whatsapp_messages` without calling Meta.

## Inbound Webhook Providers

Inbound provider routes:

- `POST /webhooks/inbound-message` (Manual JSON webhook)
- `GET /webhooks/meta/whatsapp` (Meta verify)
- `POST /webhooks/meta/whatsapp` (Meta inbound)
- `POST /webhooks/twilio/whatsapp` (Twilio inbound form webhook)
- `POST /webhooks/360dialog/whatsapp` (360dialog inbound)
- `POST /webhooks/bridge/heartbeat` (WhatsApp Web Bridge heartbeat)

Backward-compatible Meta endpoint:

- `GET/POST /webhooks/whatsapp`

## API Settings From Dashboard

You can manage WhatsApp API runtime settings from Filament:

- Open the user menu (top-right) and click `API Settings`.
- Update provider, outbound toggle, phone number ID, base URL, and graph version.
- You can rotate `verify_token` and `access_token` without editing `.env`.
- For `WhatsApp Web Bridge`, set bridge secret and monitor bridge status panel (`connected/stale/unknown`) with last heartbeat/message times.
- Enable `Use selected provider only` to force inbound processing from one provider and ignore other provider webhooks.

## WhatsApp Contacts Routing Page

You can manage phone-number-to-branch/location mapping from:

- `Admin -> Task Management -> WhatsApp Contacts`

Use this page to:

- Add or edit inbound phone numbers.
- Set `Default Branch / Location` (for example: `ميجا 6`).
- Optionally set a default department.

When converting WhatsApp inbox messages into tasks, these defaults are auto-filled (and can still be edited before save).

## Employee & Role Management

New admin pages are available under `Administration`:

- `Employees`: create/edit employee accounts with:
  - name
  - email
  - password
  - role (single role assignment)
  - department
  - work location
- `Roles`: create/edit roles and assign permissions.

Smart shortcut:

- From `Employees` page header, use `Manage Roles` button to jump directly to role management.

Permissions:

- `settings.api.manage` (granted to `super_admin` and `admin` through RBAC seeding).

## In-App Notifications

Filament database notifications are enabled. Workflow notifications are generated for:

- New inbound WhatsApp messages.
- WhatsApp message conversion to task.
- Task assignment (to employee).
- Task rejection (to dispatcher/admin roles).
- Task completion (to dispatcher/admin roles).

Make sure migrations are up to date and queue worker is running for queued notification channels.

## Task Reports

Task reports are available in the admin panel under `Reports > Task Reports`.

Included report metrics and breakdowns:

- Total / new / pending assignment / in progress / completed / rejected-cancelled / overdue.
- Average completion time.
- WhatsApp messages converted to tasks.
- Grouping by department, category, employee, priority, status, and source.

Filters:

- Date range
- Department
- Employee
- Status
- Priority
- Source

## Mobile API (Sanctum)

The project now includes mobile-ready Sanctum endpoints:

- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`
- `GET /api/my-tasks`
- `GET /api/my-tasks/{task}`
- `POST /api/my-tasks/{task}/accept`
- `POST /api/my-tasks/{task}/start`
- `POST /api/my-tasks/{task}/comment`
- `POST /api/my-tasks/{task}/complete`
- `POST /api/my-tasks/{task}/reject`
- `POST /api/my-tasks/{task}/attachments`

Postman payload examples are available at:

- `docs/postman/mobile-api-examples.md`

## WhatsApp Web Bridge (Baileys, read-only)

A local Node.js bridge is available in:

- `whatsapp-bridge/`

It listens to normal WhatsApp group messages (read-only) and forwards them to Laravel inbound webhook. It also sends heartbeat updates to show bridge status in API Settings.

Quick start:

```bash
cd whatsapp-bridge
npm install
cp .env.example .env
npm run start
```

Full setup and test steps:

- `whatsapp-bridge/README.md`

## Important Files

- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Models/User.php`
- `app/Support/Rbac.php`
- `app/Services/Authorization/RbacInitializationService.php`
- `database/seeders/RolesAndPermissionsSeeder.php`
- `database/seeders/AdminUserSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `config/task_manager.php`
- `config/permission.php`
- `config/sanctum.php`

## Manual Smoke Test Checklist

1. Visit `/admin` and log in with seeded admin credentials.
2. Confirm `roles` and `permissions` tables are populated.
3. Confirm seeded admin user has `super_admin` role.
4. Confirm API route `/api/user` returns authenticated user with `auth:sanctum` token.
5. Confirm queue worker starts successfully with `php artisan queue:work`.

## Security Notes

- Do not expose `INBOUND_BRIDGE_SECRET` publicly.
- `X-Bridge-Token` is required for bridge inbound/heartbeat when bridge secret is configured.
- Webhook routes are public by design but protected by provider validation/token checks.
