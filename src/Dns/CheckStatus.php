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

namespace App\Dns;

/**
 * The outcome of a DNS check, ordered by severity.
 */
enum CheckStatus: int
{
    /**
     * The records are set up correctly.
     */
    case Ok = 0;

    /**
     * Optional records are missing, or the check could not be done.
     */
    case Info = 1;

    /**
     * The records work, but could be improved.
     */
    case Warning = 2;

    /**
     * The records are missing or wrong, which affects the delivery of emails.
     */
    case Error = 3;

    /**
     * Returns the more severe of the two statuses.
     *
     * @param CheckStatus $other The other status.
     */
    public function worst(self $other): self
    {
        return $other->value > $this->value ? $other : $this;
    }
}
