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
* edit an account, e.g. to change the name, password or quota of a mailbox, or the destination of an alias,
* disable and enable an account by clicking its status,
* delete an account.

Disabling an Account
--------------------

A disabled account can't log in to the mail server or the Post Office, and emails to it are rejected (or delivered to
the catch-all address, if there is one). All its settings, emails and manager roles are kept, so it can be enabled
again at any time.

Deleting an Account
-------------------

Deleting an account removes it from all mailing lists and revokes all its manager roles. Aliases which only forwarded to
the deleted account are deleted as well, because they would be useless otherwise.

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

Catch-all Addresses
===================

An alias or mailing list without a username is a catch-all address. It receives all emails to addresses of its domain
which don't belong to any other enabled account. Each domain can have one catch-all address.

.. caution::

  Catch-all addresses tend to receive a lot of spam, because spammers often send emails to random addresses.
