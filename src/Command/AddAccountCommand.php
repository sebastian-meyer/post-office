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

use App\Entity\Account;
use App\Form\Model\AccountData;
use App\Form\Model\SendOnlyData;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Adds a mailbox, send-only address or alias, e.g. the first administrator account after the installation.
 *
 * Passwords are always asked for interactively, so they don't end up in the shell history. The ID of the new account
 * is printed, so it can be added to ADMIN_IDS.
 */
#[AsCommand(name: 'app:account:add', description: 'Adds a mailbox (or send-only address or alias) and prints its ID.')]
final class AddAccountCommand
{
    /**
     * The validation groups for new accounts.
     */
    private const array GROUPS = ['Default', 'create'];

    /**
     * The names of the command's arguments and options for the properties of the account data (for error messages).
     */
    private const array NAMES = [
        'username' => 'email',
        'label' => 'name',
        'plainPassword' => 'password',
        'destination' => 'alias'
    ];

    /**
     * Executes the command.
     *
     * @param SymfonyStyle $io The console input/output helper.
     * @param InputInterface $input The console input.
     * @param string $email The email address of the new account.
     * @param string $name The name of the account's owner.
     * @param ?string $quota The storage quota in megabytes.
     * @param bool $sendOnly Whether to create a send-only address instead of a mailbox.
     * @param ?string $alias The destination address, to create an alias instead of a mailbox.
     */
    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument(description: 'The email address, e.g. admin@example.com, or @example.com for a catch-all alias. Its domain must exist already.')] string $email,
        #[Option(description: 'The name of the account\'s owner.')] string $name = '',
        #[Option(description: 'The storage quota in megabytes (0 means unlimited). Defaults to 100.')] ?string $quota = null,
        #[Option(description: 'Create a send-only address instead of a mailbox.')] bool $sendOnly = false,
        #[Option(description: 'Create an alias forwarding to this address instead of a mailbox.')] ?string $alias = null
    ): int {
        try {
            $data = $this->accountFactory->createData($email, $name, $quota, $sendOnly, $alias);

            // Check everything else first, so nobody has to enter a password for an invalid account.
            $this->validate($data, null);

            if ($data instanceof SendOnlyData) {
                $data->plainPassword = $this->askPassword($io, $input);
                $this->validate($data, 'plainPassword');
            }
        } catch (InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->report($io, $this->accountFactory->save($data));

        return Command::SUCCESS;
    }

    /**
     * Asks for the password twice.
     *
     * @param SymfonyStyle $io The console input/output helper.
     * @param InputInterface $input The console input.
     *
     * @throws InvalidArgumentException if the command runs non-interactively, or the two entries don't match.
     */
    private function askPassword(SymfonyStyle $io, InputInterface $input): string
    {
        if (!$input->isInteractive()) {
            throw new InvalidArgumentException('The password can only be entered interactively. Run the command without --no-interaction.');
        }

        $password = $io->askHidden('Password', static function (mixed $value): string {
            if (!\is_string($value) || $value === '') {
                throw new InvalidArgumentException('The password must not be empty.');
            }

            return $value;
        });

        if (!\is_string($password) || $password !== $io->askHidden('Password (repeat)')) {
            throw new InvalidArgumentException('The passwords do not match.');
        }

        return $password;
    }

    /**
     * Reports the new account and its ID.
     *
     * @param SymfonyStyle $io The console input/output helper.
     * @param Account $account The new account.
     */
    private function report(SymfonyStyle $io, Account $account): void
    {
        $io->success(\sprintf(
            'The %s %s has been created with ID %d.',
            match (true) {
                $account->isCatchAll() => 'catch-all alias',
                $account->isAlias() => 'alias',
                $account->isSendOnly() => 'send-only address',
                default => 'mailbox'
            },
            (string) $account,
            $account->id
        ));

        if (!$account->isAlias()) {
            $io->text(\sprintf('To make it an administrator, add %d to ADMIN_IDS in your .env.local file.', $account->id));
        }
    }

    /**
     * Validates the account data.
     *
     * @param AccountData $data The account data.
     * @param ?string $property Only validate this property, or NULL to validate all properties except the password.
     *
     * @throws InvalidArgumentException with all violations, if the data is invalid.
     */
    private function validate(AccountData $data, ?string $property): void
    {
        $violations = $property !== null
            ? $this->validator->validateProperty($data, $property, self::GROUPS)
            : $this->validator->validate($data, null, self::GROUPS);
        $messages = [];

        foreach ($violations as $violation) {
            if ($property === null && $violation->getPropertyPath() === 'plainPassword') {
                continue;
            }

            $path = $violation->getPropertyPath();
            $messages[] = \sprintf('%s: %s', self::NAMES[$path] ?? $path, (string) $violation->getMessage());
        }

        if ($messages !== []) {
            throw new InvalidArgumentException(implode(PHP_EOL, $messages));
        }
    }

    /**
     * @param AccountFactory $accountFactory The factory for accounts from the command's arguments.
     * @param ValidatorInterface $validator The validator.
     */
    public function __construct(
        private readonly AccountFactory $accountFactory,
        private readonly ValidatorInterface $validator
    ) {
    }
}
