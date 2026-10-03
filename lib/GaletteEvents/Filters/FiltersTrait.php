<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Filters;

use Analog\Analog;

/**
 * Access to the filtering properties of a list, and to its pagination
 *
 * Classes using it extend Galette\Core\Pagination.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait FiltersTrait
{
    /**
     * Names of the filtering properties that can be read
     *
     * @return array<string>
     */
    abstract protected function getFilterNames(): array;

    /**
     * Set a filtering property
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     *
     * @return bool false if the property cannot be set
     */
    abstract protected function setFilter(string $name, mixed $value): bool;

    /**
     * Get a filtering property
     *
     * @param string $name Property name
     */
    protected function getFilter(string $name): mixed
    {
        return $this->$name;
    }

    /**
     * Global isset method
     *
     * @param string $name Property name
     */
    public function __isset(string $name): bool
    {
        return in_array($name, $this->pagination_fields) || in_array($name, $this->getFilterNames());
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        }
        if (in_array($name, $this->getFilterNames())) {
            return $this->getFilter($name);
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                static::class,
                $name
            )
        );
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     */
    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, $this->pagination_fields)) {
            parent::__set($name, $value);
            return;
        }

        if (!$this->setFilter($name, $value)) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to set property "%s::%s"!',
                    static::class,
                    $name
                )
            );
        }
    }

    /**
     * Get an identifier from a value, null for none or all
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     *
     * @return ?int Identifier, null for none; false if the value is not valid
     */
    protected function toId(string $name, mixed $value): int|false|null
    {
        if ($value === null || $value === '' || $value === 'all' || $value === 0 || $value === '0') {
            return null;
        }
        if (is_numeric($value) && (int)$value > 0) {
            return (int)$value;
        }
        $this->logInvalid($name, $value);
        return false;
    }

    /**
     * Check a value is one of the allowed choices
     *
     * @param string     $name    Property name
     * @param mixed      $value   Value
     * @param array<int> $choices Allowed values
     *
     * @return ?int Value, null if it is not allowed
     */
    protected function toChoice(string $name, mixed $value, array $choices): ?int
    {
        if (is_numeric($value) && in_array((int)$value, $choices, true)) {
            return (int)$value;
        }
        $this->logInvalid($name, $value);
        return null;
    }

    /**
     * Log an invalid value, that is ignored
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     */
    private function logInvalid(string $name, mixed $value): void
    {
        Analog::log(
            sprintf(
                '[%1$s] Invalid value for %2$s: %3$s',
                static::class,
                $name,
                var_export($value, true)
            ),
            Analog::WARNING
        );
    }
}
