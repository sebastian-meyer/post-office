<?php

/**
 * Post Office
 * Copyright (C) 2026 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fixes data which the mail server can't handle.
 *
 * - The default TLS policy for unitymedia.de is changed from "dane-only" to "secure", since its mail exchanger has
 *   no TLSA record, so Postfix would never deliver any email there. It has a valid certificate, though.
 * - Usernames and domains are converted to lower case. Dovecot looks up addresses in lower case, but uses the stored
 *   spelling for the mailbox path, so mixed-case accounts got their emails delivered to a different directory than
 *   the one they read with IMAP.
 */
final class Version20261121150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix the TLS policy for unitymedia.de and convert usernames and domains to lower case';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on MariaDB or MySQL.'
        );

        // Only if the default hasn't been changed since.
        $this->addSql("UPDATE tlspolicies SET policy = 'secure', params = 'match=.unitymedia.de' WHERE domain = 'unitymedia.de' AND policy = 'dane-only' AND params = ''");

        // The comparisons are case-insensitive (collation), so compare the binary values.
        $this->addSql('UPDATE accounts SET username = LOWER(username) WHERE CAST(username AS BINARY) <> CAST(LOWER(username) AS BINARY)');

        // The domain is referenced by foreign keys without ON UPDATE CASCADE, so all tables are updated together.
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['domains', 'accounts', 'domain_managers'] as $table) {
            $this->addSql("UPDATE {$table} SET domain = LOWER(domain) WHERE CAST(domain AS BINARY) <> CAST(LOWER(domain) AS BINARY)");
        }
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        // Nothing to do: the original spelling of usernames and domains is not known anymore, and the old TLS policy
        // would only break the delivery of emails again.
    }
}
