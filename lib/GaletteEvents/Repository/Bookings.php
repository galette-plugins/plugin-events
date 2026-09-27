<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Repository;

use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Galette\Core\Login;
use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use GaletteEvents\Event;
use GaletteEvents\Booking;
use GaletteEvents\Filters\BookingsList;
use Laminas\Db\Sql\Select;

/**
 * Bookings
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Bookings extends AbstractRepository
{
    protected const string PK = Booking::PK;
    protected const string ALIAS = 'b';

    /** @var BookingsList */
    protected \Galette\Core\Pagination $filters;

    private float $sum = 0;

    public const int ORDERBY_EVENT = 0;
    public const int ORDERBY_MEMBER = 1;
    public const int ORDERBY_BOOKDATE = 2;
    public const int ORDERBY_PAID = 3;

    public const int FILTER_DC_PAID = 0;
    public const int FILTER_PAID = 1;
    public const int FILTER_NOT_PAID = 2;

    /**
     * Constructor
     *
     * @param Db            $zdb         Database instance
     * @param Login         $login       Login instance
     * @param History       $history     History instance
     * @param Preferences   $preferences Preferences instance
     * @param ?BookingsList $filters     Filtering
     */
    public function __construct(
        Db $zdb,
        Login $login,
        History $history,
        Preferences $preferences,
        ?BookingsList $filters = null
    ) {
        parent::__construct($zdb, $login, $history, $preferences, 'Booking', $filters ?? new BookingsList());
    }

    /**
     * Get booking list
     *
     * @param bool $full Export full list (no pagination), defaults to false
     *
     * @return array<Booking>
     */
    public function getList(bool $full = false): array
    {
        try {
            $select = $this->buildSelect();
            $this->calculateSum($select);
            $this->proceedCount($select);
            $select->order($this->buildOrderClause());

            if ($full !== true) {
                $this->filters->setLimits($select);
            }
            $results = $this->zdb->execute($select);

            $bookings = [];
            foreach ($results as $row) {
                $booking = new Booking($this->zdb, $this->login, $this->history, $row);
                $bookings[] = $booking;
            }
            $this->loadEvents($bookings);

            return $bookings;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list bookings | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Load events of listed bookings, once each
     *
     * @param array<Booking> $bookings Bookings
     */
    private function loadEvents(array $bookings): void
    {
        $ids = array_unique(array_map(fn(Booking $booking): int => (int)$booking->getEventId(), $bookings));
        if (count($ids) === 0) {
            return;
        }

        $select = $this->zdb->select(EVENTS_PREFIX . Event::TABLE);
        $select->where([Event::PK => array_values($ids)]);
        $events = [];
        foreach ($this->zdb->execute($select) as $row) {
            $events[(int)$row[Event::PK]] = new Event($this->zdb, $this->login, $this->history, $row);
        }

        foreach ($bookings as $booking) {
            $booking->useEvent($events[$booking->getEventId()]);
        }
    }

    /**
     * Builds the SELECT statement, filtered but neither ordered nor limited
     */
    private function buildSelect(): Select
    {
        $select = $this->zdb->select(EVENTS_PREFIX . Booking::TABLE, 'b');

        //joined tables are used for filtering and ordering only, their columns would override bookings ones
        $select->join(
            ['a' => PREFIX_DB . Adherent::TABLE],
            'b.' . Adherent::PK . '= a.' . Adherent::PK,
            []
        );
        $select->join(
            ['e' => PREFIX_DB . EVENTS_PREFIX . Event::TABLE],
            'b.' . Event::PK . '= e.' . Event::PK,
            []
        );

        $this->buildWhereClause($select);
        return $select;
    }

    /**
     * Calculate sum of all selected bookings
     *
     * @param Select $select Original select
     */
    private function calculateSum(Select $select): void
    {
        $sum_select = clone $select;
        $sum_select->columns(['sum' => new Expression('SUM(b.payment_amount)')]);
        $this->sum = round((float)$this->zdb->execute($sum_select)->current()['sum'], 2);
    }

    /**
     * Builds where clause, for filtering on simple list mode
     *
     * @param Select $select Original select
     */
    private function buildWhereClause(Select $select): void
    {
        try {
            switch ($this->filters->paid_filter) {
                case self::FILTER_PAID:
                    $select->where(['b.is_paid' => 1]);
                    break;
                case self::FILTER_NOT_PAID:
                    $select->where(['b.is_paid' => 0]);
                    break;
                case self::FILTER_DC_PAID:
                    //nothing to do here.
                    break;
            }

            if ($this->filters->event_filter !== null) {
                $select->where(['b.' . Event::PK => $this->filters->event_filter]);
            }

            if ($this->filters->payment_type_filter != -1) {
                $select->where->equalTo(
                    'payment_method',
                    $this->filters->payment_type_filter
                );
            }

            if ($this->filters->group_filter !== null) {
                $select->where(['e.' . Group::PK => $this->filters->group_filter]);
            }

            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                //members see their own bookings, group managers also the ones on events of groups they manage
                $set = [
                    new Predicate\Operator(
                        'a.' . Adherent::PK,
                        '=',
                        $this->login->id
                    )
                ];

                if ($this->login->isGroupManager() && count($this->login->managed_groups)) {
                    $set[] = new Predicate\In(
                        'e.' . Group::PK,
                        $this->login->managed_groups
                    );
                }

                $select->where(
                    new PredicateSet(
                        $set,
                        PredicateSet::OP_OR
                    )
                );
            }

            if (count($this->filters->selected)) {
                $select->where([Booking::PK => $this->filters->selected]);
            }
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
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
        $columns = match ($this->filters->orderby) {
            self::ORDERBY_EVENT => ['e.name'],
            self::ORDERBY_MEMBER => ['a.nom_adh', 'a.prenom_adh'],
            self::ORDERBY_PAID => ['b.is_paid'],
            default => ['b.booking_date'],
        };
        $direction = $this->filters->getDirection();
        return array_map(fn(string $column): string => $column . ' ' . $direction, $columns);
    }

    /**
     * Get sum
     */
    public function getSum(): float
    {
        return $this->sum;
    }
}
