.. title:: Configuration

Configuration
#############

.. sidebar:: Table of Contents
  .. contents::

The Post Office is configured with environment variables. The defaults are defined in the file ``.env`` in the
application's root directory. **Don't change that file**, because it is overwritten on upgrades. Instead, put your own
settings into a file ``.env.local``, which takes precedence. Real environment variables (e.g. set by your web server or
PHP-FPM pool) take precedence over both files.

.. code-block:: shell

  # .env.local
  APP_SECRET=a-random-string-of-32-characters
  DATABASE_URL="mysql://postoffice:a-secure-password@127.0.0.1:3306/vmail?serverVersion=11.8.0-MariaDB&charset=utf8mb4"
  DEFAULT_URI=https://postoffice.example.com/
  ADMIN_IDS=1

.. hint::

  To save the overhead of parsing the ``.env`` files on every request, you can compile them into a PHP file with
  ``composer dump-env prod``. Remember to run that command again whenever you change ``.env.local``!

Settings
========

``ADMIN_IDS``
  Comma-separated list of the IDs of all accounts with administrator privileges, e.g. ``1,5``. Administrators can
  manage all domains, accounts and mailing lists. See :doc:`first steps <../usage/first-steps>` for how to find the ID
  of an account.

  Administrator accounts can't be deleted in the Post Office. To revoke the administrator privileges of an account,
  remove its ID from this list.

``APP_SECRET``
  A random string which is used to secure sessions and forms, and to encrypt the users' passwords kept in the session
  for sending emails. It should be at least 32 characters long and must be kept secret. Changing it logs out all users.

``APP_ENV``
  The environment the application runs in. Always use ``prod`` (the default) for production systems.

``DATABASE_URL``
  The connection to the database in the format
  ``mysql://USER:PASSWORD@HOST:PORT/DATABASE?serverVersion=VERSION&charset=utf8mb4``.

  Set ``serverVersion`` to the version of your database server, e.g. ``11.8.0-MariaDB`` for MariaDB 11.8 or ``8.4.0``
  for MySQL 8.4. Special characters in the password have to be URL-encoded (e.g. ``%40`` instead of ``@``).

``DEFAULT_URI``
  The URL of your Post Office installation, e.g. ``https://postoffice.example.com/``. This is used to generate links
  when there is no HTTP request, e.g. in console commands.

``DKIM_SELECTORS``
  Comma-separated list of the DKIM selectors your mail server signs emails with, e.g. ``mail`` (the ``Selector`` in
  OpenDKIM's configuration, or the selector in Rspamd's ``dkim_signing.conf``). The :doc:`DNS check
  <../usage/administration>` looks for the DKIM keys under these selectors; at least one of them must have a valid
  key. If empty, DKIM keys aren't checked.

``DNS_RESOLVERS``
  Comma-separated list of the IP addresses of the name servers used by the DNS check, e.g. ``127.0.0.1``. If empty,
  the name servers in ``/etc/resolv.conf`` are used. Use a resolver that validates DNSSEC (like the one your mail
  server needs for DANE), otherwise the DNS check can only tell that a zone is signed, but not that the signatures are
  valid.

``MAIL_HOSTNAME``
  The host name of your mail server, i.e. Postfix' ``myhostname``, e.g. ``mail.example.com``. The DNS check uses it to
  verify that the MX records of a domain point to your mail server, that its SPF record allows your mail server to
  send emails, and that the reverse DNS of your mail server's IP addresses matches. If empty, these checks are skipped.

``MAILER_DSN``
  The mail server used to send the emails written by administrators, domain managers and list managers (see
  :doc:`managing accounts <../usage/managing>`), e.g. ``smtp://mail.example.com:587``. Use the submission port of
  your own mail server: the emails are sent with the email address and password of the logged-in user, so any user
  name and password in ``MAILER_DSN`` are ignored. If the certificate of the mail server doesn't match the host name
  (e.g. when connecting to ``localhost``), add ``?verify_peer=0``.

  The default ``null://null`` discards all emails.

Languages
=========

The user interface is available in English and German. The language is chosen automatically according to the
preferred language set in the user's web browser. English is used if none of the available languages matches.
