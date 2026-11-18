.. title:: Usage

Usage
#####

.. sidebar:: Table of Contents
  .. contents::

Here you will find out how to set up the Post Office after the installation and how to use it.

.. toctree::
  :maxdepth: 2

  first-steps
  managing
  administration

Logging In
==========

Every enabled mailbox or send-only address can log in to the Post Office with its full email address and the password
of its email account. Aliases and mailing lists have no password and can't log in.

After five failed login attempts, further attempts are blocked for 15 minutes.

Roles
=====

What a user can do in the Post Office depends on their role. Every user has at least the role of a regular user, and
can additionally be manager of any number of mailing lists and domains.

**Users** can

* change their name and password,
* see the mailing lists they are a member of, and leave them.

**List managers** can additionally

* change the settings of their mailing lists,
* add and remove members of their mailing lists,
* delete their mailing lists.

**Domain managers** can additionally

* create, edit, disable and delete all accounts and mailing lists of their domains,
* appoint and remove list managers for the mailing lists of their domains.

**Administrators** can additionally

* manage all domains, accounts and mailing lists,
* create and delete domains,
* appoint and remove domain managers,
* manage the TLS policies and the blacklist of the mail server.

Only enabled mailboxes can be list or domain managers, but they can be mailboxes of any domain. When a manager's
account is disabled, their manager roles are suspended until the account is enabled again. When it is deleted, all its
manager roles are revoked.

Administrators are appointed in the :doc:`configuration <../setup/configuration>` of the Post Office (see
:doc:`first steps <first-steps>`). They can't be deleted, and only other administrators can edit them.
