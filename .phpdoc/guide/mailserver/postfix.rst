.. title:: Postfix

Postfix
#######

.. sidebar:: Table of Contents
  .. contents::

Step by Step
============

#. Copy the configuration files from ``./mailserver/postfix/`` to ``/etc/postfix/``.

   .. code-block:: shell

     sudo mkdir -p /etc/postfix/sql
     sudo cp ./mailserver/postfix/main.cf ./mailserver/postfix/master.cf /etc/postfix/
     sudo cp ./mailserver/postfix/submission_header_cleanup ./mailserver/postfix/whitelist_client /etc/postfix/
     sudo cp ./mailserver/postfix/sql/*.cf /etc/postfix/sql/

#. Replace all placeholders in square brackets in ``/etc/postfix/main.cf`` and ``/etc/postfix/sql/*.cf``:

   ``[HOSTNAME]``
     The hostname of your mail server, e.g. ``mail.example.com``.

   ``[DOMAIN]``
     The main domain of your mail server, e.g. ``example.com``. The TLS certificate is expected in
     ``/etc/letsencrypt/live/[DOMAIN]/``, adjust the path if you obtained it for the hostname instead.

   ``[IP4]`` and ``[IP6]``
     The public IP addresses of your mail server.

   ``[UID_OF_VMAIL]`` and ``[GID_OF_VMAIL]``
     The user and group ID of the system user ``vmail`` (see ``id vmail``).

   ``[PASSWORD]``
     The password of the database user ``vmail``.

#. Remove or comment out everything marked as **OPTIONAL** in ``main.cf`` and ``master.cf`` that you don't use.

   .. caution::

     Postfix temporarily rejects all emails if a configured policy service, milter or lookup table is unavailable,
     e.g. because SPF checking, Rspamd or PostSRSd is not installed.

#. Protect the files containing the database password and build the lookup table for trusted clients.

   .. code-block:: shell

     sudo chown root:postfix /etc/postfix/sql/*.cf
     sudo chmod 640 /etc/postfix/sql/*.cf
     sudo postmap /etc/postfix/whitelist_client

#. Install the policy service for members-only mailing lists. Replace ``[PASSWORD]`` in the script with the password of
   the database user ``vmail``.

   .. code-block:: shell

     sudo cp ./mailserver/postfix/postfix-custom-policyd.py /usr/local/bin/
     sudo chown root:postfix /usr/local/bin/postfix-custom-policyd.py
     sudo chmod 750 /usr/local/bin/postfix-custom-policyd.py

     sudo cp ./mailserver/postfix/postfix-custom-policyd.service /etc/systemd/system/
     sudo systemctl daemon-reload
     sudo systemctl enable --now postfix-custom-policyd

   .. caution::

     The policy service has to be running, otherwise Postfix temporarily rejects all incoming emails.

#. Check the configuration and restart Postfix.

   .. code-block:: shell

     sudo postfix check
     sudo systemctl restart postfix

Lookup Tables
=============

All lookup tables in ``/etc/postfix/sql/`` are accessed via ``proxymap`` to share database connections between the
Postfix processes. Each of them has to be listed in ``proxy_read_maps`` in ``main.cf``.

``domains.cf`` (``virtual_mailbox_domains``)
  The domains the mail server receives emails for.

``accounts.cf`` (``virtual_mailbox_maps``)
  The enabled mailboxes. Emails to mailboxes are delivered to Dovecot via LMTP (``virtual_transport``).

``aliases.cf`` (``virtual_alias_maps``)
  The destinations of enabled aliases and mailing lists, including catch-all addresses. A catch-all address only
  receives emails to addresses which don't belong to any other enabled account of its domain.

``sendonly.cf`` (``check_recipient_access``)
  Rejects emails to send-only addresses.

``replyto.cf`` (``check_recipient_access``)
  Adds a ``Reply-To`` header with the list address to emails sent to mailing lists with the option "Replies go to the
  list".

``sender-login-maps.cf`` (``smtpd_sender_login_maps``)
  Makes sure users can only send emails from their own address. Applies to ports 587 and 465.

``blacklist.cf`` (``check_sender_access``)
  Rejects or discards emails from senders on the :doc:`blacklist <../usage/administration>`. An entry for a domain
  also applies to all its subdomains.

``tlspolicy.cf`` (``smtp_tls_policy_maps``)
  The :doc:`TLS policies <../usage/administration>` for sending emails to other mail servers.

You can test a lookup table with ``postmap``:

.. code-block:: shell

  # Should print "1" if john@example.com is an enabled mailbox
  sudo postmap -q john@example.com proxy:mysql:/etc/postfix/sql/accounts.cf

  # Should print the members of the mailing list team@example.com
  sudo postmap -q team@example.com proxy:mysql:/etc/postfix/sql/aliases.cf

Policy Service
==============

The policy service ``postfix-custom-policyd.py`` listens on ``127.0.0.1:12345`` and is asked by Postfix for every
recipient (see ``smtpd_recipient_restrictions`` in ``main.cf``). It rejects emails to mailing lists with the option
"Members only" if the sender's address isn't a member of the list.

Ports
=====

==========  ==============  ===================================================================================
Port        Service         Purpose
==========  ==============  ===================================================================================
25          ``smtp``        Receiving emails from other mail servers. No login possible.
465         ``smtps``       Sending emails for users of the mail server (TLS, login required).
587         ``submission``  Sending emails for users of the mail server (STARTTLS, login required).
==========  ==============  ===================================================================================

Users log in with their full email address and password. Postfix passes the login to Dovecot via the socket
``/var/spool/postfix/private/auth``, so mailboxes and send-only addresses can send emails.
