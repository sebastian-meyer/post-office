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

namespace App\Trait;

use InvalidArgumentException;

/**
 * Magic getter for all class properties.
 */
trait Getter
{
    /**
     * Magic getter for all class properties.
     *
     * @param string $name The name of the property.
     *
     * @return mixed The property's value.
     *
     * @throws InvalidArgumentException if the property is not defined.
     */
    public function __get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            // The property is read in the scope of the object's actual class, so this also works for private
            // properties of subclasses when the trait is used in a common base class.
            return (fn (): mixed => $this->$name)->call($this);
        }

        throw new InvalidArgumentException(\sprintf('Undefined property: %s::$%s', get_debug_type($this), $name));
    }

    /**
     * Whether a property is defined (needed e.g. for property access in Twig templates).
     *
     * @param string $name The name of the property.
     */
    public function __isset(string $name): bool
    {
        return property_exists($this, $name);
    }
}
