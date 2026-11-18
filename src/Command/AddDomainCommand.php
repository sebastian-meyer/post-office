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

namespace App\Command;

use App\Entity\Domain;
use App\Form\Model\DomainData;
use App\Repository\DomainRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Adds a domain, e.g. the first one after the installation (when nobody can log in yet).
 */
#[AsCommand(name: 'app:domain:add', description: 'Adds a domain.')]
final class AddDomainCommand
{
    /**
     * Executes the command.
     *
     * @param SymfonyStyle $io The console input/output helper.
     * @param string $domain The domain name.
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The domain name, e.g. example.com.')] string $domain
    ): int {
        $data = new DomainData();
        $data->domain = strtolower(trim($domain));

        $violations = $this->validator->validate($data);

        if ($violations->count() > 0) {
            foreach ($violations as $violation) {
                $io->error(\sprintf('%s: %s', $data->domain, (string) $violation->getMessage()));
            }

            return Command::FAILURE;
        }

        $this->domainRepository->save(new Domain($data->domain));

        $io->success(\sprintf('The domain %s has been created.', $data->domain));

        return Command::SUCCESS;
    }

    /**
     * @param DomainRepository $domainRepository The repository for domains.
     * @param ValidatorInterface $validator The validator.
     */
    public function __construct(
        private readonly DomainRepository $domainRepository,
        private readonly ValidatorInterface $validator
    ) {
    }
}
