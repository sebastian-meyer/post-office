.. title:: Maintenance

Maintenance
###########

.. sidebar:: Table of Contents
  .. contents::

Backups
=======

The database contains all domains, accounts, mailing lists and passwords, so it should be backed up regularly, e.g.
with a daily cron job:

.. code-block:: shell

  sudo mariadb-dump --single-transaction vmail | gzip > /var/backups/vmail-$(date +%F).sql.gz

The emails themselves are stored by Dovecot in ``/var/mail/virtual/`` and have to be backed up separately.

Deleted Mailboxes
=================

When a mailbox is deleted in the Post Office, only its database entry is removed. Its emails stay on disk in the
directory ``/var/mail/virtual/DOMAIN/USERNAME/`` until the server administrator deletes them:

.. code-block:: shell

  # Remove the emails of the deleted mailbox john@example.com
  sudo rm -r /var/mail/virtual/example.com/john/

.. caution::

  If a new mailbox with the same address is created before the directory is removed, the new owner gets access to all
  emails of the previous one!

To find directories of deleted mailboxes, compare the directories on disk with the mailboxes known to Dovecot:

.. code-block:: shell

  # List all mailboxes known to Dovecot
  sudo doveadm user '*'

  # List all mail directories on disk
  sudo find /var/mail/virtual -mindepth 2 -maxdepth 2 -type d -not -path '*/default/*'

Logs
====

The Post Office writes errors to the standard error output, which ends up in the error log of your web server or PHP-FPM
(e.g. ``/var/log/nginx/error.log`` or ``/var/log/php8.4-fpm.log``). Postfix, Dovecot and the policy service log to the
system journal:

.. code-block:: shell

  sudo journalctl -u postfix@- -u dovecot -u postfix-custom-policyd

Cache
=====

If the Post Office behaves strangely after changing its configuration or files, clear the cache:

.. code-block:: shell

  php bin/console cache:clear
