<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Repository;

use Analog\Analog;
use Galette\Entity\Adherent;
use GaletteEvents\Booking;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Galette\Core\Login;
use Galette\Core\Db;
use Galette\Entity\Group;
use Galette\Repository\Groups;
use GaletteEvents\Event;
use GaletteEvents\Filters\EventsList;
use Laminas\Db\Sql\Select;

/**
 * Events
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Events
{
    private Db $zdb;
    private Login $login;
    private EventsList $filters;
    private int $count = 0;

    public const int ORDERBY_DATE = 0;
    public const int ORDERBY_NAME = 1;
    public const int ORDERBY_TOWN = 2;

    /**
     * Constructor
     *
     * @param Db          $zdb     Database instance
     * @param Login       $login   Login instance
     * @param ?EventsList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, ?EventsList $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

        if ($filters === null) {
            $this->filters = new EventsList();
        } else {
            $this->filters = $filters;
        }
    }

    /**
     * Get events list
     *
     * @param bool $onlyevents   get events member has booking on
     * @param bool $fullcalendar get events for fullcalendar display (ie. end date +1 day)
     *
     * @return array<int|string, Event|array<string, mixed>>
     */
    public function getList(bool $onlyevents = false, bool $fullcalendar = false): array
    {
        try {
            $select = $this->zdb->select(EVENTS_PREFIX . Event::TABLE, 'e');

            $select->join(
                ['b' => PREFIX_DB . EVENTS_PREFIX . Booking::TABLE],
                'e.' . Event::PK . '=b.' . Event::PK,
                [],
                $select::JOIN_LEFT
            );

            $groups = null;
            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                if ($this->login->isGroupManager()) {
                    $groups = $this->login->managed_groups;
                    $set = [new PredicateSet(
                        [
                            new Predicate\IsNull(Group::PK),
                            new Predicate\Operator(
                                'is_open',
                                '=',
                                true
                            ),
                            new Predicate\Operator(
                                'begin_date',
                                '>=',
                                date('Y-m-d')
                            )
                        ]
                    )];

                    if (count($groups)) {
                        $set[] = new Predicate\In(
                            Group::PK,
                            $groups
                        );
                    }

                    if ($onlyevents === false) {
                        //get events member has booking on
                        $set[] = new Predicate\Operator(
                            'b.' . Adherent::PK,
                            '=',
                            $this->login->id
                        );
                    }

                    $select->where(
                        new PredicateSet(
                            $set,
                            PredicateSet::OP_OR
                        )
                    );
                } else {
                    $select->where(
                        'is_open',
                        //@phpstan-ignore-next-line
                        new Expression('true')
                    );
                    $select->where->greaterThanOrEqualTo('begin_date', date('Y-m-d'));

                    $set = [new Predicate\IsNull(Group::PK)];
                    $groups = Groups::loadGroups($this->login->id, false, false);
                    if (count($groups)) {
                        $set[] = new Predicate\In(
                            Group::PK,
                            $groups
                        );
                    }

                    if ($onlyevents === false) {
                        //get events member has booking on

                        $set[] = new Predicate\Operator(
                            'b.' . Adherent::PK,
                            '=',
                            $this->login->id
                        );
                    }

                    $select->where(
                        new PredicateSet(
                            $set,
                            PredicateSet::OP_OR
                        )
                    );
                }
            } else {
                if (isset($this->filters->start_date_filter) && $this->filters->raw_start_date_filter !== null) {
                    $select->where->greaterThanOrEqualTo('begin_date', $this->filters->raw_start_date_filter);
                }
            }

            $select->group(['e.' . Event::PK]);
            $select->order($this->buildOrderClause());

            $this->proceedCount($select);

            if (!$this->filters->calendar_filter) {
                $this->filters->setLimits($select);
            }
            $results = $this->zdb->execute($select);
            $this->filters->query = $this->zdb->query_string;

            $events = [];
            foreach ($results as $row) {
                $event = new Event($this->zdb, $this->login, $row);
                if (!$this->filters->calendar_filter) {
                    $events[] = $event;
                } else {
                    //required entries for fullcalendar
                    $row['title'] = $row['name'];
                    $row['start'] = $row['begin_date'];
                    $end_date = new \DateTime($event->getEndDate(false));
                    if ($fullcalendar === true) {
                        $end_date = $end_date->modify('+1 day');
                        $row['textColor'] = $event->getForegoundColor();
                    }
                    $row['end'] = $end_date->format('Y-m-d');

                    //extended description
                    $row['begin_date_fmt'] = $event->getBeginDate();
                    $row['end_date_fmt'] = $event->getEndDate();
                    $description = '<h4>';
                    $description .= _T('Event information', 'events');
                    $description .= '</h4>';
                    $description .= '<ul class="ui bulleted list">';
                    $pattern = '<li><strong>%1$s</strong> %2$s</li>';
                    $description .= sprintf($pattern, _T("Start date:", "events"), $event->getBeginDate());
                    $description .= sprintf($pattern, _T("End date:", "events"), $event->getEndDate());
                    $description .= sprintf($pattern, _T("Location:", "events"), $this->escape($event->getTown()));
                    if ($comment = $event->getComment()) {
                        $description .= sprintf($pattern, _T("Comment:", "events"), $this->escape($comment));
                    }

                    /** @var ResultSet $attendees */
                    $attendees = $event->countAttendees();
                    $total_attendees = 0;
                    $paid_attendees = 0;
                    foreach ($attendees as $attendee) {
                        $total_attendees += $attendee['count'];
                        if ($attendee['is_paid']) {
                            $paid_attendees += $attendee['count'];
                        }
                    }

                    $attendees_str = $total_attendees;
                    if ($total_attendees) {
                        //TRANS: %1$s is the number of paid attendees
                        $attendees_str .= ' (' . sprintf(_T('%1$s paid', 'events'), $paid_attendees) . ')';
                    }

                    $description .= sprintf(
                        $pattern,
                        _T("Attendees:", "events"),
                        $attendees_str
                    );

                    $description .= '</ul>';

                    $activities = $event->getActivities();
                    if (count($activities)) {
                        $description .= '<h4>' . _T('Activities', 'events') . '</h4>';
                        $description .= '<ul class="ui bulleted list">';
                        foreach ($activities as $activity) {
                            $description .= '<li>' . $this->escape($activity['activity']->getName()) . '</li>';
                        }
                        $description .= '</ul>';
                    }

                    $row['description'] = $description;

                    $events[] = $row;
                }
            }

            return $events;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list events | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Escape a value typed by users for the calendar HTML description
     *
     * @param string $value Value to escape
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Is field allowed to order? it should be present in
     * provided fields list (those that are SELECT'ed).
     *
     * @param string         $field_name Field name to order by
     * @param ?array<string> $fields     SELECTE'ed fields
     */
    private function canOrderBy(string $field_name, ?array $fields = null): bool
    {
        if (!is_array($fields)) {
            return true;
        } elseif (in_array($field_name, $fields)) {
            return true;
        } else {
            Analog::log(
                'Trying to order by ' . $field_name . ' while it is not in '
                . 'selected fields.',
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Builds the order clause
     *
     * @param array<string> $fields Fields list to ensure ORDER clause
     *                              references selected fields. Optional.
     *
     * @return array<string> SQL ORDER clauses
     */
    private function buildOrderClause(?array $fields = null): array
    {
        $order = [];

        switch ($this->filters->orderby) {
            case self::ORDERBY_DATE:
                if ($this->canOrderBy('begin_date', $fields)) {
                    $order[] = 'begin_date ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_NAME:
                if ($this->canOrderBy('name', $fields)) {
                    $order[] = 'name ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_TOWN:
                if ($this->canOrderBy('town', $fields)) {
                    $order[] = 'town ' . $this->filters->getDirection();
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
            $countSelect->reset($countSelect::GROUP);
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
                    'count' => new Expression('count(DISTINCT e.' . Event::PK . ')')
                ]
            );

            $have = $select->having;
            if ($have->count() > 0) {
                foreach ($have->getPredicates() as $h) {
                    $countSelect->where($h);
                }
            }

            $results = $this->zdb->execute($countSelect);

            if ($result = $results->current()) {
                $this->count = (int)$result->count;
                if ($this->count > 0) {
                    $this->filters->setCounter($this->count);
                }
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot count events | ' . $e->getMessage(),
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
}
