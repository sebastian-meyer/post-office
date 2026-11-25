# Post Office

**A self-service web frontend for mail servers running [Postfix](https://www.postfix.org/) and [Dovecot](https://www.dovecot.org/).**

The Post Office manages the domains, mailboxes, aliases and mailing lists of your mail server, which Postfix and Dovecot read from a shared [MariaDB](https://mariadb.org/) or [MySQL](https://www.mysql.com/) database. Every change takes effect immediately, without reloading or restarting anything.

Users manage their own email accounts, and administrators can hand over the management of individual domains and mailing lists to other users. So you don't have to do everything yourself, and nobody needs shell access to the server.

## Features

- **Self-service for users**: everyone logs in with their email address and password to change their name and password, check their storage quota, and see and leave the mailing lists they belong to.
- **Delegated management**: administrators appoint *domain managers*, who manage all accounts and mailing lists of their domains, and domain managers appoint *list managers*, who look after the members and settings of individual mailing lists.
- **All common account types**: mailboxes with storage quota, send-only addresses, aliases, mailing lists (open or members-only) and catch-all addresses are supported.
- **Mail server administration**: TLS policies for outgoing emails and a blacklist of senders, managed right in the browser. Additionally, administrators can perform DNS health checks for mail domains.
- **Bulk emails and notifications**: managers write emails to selected or all addresses of their domains and mailing lists.
- **Secure by default**: strong passwords that are checked against known data breaches, rate-limited logins, and permissions that are checked on every action.
- **Available in English and German**, chosen automatically according to the user's browser language.

## Requirements

- PHP 8.4 or later with the extensions `ctype`, `iconv`, `intl` and `pdo_mysql`
- MariaDB 10.11 or later (recommended) or MySQL 8.0 or later
- A web server which can run PHP applications (e.g. [Nginx](https://nginx.org/) with PHP-FPM, or [Apache](https://httpd.apache.org/) with PHP-FPM or `mod_php`), reachable via HTTPS
- [Composer](https://getcomposer.org/) 2.3 or later
- Postfix 3.6 or later and Dovecot 2.3 or 2.4, both with MySQL support

## Quick Start

The intended and recommended way of installing this application is via [Composer](https://getcomposer.org/). The following command will get you the latest version:

```shell
composer create-project sebastian-meyer/post-office --ask --no-dev
```

All available versions as well as further information about requirements and dependencies can be found on [Packagist](https://packagist.org/packages/sebastian-meyer/post-office).

Then, in short:

1. Create a file `.env.local` with at least your `APP_SECRET`, `DATABASE_URL` and `DEFAULT_URI`.
2. Create the database tables, compile the assets and warm up the cache:

   ```shell
   php bin/console doctrine:migrations:migrate
   php bin/console asset-map:compile
   php bin/console cache:clear
   ```

3. Point your web server's document root to the `./public/` directory.
4. Create the first domain and administrator account:

   ```shell
   php bin/console app:domain:add example.com
   php bin/console app:account:add admin@example.com --name="Jane Doe"
   ```

   Then add the printed account ID to `ADMIN_IDS` in your `.env.local` and log in.

5. Configure Postfix and Dovecot to use the database of the Post Office.

The [installation guide](https://sebastian-meyer.github.io/post-office/guides/setup/installation.html) explains each step in detail, including the database users and a sample Nginx configuration.

## Upgrading

The Post Office comes with the [Composer Updater](https://code.opencultureconsulting.com/composer-updater/), so upgrading to a new version is as easy as:

```shell
composer app:upgrade --list  # list available versions
composer app:upgrade         # upgrade to the latest stable release
```

This downloads and installs the new version and its dependencies, runs the database migrations and compiles the frontend assets. Make a backup of your database first and check the [changelog](https://sebastian-meyer.github.io/post-office/guides/changelog.html) for breaking changes. The [upgrade guide](https://sebastian-meyer.github.io/post-office/guides/setup/upgrade.html) has all the details, including how to upgrade from v1.1.0 or earlier.

## Full Documentation

The full documentation is available on [GitHub Pages](https://sebastian-meyer.github.io/post-office/). It covers

- the [setup](https://sebastian-meyer.github.io/post-office/guides/setup/index.html): requirements, installation, configuration, maintenance and upgrades,
- the [mail server](https://sebastian-meyer.github.io/post-office/guides/mailserver/index.html): how to set up Postfix and Dovecot to work with the Post Office, and
- the [usage](https://sebastian-meyer.github.io/post-office/guides/usage/index.html): first steps, roles, and managing accounts, mailing lists and the mail server.

## Quality Gates

This application follows the highest coding standards of [PHPStan](https://phpstan.org/), [Psalm](https://psalm.dev/), [PHP Mess Detector](https://phpmd.org/), [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/), and complies to [PSR-12](https://www.php-fig.org/psr/psr-12/) code style guidelines to make sure it is reliable, maintainable and easily reusable.

[![PHPCS](https://github.com/sebastian-meyer/post-office/actions/workflows/phpcs.yml/badge.svg)](https://github.com/sebastian-meyer/post-office/actions/workflows/phpcs.yml)
[![PHPMD](https://github.com/sebastian-meyer/post-office/actions/workflows/phpmd.yml/badge.svg)](https://github.com/sebastian-meyer/post-office/actions/workflows/phpmd.yml)
[![PHPStan](https://github.com/sebastian-meyer/post-office/actions/workflows/phpstan.yml/badge.svg)](https://github.com/sebastian-meyer/post-office/actions/workflows/phpstan.yml)
[![Psalm](https://github.com/sebastian-meyer/post-office/actions/workflows/psalm.yml/badge.svg)](https://github.com/sebastian-meyer/post-office/actions/workflows/psalm.yml)
[![PHPUnit](https://github.com/sebastian-meyer/post-office/actions/workflows/phpunit.yml/badge.svg)](https://github.com/sebastian-meyer/post-office/actions/workflows/phpunit.yml)

Assisted-by: Claude Opus 5.5 (Anthropic)

## Contributing

Contributions are welcome! Please read the [contribution guidelines](CONTRIBUTING.md) first.

## License

The Post Office is free software licensed under the [GNU General Public License v3.0 or later](LICENSE).
