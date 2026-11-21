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

Only administrators can set a new password for an existing account (e.g. when a user forgot it). Domain managers can
only set the password of new accounts, otherwise they could take over accounts which manage other domains or mailing
lists.

DNS Check
=========

"DNS check" on the page of a domain shows whether the DNS records of the domain are set up for sending and receiving
emails with your mail server. Each check is marked as "OK", "Note" (optional or not checked), "Warning" (works, but
could be improved) or "Error" (affects the delivery of emails), and shows the records found. For missing records, it
suggests a record to add.

MX
  The MX records must point to your mail server, and their hosts must have IP addresses.

Reverse DNS
  The IP addresses of your mail server must point back to its host name. This is the same for all domains, and is set
  by the provider of the IP addresses, not in the domain's DNS zone.

DNSSEC and DANE
  DANE lets other mail servers verify the certificate of your mail server with TLSA records. It requires the zone to
  be signed with DNSSEC.

SPF, DKIM and DMARC
  These records protect your domain from forged senders. Without them, many mail servers reject your emails or mark
  them as spam. The SPF record must allow your mail server to send emails, a DKIM key must be published for the
  selector your mail server signs with, and the DMARC policy should be "quarantine" or "reject".

MTA-STS and TLS-RPT
  Both are optional. MTA-STS tells other mail servers to deliver emails to your domain only encrypted, TLS-RPT asks
  them to report problems with that.

Some checks depend on settings of the Post Office (``MAIL_HOSTNAME``, ``DKIM_SELECTORS`` and ``DNS_RESOLVERS``, see
:doc:`configuration <../setup/configuration>`) and are skipped if they are missing. Changes to DNS records can take a
while to show up, depending on their TTL.

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
