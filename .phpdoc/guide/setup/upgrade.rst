.. title:: Upgrading

Upgrading
#########

.. sidebar:: Table of Contents
  .. contents::

The Post Office comes with the `Composer Updater <https://code.opencultureconsulting.com/composer-updater/>`_, which
adds the command ``composer app:upgrade`` to the application. It downloads and installs a new version, updates the
dependencies, runs the database migrations and compiles the frontend assets in one go. If anything goes wrong while the
new version is installed, the previous version is restored automatically.

.. caution::

  Just like during the :doc:`installation <installation>`, all commands should be run as your unprivileged
  Apache/Nginx system user or the PHP-FPM/PHP-CGI user (depending on your environment)!

Step by Step
============

#. Check the :doc:`changelog <../changelog>` for breaking changes and for changes to the
   :doc:`mail server configuration <../mailserver/index>`.

#. Make a backup of your database before upgrading (see :doc:`maintenance <maintenance>`).

   .. code-block:: shell

     sudo mariadb-dump --single-transaction vmail | gzip > /var/backups/vmail-$(date +%F).sql.gz

#. Show the available versions and upgrade to the latest stable release.

   .. code-block:: shell

     # List all versions available for your environment
     composer app:upgrade --list

     # Upgrade to the latest stable release
     composer app:upgrade

   The command asks for confirmation before it changes anything. It only offers versions whose requirements (e.g. the
   PHP version and extensions) are met by your environment.

   .. hint::

     If you want to install a version other than the latest stable release, append it to the command. Downgrading or
     reinstalling the current version additionally requires the ``--force`` option.

     .. code-block:: shell

       # Install a specific version
       composer app:upgrade 1.1.0

     See ``composer app:upgrade --help`` for all options.

#. If you have cached your environment variables with ``composer dump-env prod`` (see
   :doc:`configuration <configuration>`), run that command again, because the new version may come with new settings.

#. Check the new version's :doc:`configuration <configuration>` options and adjust your ``.env.local`` if needed. The
   example configuration files in ``./mailserver/`` are updated together with the application.

What Happens During an Upgrade
==============================

#. The selected version is downloaded from `Packagist <https://packagist.org/packages/sebastian-meyer/post-office>`_.

#. The ``./vendor/`` directory and every file that is going to be replaced or deleted are backed up to
   ``./.composer-updater-backup/``.

#. The application's files are replaced with those of the new version. The directories ``./assets/``, ``./bin/``,
   ``./config/``, ``./doc/``, ``./mailserver/``, ``./migrations/``, ``./src/``, ``./templates/`` and ``./translations/``
   are replaced entirely, so files which are not part of the new version are deleted. Your local settings in
   ``.env.local`` (and ``.env.local.php``), the cache and logs in ``./var/`` and any secrets in ``./config/secrets/``
   are kept.

   .. caution::

     Don't put your own files into the directories listed above, because they are deleted on upgrades.

#. The dependencies of the new version are installed with ``composer install`` and the cache is cleared.

#. The backup is removed.

#. All changes to the database schema that came with the new version are applied and the frontend assets are compiled
   (``composer run-script post-upgrade-cmd``).

Troubleshooting
===============

If the download, the replacement of files or the installation of the dependencies fails, the previous version is
restored from the backup and keeps working. Fix the cause and run ``composer app:upgrade`` again.

If the database migrations or the compilation of the assets fail, the new version is already installed and **no**
rollback takes place, because the previous version might not work with a partially migrated database. Fix the cause and
run the remaining tasks manually:

.. code-block:: shell

  # Show which migrations have not been executed yet
  php bin/console doctrine:migrations:status

  # Run the database migrations and compile the frontend assets
  composer run-script post-upgrade-cmd

.. caution::

  MariaDB can't roll back changes to the database schema, so a failed migration may leave the database in an
  intermediate state. In this case restore the backup, fix the cause and run the migrations again.

If restoring the previous version fails, the backup is kept in ``./.composer-updater-backup/`` and further upgrades are
refused until you have restored the files manually and removed that directory.

Upgrading from v1.1.0 or Earlier
================================

Versions up to and including v1.1.0 don't include the Composer Updater yet, so the upgrade to the next version has to
be done manually once. All later upgrades can be done with ``composer app:upgrade``.

If you have installed the application with the ``--keep-vcs`` option as recommended for those versions, use Git to
check out the new version (e.g. v1.2.0):

.. code-block:: shell

  # Check out the new version of the application
  git fetch --tags
  git checkout v1.2.0

  # Install the dependencies
  composer install --no-dev --optimize-autoloader

  # Run the database migrations and compile the frontend assets
  composer run-script post-upgrade-cmd

Otherwise, download the new version from the `GitHub releases page <https://github.com/sebastian-meyer/post-office/releases>`_,
replace the application's files with those of the new version (keeping your ``.env.local``) and run the last two
commands above. Because Composer can't detect the installed version without Git, you have to tell it once on your next
upgrade:

.. code-block:: shell

  # Upgrade from v1.2.0 to the latest stable release
  COMPOSER_ROOT_VERSION=1.2.0 composer app:upgrade
