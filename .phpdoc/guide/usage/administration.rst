.. title:: Administration

Administration
##############

.. sidebar:: Table of Contents
  .. contents::

The features described on this page are only available to administrators.

Domains
=======

All domains are listed under "Manage". Click "New domain" to add a domain. Before the mail server can receive emails for
a new domain, its MX record has to point to your mail server.

A domain can only be deleted when it has no accounts left. This makes sure that no accounts get lost by accident.

Under "Domain managers" on the page of a domain you can appoint users who are allowed to manage all accounts and
mailing lists of that domain (see :doc:`first steps <first-steps>`).

TLS Policies
============

By default, Postfix encrypts emails sent to other mail servers whenever possible (DANE or opportunistic TLS). TLS
policies override this for specific recipient domains, e.g. to enforce encryption with a verified certificate for a
business partner, or to disable encryption for a mail server with a broken TLS setup.

"Recipient domain"
  The domain of the recipients, e.g. ``example.com``. A leading dot (e.g. ``.example.com``) includes all subdomains.

"Policy"
  One of the `TLS security levels <https://www.postfix.org/TLS_README.html#client_tls_levels>`_ of Postfix:

  ``none``
    Never encrypt.
  ``may``
    Encrypt if possible, but don't verify the certificate.
  ``encrypt``
    Always encrypt, but don't verify the certificate.
  ``dane`` and ``dane-only``
    Verify the certificate with DANE, if possible (``dane``) or always (``dane-only``).
  ``fingerprint``
    Always encrypt and verify the certificate's fingerprint, which has to be given in the parameters.
  ``verify`` and ``secure``
    Always encrypt and verify the certificate against trusted certificate authorities.

"Parameters"
  Optional parameters for the policy, e.g. ``match=hostname`` or ``match=AA:BB:CC:...`` for ``fingerprint``. See the
  `Postfix documentation <https://www.postfix.org/postconf.5.html#smtp_tls_policy_maps>`_ for details.

Disabled TLS policies are ignored.

Blacklist
=========

The mail server doesn't accept emails from senders on the blacklist.

"Sender"
  An email address, e.g. ``spammer@example.com``, or a domain, e.g. ``example.com``. A domain includes all its
  subdomains.

"Action"
  "Reject" refuses the email, so the sender is notified that it wasn't delivered. "Discard" accepts the email, but
  silently drops it, so the sender doesn't notice.

Disabled blacklist entries are ignored.

.. note::

  The blacklist checks the envelope sender of an email, which isn't necessarily the sender shown by email clients.
  It is meant to block persistent unwanted senders, not as a replacement for a spam filter.
