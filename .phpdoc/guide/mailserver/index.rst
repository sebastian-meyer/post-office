.. title:: Mail Server

Mail Server
###########

.. sidebar:: Table of Contents
  .. contents::

The Post Office doesn't send or receive any emails itself. It only manages the database which Postfix and Dovecot use to
look up domains, accounts and passwords. Any change made in the Post Office takes effect on the mail server
immediately, there is no need to reload or restart anything.

This chapter describes how to set up Postfix and Dovecot to use the database of the Post Office.

.. toctree::
  :maxdepth: 2

  preparation
  postfix
  dovecot
  testing

How It Works
============

* **Postfix** receives incoming emails on port 25. It looks up the recipient's domain and address in the database and
  either hands the email over to Dovecot (for mailboxes), forwards it (for aliases and mailing lists), or rejects it.
* Users send emails via Postfix on port 587 (STARTTLS) or 465 (TLS). Postfix asks **Dovecot** to check their
  passwords and only allows them to send from their own address.
* **Dovecot** stores the emails delivered by Postfix and lets users access them via IMAP on port 143 (STARTTLS) or 993
  (TLS). Users can manage their Sieve filters (e.g. vacation notices) via ManageSieve on port 4190.
* A small **policy service** written in Python checks whether senders are allowed to post to members-only mailing
  lists.

The account types of the Post Office are implemented by the mail server as follows:

============  ==========================================================================================================
Type          Behavior on the mail server
============  ==========================================================================================================
Mailbox       Receives and stores emails, can log in to IMAP, ManageSieve and SMTP. Has a storage quota.
Alias         Forwards all emails to exactly one destination address, which may be on another mail server.
Mailing list  Forwards all emails to all of its members. Optionally adds a ``Reply-To`` header with the list address,
              and optionally only accepts emails from its members.
Send-only     Can log in to SMTP to send emails from its address, but doesn't receive any emails.
Catch-all     An alias or mailing list without a username. Receives all emails to addresses of its domain which
              don't exist.
============  ==========================================================================================================

Disabled accounts are ignored by the mail server, i.e. they can't log in and emails to them are rejected (or go to
the catch-all address, if there is one).

Configuration Files
===================

Ready-to-use configuration files are included in the directory ``./mailserver/`` of your Post Office installation (or
in the `GitHub repository <https://github.com/sebastian-meyer/post-office/tree/main/mailserver>`_). Every file starts
with a comment telling you where it belongs. Placeholders in square brackets (e.g. ``[PASSWORD]``) have to be replaced
with the actual values for your setup.

==================================================  ================================================================
File                                                Destination
==================================================  ================================================================
``postfix/main.cf``                                 ``/etc/postfix/main.cf``
``postfix/master.cf``                               ``/etc/postfix/master.cf``
``postfix/sql/*.cf``                                ``/etc/postfix/sql/*.cf``
``postfix/submission_header_cleanup``               ``/etc/postfix/submission_header_cleanup``
``postfix/whitelist_client``                        ``/etc/postfix/whitelist_client``
``postfix/postfix-custom-policyd.py``               ``/usr/local/bin/postfix-custom-policyd.py``
``postfix/postfix-custom-policyd.service``          ``/etc/systemd/system/postfix-custom-policyd.service``
``dovecot/2.4/local.conf``                          ``/etc/dovecot/local.conf`` (Dovecot 2.4)
``dovecot/2.3/local.conf``                          ``/etc/dovecot/local.conf`` (Dovecot 2.3)
``dovecot/2.3/local-sql.conf.ext``                  ``/etc/dovecot/local-sql.conf.ext`` (Dovecot 2.3)
``dovecot/sieve/*.sieve``                           ``/var/mail/virtual/default/sieve/*.sieve``
==================================================  ================================================================

.. note::

  The example configuration is a complete and hardened setup, which also includes some optional components for spam
  filtering, DKIM signing and sender rewriting. Those are marked as **OPTIONAL** in the files. Setting them up is
  beyond the scope of this documentation, so remove them if you don't use them.
