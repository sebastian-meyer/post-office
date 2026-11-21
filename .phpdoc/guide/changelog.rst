.. title:: Changelog

Changelog
#########

.. sidebar:: Table of Contents
  .. contents::

All available versions as well as further information about :doc:`requirements and dependencies <setup/requirements>`
can be found in the `Packagist repository <https://packagist.org/packages/sebastian-meyer/post-office>`_ and on the
`GitHub releases page <https://github.com/sebastian-meyer/post-office/releases>`_.

v1.0.0
======

**Initial Release**

v1.1.0
======

**New Features**

* Added a feature for managers to send emails to recipients of their managed domains and mailing lists.
* Added a feature for managers to perform bulk actions on multiple accounts, aliases and mailing lists.
* Added default TLS policies for common email providers.
* Added DNS health checks for mail domains (only for administrators).
* Revoked the ability for managers to set new passwords for accounts. This is now only possible for administrators.

**Upgrading**

There are no breaking changes in this release and all normal :doc:`upgrade procedures <setup/upgrade>` apply. However,
some of the new features require :doc:`additional configuration <setup/configuration>` in order to function properly.
