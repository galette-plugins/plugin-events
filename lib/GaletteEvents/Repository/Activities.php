<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Repository;

use Analog\Analog;
use Galette\Repository\Repository;
use GaletteEvents\Activity;
use Galette\Core\Preferences;
use GaletteEvents\Filters\ActivitiesList;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Expression;
use Galette\Core\Login;
use Galette\Core\Db;
use Laminas\Db\Sql\Select;

/**
 * Events
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activities extends Repository
{
    private int $count;

    public const int ORDERBY_DATE = 0;
    public const int ORDERBY_NAME = 1;

    /**
     * Constructor
     *
     * @param Db              $zdb         Database instance
     * @param Login           $login       Login instance
     * @param Preferences     $preferences Preferences instance
     * @param ?ActivitiesList $filters     Filtering
     */
    public function __construct(Db $zdb, Login $login, Preferences $preferences, ?ActivitiesList $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

        parent::__construct($zdb, $preferences, $login, 'Activity', 'GaletteEvents', EVENTS_PREFIX);

        if ($filters === null) {
            $this->filters = new ActivitiesList();
        } else {
            $this->filters = $filters;
        }
    }

    /**
     * Get activities list
     *
     * @return array<int, Activity>|ResultSet
     */
    public function getList(): array|ResultSet
    {
        try {
            $select = $this->zdb->select(EVENTS_PREFIX . Activity::TABLE, 'ac');
            $select->order($this->buildOrderClause());

            $this->proceedCount($select);

            $this->filters->setLimits($select);
            $results = $this->zdb->execute($select);
            //@phpstan-ignore-next-line
            $this->filters->query = $this->zdb->query_string;

            $activities = [];
            foreach ($results as $row) {
                $activity = new Activity($this->zdb, $this->login, $row);
                $activities[] = $activity;
            }

            return $activities;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list activities | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the order clause
     *
     * @param ?array<string> $fields Fields list to ensure ORDER clause
     *                               references selected fields. Optional.
     *
     * @return array<string> SQL ORDER clauses
     */
    private function buildOrderClause(?array $fields = null): array
    {
        $order = [];

        switch ($this->filters->orderby) {
            case self::ORDERBY_DATE:
                if ($this->canOrderBy('creation_date', $fields)) {
                    $order[] = 'creation_date ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_NAME:
                if ($this->canOrderBy('name', $fields)) {
                    $order[] = 'name ' . $this->filters->getDirection();
                }
                break;
        }

        return $order;
    }

    /**
     * Count events from the query
     *
     * @param Select $select Original select
     */
    private function proceedCount(Select $select): void
    {
        try {
            $countSelect = clone $select;
            $countSelect->reset($countSelect::COLUMNS);
            $countSelect->reset($countSelect::ORDER);
            $countSelect->reset($countSelect::HAVING);
            $joins = $countSelect->joins;
            $countSelect->reset($countSelect::JOINS);
            foreach ($joins as $join) {
                $countSelect->join(
                    $join['name'],
                    $join['on'],
                    [],
                    $join['type']
                );
                unset($join['columns']);
            }

            $countSelect->columns(
                [
                    'count' => new Expression('count(DISTINCT ac.' . Activity::PK . ')')
                ]
            );

            $have = $select->having;
            if ($have->count() > 0) {
                foreach ($have->getPredicates() as $h) {
                    $countSelect->where($h);
                }
            }

            $results = $this->zdb->execute($countSelect);

            $this->count = (int)$results->current()->count;
            if (isset($this->filters) && $this->count > 0) {
                $this->filters->setCounter($this->count);
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot count activities | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get count for current query
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Add default activities in database
     *
     * @param bool $check_first Check first if it seems initialized
     */
    public function installInit(bool $check_first = true): bool
    {
        //to satisfy inheritance
        return true;
    }
}
