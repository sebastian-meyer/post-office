.. title:: First Steps

First Steps
###########

.. sidebar:: Table of Contents
  .. contents::

After the :doc:`installation <../setup/installation>` the database is empty, so nobody can log in yet. Only
administrators can create domains in the web interface, but administrators need an account, and an account needs a
domain. To solve this chicken-and-egg problem, the first domain and the first administrator account are created with
console commands.

Creating the First Administrator
================================

#. Add the first domain. Replace ``example.com`` with your own domain.

   .. code-block:: shell

     php bin/console app:domain:add example.com

#. Add a mailbox for the administrator. The command asks for the password and prints the ID of the new account.

   .. code-block:: shell

     php bin/console app:account:add admin@example.com --name="Jane Doe" --quota=1024

   .. code-block:: text

      Password:
      >
      Password (repeat):
      >

      [OK] The mailbox admin@example.com has been created with ID 1.

      To make it an administrator, add 1 to ADMIN_IDS in your .env.local file.

   .. note::

     The password has to be strong, and it must not appear in any known data breach. To check the latter, the command
     sends the first characters of the password's SHA-1 hash to `Have I Been Pwned <https://haveibeenpwned.com/>`_,
     so the server needs access to the internet.

#. Add the ID of the new account to ``ADMIN_IDS`` in your ``.env.local`` file:

   .. code-block:: shell

     ADMIN_IDS=1

   If you compiled your configuration with ``composer dump-env prod``, run that command again.

#. Log in to the Post Office with ``admin@example.com`` and the password you chose. Besides "Manage", the homepage
   now offers the sections "TLS policies" and "Blacklist", which are only available to administrators.

.. hint::

  To appoint more administrators later, create their mailboxes and add their IDs to ``ADMIN_IDS``, e.g.
  ``ADMIN_IDS=1,5``. The ID is printed by ``app:account:add``. For accounts created in the web interface, you can find
  it in the address of their edit page, e.g. ``/manage/account/5``.

Console Commands
================

``php bin/console app:domain:add DOMAIN``
  Adds a domain, e.g. ``example.com``.

``php bin/console app:account:add EMAIL [--name=NAME] [--quota=MB | --send-only | --alias=DESTINATION]``
  Adds a mailbox with the given email address, whose domain must exist already. Passwords are always asked for
  interactively, so they don't end up in your shell history. The ID of the new account is printed.

  ``--name``
    The name of the account's owner.
  ``--quota``
    The storage quota in megabytes (0 means unlimited). Defaults to 100.
  ``--send-only``
    Creates a send-only address instead of a mailbox. Send-only addresses have no storage quota.
  ``--alias``
    Creates an alias forwarding all emails to the given destination address instead of a mailbox. Aliases have no
    password and no storage quota. Use ``@example.com`` as email address to create a catch-all alias for the domain.

  For example:

  .. code-block:: shell

    # Forward all emails to info@example.com to jane@example.com
    php bin/console app:account:add info@example.com --alias=jane@example.com

    # Forward all emails to unknown addresses of example.com to jane@example.com
    php bin/console app:account:add @example.com --alias=jane@example.com

Both commands apply the same checks as the web interface. Mailing lists can only be created in the web interface.

Adding Domains
==============

The domain of your administrator account already exists. To add more domains:

#. Go to "Manage" and click "New domain".
#. Enter the domain name, e.g. ``example.org``, and save.

Alternatively, use the console command ``php bin/console app:domain:add example.org``.

Make sure the MX record of each new domain points to your mail server (see
:doc:`preparation <../mailserver/preparation>`).

Adding Accounts
===============

#. Go to "Manage" and open the domain.
#. Click the button for the type of account you want to create:

   "New mailbox"
     A regular email account with storage. Enter the username (the part before the ``@``), the name of its owner,
     a password, and the storage quota in megabytes (0 means unlimited).

   "New alias"
     Forwards all emails to one destination address, which may be on another mail server. Leave the username empty
     to create a catch-all address for the domain.

   "New mailing list"
     Forwards all emails to its members. Members are added after the list has been created.

   "New send-only address"
     Can only send emails, e.g. for notifications of a web application. Requires a password.

#. Save the new account. Mailboxes can be used right away, there is no need to reload the mail server.

Tell the owner of a new mailbox their email address and password, and ask them to change the password in the Post Office
under "Change password".

Appointing Managers
===================

You don't have to manage everything yourself. Instead, you can delegate the management of domains and mailing lists to
other users.

To appoint a **domain manager**:

#. Go to "Manage" and open the domain.
#. Under "Domain managers" enter the email address of the new manager and click "Add manager".

To appoint a **list manager**:

#. Go to "Manage", open the domain and then the mailing list, or open the mailing list under "Mailing lists I
   manage".
#. Under "List managers" enter the email address of the new manager and click "Add manager".

Managers have to be enabled mailboxes, but may belong to any domain. The new manager finds the domain or mailing list
under "Manage" the next time they open the Post Office.

Next Steps
==========

* :doc:`Test your mail server <../mailserver/testing>`, if you haven't done that already.
* Learn more about :doc:`managing accounts and mailing lists <managing>`.
* Set up :doc:`TLS policies and the blacklist <administration>`, if needed.
