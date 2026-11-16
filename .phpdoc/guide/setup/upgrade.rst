.. title:: Upgrading

Upgrading
#########

.. sidebar:: Table of Contents
  .. contents::

Step by Step
============

#. Make a backup of your database before upgrading.

#. Update the application and its dependencies.

   .. code-block:: shell

     # Update to the latest version allowed by your composer.json
     composer update --no-dev

#. Apply all changes to the database schema that came with the new version.

   .. code-block:: shell

     # Show which migrations are going to be executed
     php bin/console doctrine:migrations:status

     # Execute them
     php bin/console doctrine:migrations:migrate

   .. caution::

     MariaDB can't roll back changes to the database schema, so a failed migration may leave the database in an
     intermediate state. In this case restore the backup, fix the cause and run the migrations again.
