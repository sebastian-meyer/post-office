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
  A random string which is used to secure sessions and forms. It should be at least 32 characters long and must be
  kept secret. Changing it logs out all users.

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

``MAILER_DSN`` and ``MESSENGER_TRANSPORT_DSN``
  These are not used yet and are reserved for upcoming features. Leave them at their defaults.

Languages
=========

The user interface is available in English and German. The language is chosen automatically according to the
preferred language set in the user's web browser. English is used if none of the available languages matches.
