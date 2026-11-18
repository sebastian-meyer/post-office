.. title:: Testing

Testing
#######

.. sidebar:: Table of Contents
  .. contents::

Once Postfix and Dovecot are running and you have created a domain and a mailbox as described in
:doc:`first steps <../usage/first-steps>`, check that everything works together. The examples use the mailbox
``john@example.com``.

Dovecot
=======

.. code-block:: shell

  # Check the password (Dovecot asks for it)
  sudo doveadm auth test john@example.com

  # Show the user data, which should include the quota
  sudo doveadm user john@example.com

  # List all mailboxes
  sudo doveadm user '*'

Postfix
=======

.. code-block:: shell

  # Should print "example.com"
  sudo postmap -q example.com proxy:mysql:/etc/postfix/sql/domains.cf

  # Should print "1"
  sudo postmap -q john@example.com proxy:mysql:/etc/postfix/sql/accounts.cf

  # Send a test email to the mailbox
  echo "Hello John!" | sendmail john@example.com

Then log in to the mailbox with an email client using the following settings and check that the test email arrived:

==========  ======================  ====  ==========  ======================
Protocol    Server                  Port  Security    Username
==========  ======================  ====  ==========  ======================
IMAP        ``mail.example.com``    993   SSL/TLS     ``john@example.com``
SMTP        ``mail.example.com``    465   SSL/TLS     ``john@example.com``
==========  ======================  ====  ==========  ======================

Alternatively, port 143 (IMAP) and 587 (SMTP) with STARTTLS can be used.

Finally, send an email from that mailbox to an external address and reply to it.

Troubleshooting
===============

If something doesn't work, the logs usually tell you why:

.. code-block:: shell

  sudo journalctl -f -u postfix@- -u dovecot -u postfix-custom-policyd

Typical problems are:

``Temporary lookup failure`` or ``Server configuration problem``
  Postfix can't reach the database, the policy service or another service configured in ``main.cf``. Check the
  database password in ``/etc/postfix/sql/*.cf``, that the policy service is running, and that you removed all
  **OPTIONAL** components you don't use.

``Password mismatch`` or ``unknown user``
  Check that the account is enabled and has a password, and that you log in with the full email address. For Dovecot
  2.4 check that ``auth_username_format`` is not overridden in ``conf.d/20-lmtp.conf``.

``Permission denied`` in ``/var/mail/virtual/``
  The mail directory must belong to the user ``vmail``, and ``first_valid_uid`` must not be higher than the user ID of
  ``vmail``.
