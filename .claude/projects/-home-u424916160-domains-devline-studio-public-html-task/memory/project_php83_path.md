---
name: PHP 8.3 CLI path
description: Use /opt/alt/php83/usr/bin/php for artisan commands — default CLI php is 8.2 which is incompatible
type: project
---

The server's default CLI PHP is 8.2.30, but the project requires PHP 8.3+. Use `/opt/alt/php83/usr/bin/php` for all artisan commands.

**Why:** `composer.json` requires `^8.3`, and the default `php` binary is 8.2 which causes artisan to fail with a platform error.
**How to apply:** Always prefix artisan commands with `/opt/alt/php83/usr/bin/php` instead of bare `php`.
