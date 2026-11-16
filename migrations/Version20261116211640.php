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
 * Creates the initial database schema.
 */
final class Version20261116211640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial database schema';
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

        $this->addSql('CREATE TABLE accounts (label VARCHAR(255) NOT NULL, username VARCHAR(64) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, type VARCHAR(16) DEFAULT \'mailbox\' NOT NULL, quota INT UNSIGNED DEFAULT 100, replytolist TINYINT DEFAULT 0 NOT NULL, strictlist TINYINT DEFAULT 0 NOT NULL, id INT UNSIGNED AUTO_INCREMENT NOT NULL, enabled TINYINT DEFAULT 1 NOT NULL, domain VARCHAR(180) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (username, domain), INDEX IDX_CAC89EACA7A91E0B (domain), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE account_destinations (account INT UNSIGNED NOT NULL, destination VARCHAR(254) NOT NULL, INDEX IDX_B4EB55857D3656A4 (account), INDEX IDX_B4EB55853EC63EAA (destination), PRIMARY KEY (account, destination)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE account_managers (account INT UNSIGNED NOT NULL, manager INT UNSIGNED NOT NULL, INDEX IDX_D18702B07D3656A4 (account), INDEX IDX_D18702B0FA2425B9 (manager), PRIMARY KEY (account, manager)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blacklist (username VARCHAR(64) DEFAULT NULL, domain VARCHAR(180) NOT NULL, action VARCHAR(255) DEFAULT \'REJECT\' NOT NULL, id INT UNSIGNED AUTO_INCREMENT NOT NULL, enabled TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (username, domain), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE destinations (destination VARCHAR(254) NOT NULL, PRIMARY KEY (destination)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE domains (domain VARCHAR(180) NOT NULL, PRIMARY KEY (domain)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE domain_managers (domain VARCHAR(180) NOT NULL, account INT UNSIGNED NOT NULL, INDEX IDX_BAC7AF49A7A91E0B (domain), INDEX IDX_BAC7AF497D3656A4 (account), PRIMARY KEY (domain, account)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tlspolicies (domain VARCHAR(180) NOT NULL, policy VARCHAR(255) DEFAULT \'none\' NOT NULL, params VARCHAR(255) DEFAULT \'\' NOT NULL, enabled TINYINT DEFAULT 1 NOT NULL, PRIMARY KEY (domain)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE accounts ADD CONSTRAINT FK_CAC89EACA7A91E0B FOREIGN KEY (domain) REFERENCES domains (domain)');
        $this->addSql('ALTER TABLE account_destinations ADD CONSTRAINT FK_B4EB55857D3656A4 FOREIGN KEY (account) REFERENCES accounts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE account_destinations ADD CONSTRAINT FK_B4EB55853EC63EAA FOREIGN KEY (destination) REFERENCES destinations (destination) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE account_managers ADD CONSTRAINT FK_D18702B07D3656A4 FOREIGN KEY (account) REFERENCES accounts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE account_managers ADD CONSTRAINT FK_D18702B0FA2425B9 FOREIGN KEY (manager) REFERENCES accounts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE domain_managers ADD CONSTRAINT FK_BAC7AF49A7A91E0B FOREIGN KEY (domain) REFERENCES domains (domain) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE domain_managers ADD CONSTRAINT FK_BAC7AF497D3656A4 FOREIGN KEY (account) REFERENCES accounts (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on MariaDB or MySQL.'
        );

        $this->addSql('ALTER TABLE accounts DROP FOREIGN KEY FK_CAC89EACA7A91E0B');
        $this->addSql('ALTER TABLE account_destinations DROP FOREIGN KEY FK_B4EB55857D3656A4');
        $this->addSql('ALTER TABLE account_destinations DROP FOREIGN KEY FK_B4EB55853EC63EAA');
        $this->addSql('ALTER TABLE account_managers DROP FOREIGN KEY FK_D18702B07D3656A4');
        $this->addSql('ALTER TABLE account_managers DROP FOREIGN KEY FK_D18702B0FA2425B9');
        $this->addSql('ALTER TABLE domain_managers DROP FOREIGN KEY FK_BAC7AF49A7A91E0B');
        $this->addSql('ALTER TABLE domain_managers DROP FOREIGN KEY FK_BAC7AF497D3656A4');
        $this->addSql('DROP TABLE accounts');
        $this->addSql('DROP TABLE account_destinations');
        $this->addSql('DROP TABLE account_managers');
        $this->addSql('DROP TABLE blacklist');
        $this->addSql('DROP TABLE destinations');
        $this->addSql('DROP TABLE domains');
        $this->addSql('DROP TABLE domain_managers');
        $this->addSql('DROP TABLE tlspolicies');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
