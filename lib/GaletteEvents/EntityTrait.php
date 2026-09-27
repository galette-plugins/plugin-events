<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents;

use Analog\Analog;
use ArrayObject;

/**
 * Loading, storage and removal shared by events, bookings and activities
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait EntityTrait
{
    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, mixed> $r the resultset row
     */
    abstract private function loadFromRS(ArrayObject $r): void;

    /**
     * Load entity from its id
     *
     * @param int $id Identifier
     *
     * @throws NotFoundException
     */
    public function load(int $id): void
    {
        $select = $this->zdb->select($this->getTableName());
        $select->where([self::PK => $id]);
        $results = $this->zdb->execute($select);

        if ($results->count() === 0) {
            throw new NotFoundException(sprintf('%1$s #%2$s does not exist', self::class, $id));
        }
        $this->loadFromRS($results->current());
    }

    /**
     * Remove entity; database removes its links
     */
    public function remove(): void
    {
        $delete = $this->zdb->delete($this->getTableName());
        $delete->where([self::PK => $this->id]);
        $this->zdb->execute($delete);
    }

    /**
     * Run storage in a transaction, unless one is already running
     *
     * @param callable $store Storage
     */
    private function transactional(callable $store): void
    {
        $new = $this->id === null;
        $transaction = !$this->zdb->connection->inTransaction();
        if ($transaction) {
            $this->zdb->connection->beginTransaction();
        }

        try {
            $store();
            if ($transaction) {
                $this->zdb->connection->commit();
            }
        } catch (\Throwable $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            if ($new) {
                //nothing has been stored
                $this->id = null;
            }
            throw $e;
        }
    }

    /**
     * Get identifier of the row that has just been inserted
     */
    private function getLastInsertId(): int
    {
        if ($this->zdb->isPostgres()) {
            /** @phpstan-ignore-next-line */
            return (int)$this->zdb->driver->getLastGeneratedValue(
                PREFIX_DB . $this->getTableName() . '_id_seq'
            );
        }
        return (int)$this->zdb->driver->getLastGeneratedValue();
    }

    /**
     * Parse a date typed in the localized format, or as Y-m-d
     *
     * @param string $value Typed date
     * @param string $label Field label, for the error message
     *
     * @return ?string Date as Y-m-d, null when it cannot be parsed
     */
    private function parseDate(string $value, string $label): ?string
    {
        $date = \DateTime::createFromFormat(__('Y-m-d'), $value)
            ?: \DateTime::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            Analog::log(
                'Wrong date format. field: ' . $label . ', value: ' . $value
                . ', expected fmt: ' . __('Y-m-d'),
                Analog::INFO
            );
            $this->errors[] = sprintf(
                //TRANS: %1$s is the expected date format, %2$s is the field label
                _T('- Wrong date format (%1$s) for %2$s!'),
                __('Y-m-d'),
                $label
            );
            return null;
        }
        return $date->format('Y-m-d');
    }

    /**
     * Get table's name
     */
    protected function getTableName(): string
    {
        return EVENTS_PREFIX . self::TABLE;
    }
}
