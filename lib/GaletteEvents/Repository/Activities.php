<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Repository;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Preferences;
use GaletteEvents\Activity;
use GaletteEvents\Filters\ActivitiesList;

/**
 * Activities
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activities extends AbstractRepository
{
    protected const string PK = Activity::PK;
    protected const string ALIAS = 'ac';

    /** @var ActivitiesList */
    protected \Galette\Core\Pagination $filters;

    public const int ORDERBY_DATE = 0;
    public const int ORDERBY_NAME = 1;

    /**
     * Constructor
     *
     * @param Db              $zdb         Database instance
     * @param Login           $login       Login instance
     * @param History         $history     History instance
     * @param Preferences     $preferences Preferences instance
     * @param ?ActivitiesList $filters     Filtering
     */
    public function __construct(
        Db $zdb,
        Login $login,
        History $history,
        Preferences $preferences,
        ?ActivitiesList $filters = null
    ) {
        parent::__construct($zdb, $login, $history, $preferences, 'Activity', $filters ?? new ActivitiesList());
    }

    /**
     * Get activities list
     *
     * @return array<int, Activity>
     */
    public function getList(): array
    {
        try {
            $select = $this->zdb->select(EVENTS_PREFIX . Activity::TABLE, 'ac');
            $this->proceedCount($select);
            $select->order($this->buildOrderClause());
            $this->filters->setLimits($select);

            $activities = [];
            foreach ($this->zdb->execute($select) as $row) {
                $activities[] = new Activity($this->zdb, $this->history, $row);
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
     * @return array<string> SQL ORDER clauses
     */
    private function buildOrderClause(): array
    {
        $column = match ($this->filters->orderby) {
            self::ORDERBY_NAME => 'ac.name',
            default => 'ac.creation_date',
        };
        return [$column . ' ' . $this->filters->getDirection()];
    }
}
