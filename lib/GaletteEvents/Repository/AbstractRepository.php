<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Repository;

use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Pagination;
use Galette\Core\Preferences;
use Galette\Repository\Repository;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;

/**
 * Common code for lists of events, bookings and activities
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class AbstractRepository extends Repository
{
    /** Primary key of listed entities */
    protected const string PK = '';
    /** Table alias used in queries */
    protected const string ALIAS = '';

    private int $count = 0;

    /**
     * Constructor
     *
     * @param Db          $zdb         Database instance
     * @param Login       $login       Login instance
     * @param History     $history     History instance, for listed entities
     * @param Preferences $preferences Preferences instance
     * @param string      $entity      Entity class name, relative to the plugin namespace
     * @param Pagination  $filters     Filtering
     */
    public function __construct(
        Db $zdb,
        Login $login,
        protected History $history,
        Preferences $preferences,
        string $entity,
        Pagination $filters
    ) {
        parent::__construct($zdb, $preferences, $login, $entity, 'GaletteEvents', EVENTS_PREFIX);
        $this->filters = $filters;
    }

    /**
     * Count rows matching the query
     *
     * Counting on a subquery keeps grouping right.
     *
     * @param Select $select Original select
     */
    protected function proceedCount(Select $select): void
    {
        $counted = clone $select;
        $counted->reset(Select::COLUMNS);
        $counted->reset(Select::ORDER);
        $counted->reset(Select::JOINS);
        $counted->columns(['id' => new Expression(static::ALIAS . '.' . static::PK)]);
        foreach ($select->joins as $join) {
            $counted->join($join['name'], $join['on'], [], $join['type']);
        }

        $count_select = new Select(['counted' => $counted]);
        $count_select->columns(['count' => new Expression('COUNT(DISTINCT id)')]);

        $this->count = (int)$this->zdb->execute($count_select)->current()['count'];
        $this->filters->setCounter($this->count);
    }

    /**
     * Get count for current query
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Nothing to initialize
     *
     * @param bool $check_first Check first if it seems initialized
     */
    public function installInit(bool $check_first = true): bool
    {
        return true;
    }
}
