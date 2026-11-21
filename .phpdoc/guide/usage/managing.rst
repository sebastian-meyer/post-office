.. title:: Managing Accounts

Managing Accounts
#################

.. sidebar:: Table of Contents
  .. contents::

This page is meant for domain managers, list managers and regular users. Administrators can do everything described
here for all domains.

Your Own Account
================

Every user can change their name under "Account settings" and their password under "Change password". The account
settings also show the storage quota and all mailing lists you are a member of.

To leave a mailing list, click "Leave" next to it. Only a list manager can add you to the list again.

Accounts of a Domain
====================

Domain managers find their domains under "Manage". The domain page lists all accounts of the domain with their type and
status. From there you can

* create new accounts (see :doc:`first steps <first-steps>` for the account types),
* edit an account, e.g. to change the name or quota of a mailbox, or the destination of an alias,
* disable and enable an account by clicking its status,
* delete an account,
* write an email to accounts of the domain (see `Writing Emails`_).

To disable, enable or delete several accounts at once, mark them with the checkboxes in the first column and click
"Enable", "Disable" or "Delete" above the table. The checkbox in the table header marks all accounts matching the
current search. Accounts you aren't allowed to change (e.g. your own account or an administrator account) are skipped.

You set the password of a new mailbox or send-only address when you create it. Later, only administrators can set a new
password for it, since the account may also be a manager of other domains or mailing lists. Users change their own
password under "Change password".

Disabling an Account
--------------------

A disabled account can't log in to the mail server or the Post Office, and emails to it are rejected (or delivered to
the catch-all address, if there is one). All its settings, emails and manager roles are kept, so it can be enabled
again at any time.

Deleting an Account
-------------------

Deleting an account removes it from all mailing lists and revokes all its manager roles. Aliases which only forwarded to
the deleted account are deleted as well, because they would be useless otherwise. This includes addresses with an
extension, e.g. ``bob+news@example.com`` for ``bob@example.com``, since the mail server delivers them to the same
account.

.. caution::

  Deleting a mailbox doesn't delete the emails stored on the server. Ask your server administrator to remove them (see
  :doc:`maintenance <../setup/maintenance>`), especially before a new mailbox with the same address is created.

You can't delete your own account or an administrator account.

Mailing Lists
=============

List managers find their mailing lists under "Manage" in the section "Mailing lists I manage". Domain managers can
open all mailing lists of their domains from the domain page.

Members
-------

Under "Members" you can add and remove members by their email address. Members don't need an account in the Post
Office and may belong to any other mail server. Users can't join mailing lists by themselves, but they can leave them.

To remove several members at once, mark them with the checkboxes in the first column and click "Remove" above the
table. You can also write an email to the marked members, or to the whole list with "Write to the list" (see
`Writing Emails`_).

Settings
--------

"Replies go to the list"
  Adds a ``Reply-To`` header with the list address to every email, so replies are sent to all members instead of only
  the original sender.

"Members only"
  Only members of the list are allowed to send emails to it. Emails from other senders are rejected.

List Managers
-------------

Domain managers can appoint list managers under "List managers". List managers can change the settings and members of
their lists and delete them, but they can't appoint other list managers.

Writing Emails
==============

Administrators and domain managers can write emails to the mailboxes, aliases and mailing lists of their domains, list
managers to their mailing lists and the lists' members:

#. Mark the recipients with the checkboxes in the table of the domain page or in the members table of the list page.
   The checkbox in the table header marks all entries matching the current search.
#. Click "Write email" above the table (or "Write to the list" on the list page).
#. Check the recipients, enter subject and message, and click "Send".

The email is sent from your own email address with your own account, just like an email from your mail program. The
recipients don't see each other's addresses, and you get a copy of the email, because it isn't saved in your "Sent"
folder.

Addresses which can't receive emails are left out, and the email form lists them: disabled accounts, send-only
addresses, catch-all addresses, mailing lists without members, and "members only" lists you aren't a member of.

The Post Office keeps your password from the login (encrypted) until you log out, so it can send the email with your
account. If you logged in before this feature was installed, or the mail server rejects the password, the email form
asks for your password.

Catch-all Addresses
===================

An alias or mailing list without a username is a catch-all address. It receives all emails to addresses of its domain
which don't belong to any other enabled account. Each domain can have one catch-all address.

.. caution::

  Catch-all addresses tend to receive a lot of spam, because spammers often send emails to random addresses.
