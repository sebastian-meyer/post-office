.. title:: Preparation

Preparation
###########

.. sidebar:: Table of Contents
  .. contents::

Packages
========

Install Postfix, Dovecot and the policy service's dependencies. On Debian and Ubuntu:

.. code-block:: shell

  sudo apt install postfix postfix-mysql \
      dovecot-imapd dovecot-lmtpd dovecot-mysql dovecot-sieve dovecot-managesieved \
      python3 python3-mysqldb

When asked about the type of mail server configuration during the installation of Postfix, choose
**No configuration**.

Find out which version of Dovecot you have, because the configuration differs between 2.3 and 2.4:

.. code-block:: shell

  dovecot --version

Database Access
===============

Postfix, Dovecot and the policy service connect to the database as the read-only user ``vmail``, which you created
during the :doc:`installation <../setup/installation>`. If you didn't, do it now:

.. code-block:: sql

  CREATE USER 'vmail'@'127.0.0.1' IDENTIFIED BY 'another-secure-password';
  GRANT SELECT ON vmail.* TO 'vmail'@'127.0.0.1';

The example configuration files expect the database server on ``127.0.0.1``, the database ``vmail`` and the user
``vmail``. Adjust them if your setup differs.

Mail Storage
============

All emails are stored in ``/var/mail/virtual/`` and belong to the system user ``vmail``:

.. code-block:: shell

  # Create the system user and group "vmail"
  sudo adduser --system --group --home /var/mail/virtual --no-create-home --shell /usr/sbin/nologin vmail

  # Create the mail directory and the directory for global Sieve scripts
  sudo mkdir -p /var/mail/virtual/default/sieve
  sudo chown -R vmail:vmail /var/mail/virtual
  sudo chmod 770 /var/mail/virtual

  # Note the UID and GID of the new user, you need them later
  id vmail

The mailbox ``john@example.com`` is stored in ``/var/mail/virtual/example.com/john/``. Dovecot creates the directory
when the first email arrives or the user logs in for the first time.

Copy the global Sieve scripts from ``./mailserver/dovecot/sieve/`` to ``/var/mail/virtual/default/sieve/``:

``spam-global.sieve``
  Moves emails which were marked as spam into the folder "Junk".

``learn-spam.sieve`` and ``learn-ham.sieve`` (**OPTIONAL**)
  Train `Rspamd <https://rspamd.com/>`_ when a user moves an email into or out of the folder "Junk". Only copy them if
  you use Rspamd, otherwise remove the "Spam Training" sections from the Dovecot configuration.

.. code-block:: shell

  sudo cp ./mailserver/dovecot/sieve/*.sieve /var/mail/virtual/default/sieve/
  sudo chown -R vmail:vmail /var/mail/virtual/default

TLS Certificates
================

Postfix and Dovecot need a TLS certificate for the hostname of your mail server (e.g. ``mail.example.com``). The example
configuration expects a certificate from `Let's Encrypt <https://letsencrypt.org/>`_ obtained with
`Certbot <https://certbot.eff.org/>`_:

.. code-block:: shell

  sudo apt install certbot
  sudo certbot certonly --standalone -d mail.example.com

  # Diffie-Hellman parameters for Dovecot
  sudo openssl dhparam -out /etc/letsencrypt/ssl-dhparams.pem 4096

.. hint::

  Postfix and Dovecot don't notice renewed certificates by themselves. Add a deploy hook to reload them after each
  renewal:

  .. code-block:: shell

    echo -e '#!/bin/sh\nsystemctl reload postfix dovecot' | sudo tee /etc/letsencrypt/renewal-hooks/deploy/reload-mail
    sudo chmod 755 /etc/letsencrypt/renewal-hooks/deploy/reload-mail

DNS Records
===========

For every domain you want to receive emails for, its MX record has to point to your mail server. For example:

.. code-block:: text

  example.com.        IN  MX    10 mail.example.com.
  mail.example.com.   IN  A     192.0.2.25
  mail.example.com.   IN  AAAA  2001:db8::25

The reverse DNS (PTR) record of your mail server's IP addresses should resolve back to ``mail.example.com``, otherwise
many mail servers will reject your emails. You should also publish SPF, DKIM and DMARC records for your domains.
