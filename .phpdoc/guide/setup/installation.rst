.. title:: Installation

Installation
############

.. sidebar:: Table of Contents
  .. contents::

The intended and recommended way of installing this application is via `Composer <https://getcomposer.org/>`_. First,
make sure all :doc:`requirements <requirements>` are met before trying to install the application.

.. caution::

  All commands should be run as your unprivileged Apache/Nginx system user or the PHP-FPM/PHP-CGI user (depending on
  your environment)! This is important in order to avoid later issues due to insufficient access permissions.

  Commands intended to be run as `root` are always prepended with `sudo` throughout this documentation.

Step by Step
============

#. Create the database and two database users: one for the Post Office, which needs full access to create and update
   the database tables, and one for Postfix and Dovecot, which only need to read them.

   .. code-block:: shell

     sudo mariadb

   .. code-block:: sql

     CREATE DATABASE vmail CHARACTER SET utf8mb4;

     -- The Post Office
     CREATE USER 'postoffice'@'127.0.0.1' IDENTIFIED BY 'a-secure-password';
     GRANT ALL PRIVILEGES ON vmail.* TO 'postoffice'@'127.0.0.1';

     -- Postfix and Dovecot
     CREATE USER 'vmail'@'127.0.0.1' IDENTIFIED BY 'another-secure-password';
     GRANT SELECT ON vmail.* TO 'vmail'@'127.0.0.1';

   .. note::

     Postfix and Dovecot look up email addresses case-insensitively, which relies on the case-insensitive default
     collation of the database. Don't change the collation of the database or its tables to a case-sensitive one.

#. Install the application. The following command will get you the latest stable version and all its dependencies.

   .. code-block:: shell

     # Install the latest stable version suitable for your environment
     composer create-project sebastian-meyer/post-office --ask --no-dev

   .. hint::

     If you want to use a version other than the latest available for your environment you can do so by appending the
     desired `version constraint <https://getcomposer.org/doc/articles/versions.md#writing-version-constraints>`_.

     .. code-block:: shell

       # Install the latest patch level version of 1.0 (i. e. >=1.0.0 & <1.1.0)
       composer create-project sebastian-meyer/post-office "1.0.*" --ask --no-dev

     Available versions can be found on `Packagist <https://packagist.org/packages/sebastian-meyer/post-office>`_.

#. Create a file ``.env.local`` in the application's root directory with at least the following settings. See
   :doc:`configuration <configuration>` for details.

   .. code-block:: shell

     APP_SECRET=a-random-string-of-32-characters
     DATABASE_URL="mysql://postoffice:a-secure-password@127.0.0.1:3306/vmail?serverVersion=11.8.0-MariaDB&charset=utf8mb4"
     DEFAULT_URI=https://postoffice.example.com/

   .. hint::

     You can generate a random secret with ``php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"``.

#. Create the database tables.

   .. code-block:: shell

     # Create all database tables
     php bin/console doctrine:migrations:migrate

#. Compile the frontend assets and warm up the cache.

   .. code-block:: shell

     # Write all CSS and JavaScript files to ./public/assets/
     php bin/console asset-map:compile

     # Build the cache for the production environment
     php bin/console cache:clear

#. Configure your web server to use the ``./public/`` directory as document root and ``index.php`` as default entry
   point. A minimal Nginx configuration looks like this:

   .. code-block:: nginx

     server {
         listen 443 ssl;
         listen [::]:443 ssl;
         server_name postoffice.example.com;

         ssl_certificate /etc/letsencrypt/live/postoffice.example.com/fullchain.pem;
         ssl_certificate_key /etc/letsencrypt/live/postoffice.example.com/privkey.pem;

         root /var/www/post-office/public;

         location / {
             try_files $uri /index.php$is_args$args;
         }

         location ~ ^/index\.php(/|$) {
             fastcgi_pass unix:/run/php/php8.4-fpm.sock;
             fastcgi_split_path_info ^(.+\.php)(/.*)$;
             include fastcgi_params;
             fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
             fastcgi_param DOCUMENT_ROOT $realpath_root;
             internal;
         }

         location ~ \.php$ {
             return 404;
         }
     }

   For Apache and other web servers see the
   `Symfony documentation <https://symfony.com/doc/current/setup/web_server_configuration.html>`_.

   .. admonition:: Check

     Visit ``https://postoffice.example.com/`` to verify everything is working as expected. You should see the login
     page.

#. Congratulations, the Post Office is up and running!

What's Next?
============

#. Configure :doc:`Postfix and Dovecot <../mailserver/index>` to use the database of the Post Office, if you haven't
   done that already.

#. Follow the :doc:`first steps <../usage/first-steps>` to create the first domain and appoint an administrator. Until
   then nobody can log in.

#. Take a few steps to ease :doc:`maintenance <maintenance>`.
