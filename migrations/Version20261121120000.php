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

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds default TLS policies for large email providers which support DANE or valid certificates.
 */
final class Version20261121120000 extends AbstractMigration
{
    /**
     * The default TLS policies (domain => [policy, params]).
     */
    private const array POLICIES = [
        'bund.de' => ['dane-only', ''],
        'email.de' => ['secure', 'match=.web.de'],
        'freenet.de' => ['dane-only', ''],
        'gmail.com' => ['secure', 'match=.google.com'],
        'gmx.at' => ['dane-only', ''],
        'gmx.ch' => ['dane-only', ''],
        'gmx.de' => ['dane-only', ''],
        'gmx.net' => ['dane-only', ''],
        'googlemail.com' => ['secure', 'match=.google.com'],
        'mail.de' => ['dane-only', ''],
        'mailbox.org' => ['dane-only', ''],
        'outlook.com' => ['secure', 'match=.outlook.com'],
        'posteo.de' => ['dane-only', ''],
        't-online.de' => ['secure', 'match=.t-online.de'],
        'unitymedia.de' => ['secure', 'match=.unitymedia.de'],
        'web.de' => ['dane-only', ''],
        'yahoo.com' => ['secure', 'match=.yahoodns.net'],
        'yahoo.de' => ['secure', 'match=.yahoodns.net']
    ];

    public function getDescription(): string
    {
        return 'Add default TLS policies for large email providers';
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

        // Existing policies for these domains (e.g. from an existing mail server setup) are kept.
        foreach (self::POLICIES as $domain => [$policy, $params]) {
            $this->addSql(
                'INSERT IGNORE INTO tlspolicies (domain, policy, params, enabled) VALUES (?, ?, ?, 1)',
                [$domain, $policy, $params],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Only remove policies which haven't been changed since.
        foreach (self::POLICIES as $domain => [$policy, $params]) {
            $this->addSql(
                'DELETE FROM tlspolicies WHERE domain = ? AND policy = ? AND params = ?',
                [$domain, $policy, $params],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]
            );
        }
    }
}
