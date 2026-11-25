.. title:: Requirements

Requirements
############

.. sidebar:: Table of Contents
  .. contents::

Environment
===========

This application requires at least **PHP 8.4** with the extensions `ctype`, `iconv`, `intl` and `pdo_mysql`. It runs
on any web server which can serve a PHP application via PHP-FPM or similar, e.g. `Nginx <https://nginx.org/>`_ or
`Apache <https://httpd.apache.org/>`_. Since users log in with the password of their email account, the web server
should only be reachable via **HTTPS**.

The database has to be **MariaDB** (recommended, version 10.11 or later) or **MySQL** (version 8.0 or later). Other
database systems are not supported, because Postfix and Dovecot access the very same database.

For easy :doc:`deployment <installation>` and dependency management you need `Composer <https://getcomposer.org/>`_
(version 2.3 or later) up and running, too.

Mail Server
===========

The Post Office is designed for a mail server with

* **Postfix** (version 3.6 or later) with MySQL support (Debian/Ubuntu package `postfix-mysql`),
* **Dovecot** (version 2.3 or 2.4) with MySQL and Sieve support (Debian/Ubuntu packages `dovecot-mysql`,
  `dovecot-sieve` and `dovecot-managesieved`), and
* **Python 3** with the MySQL client library (Debian/Ubuntu package `python3-mysqldb`) for the policy service which
  implements members-only mailing lists.

The mail server and the web server can run on different machines, as long as both can access the database. All
examples in this documentation assume a single server running Debian 12 or 13.

See :doc:`Mail Server <../mailserver/index>` for how to set up Postfix and Dovecot.

Dependencies
============

Although all application-level dependencies are handled by `Composer <https://getcomposer.org/>`_ (or are bundled with
the installation package) we would like to make some honorable mentions here:

Without great frameworks like `Symfony <https://symfony.com/>`_ and `Doctrine <https://www.doctrine-project.org/>`_
this application wouldn't be feasible.
