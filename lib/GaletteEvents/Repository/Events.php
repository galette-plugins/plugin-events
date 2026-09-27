<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Repository;

use Analog\Analog;
use ArrayObject;
use Galette\Entity\Adherent;
use GaletteEvents\Booking;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Galette\Core\Login;
use Galette\Core\Db;
use Galette\Core\History;
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
    private History $history;
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
     * @param History     $history History instance
     * @param ?EventsList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, History $history, ?EventsList $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;
        $this->history = $history;

        if ($filters === null) {
            $this->filters = new EventsList();
        } else {
            $this->filters = $filters;
        }
    }

    /**
     * Get events list
     *
     * Members get open and upcoming events that are public or restricted to their groups,
     * and the ones they have booked; group managers also get events of the groups they manage.
     * In calendar, members get all those events, even past or closed ones, in requested dates.
     *
     * @param bool $bookable     get only events current logged-in user can book, not paginated
     * @param bool $fullcalendar get events for fullcalendar display (ie. end date +1 day)
     * @param bool $full         get full list, not paginated
     *
     * @return array<int|string, Event|ArrayObject<string, mixed>>
     */
    public function getList(bool $bookable = false, bool $fullcalendar = false, bool $full = false): array
    {
        try {
            $select = $this->zdb->select(EVENTS_PREFIX . Event::TABLE, 'e');

            $select->join(
                ['b' => PREFIX_DB . EVENTS_PREFIX . Booking::TABLE],
                'e.' . Event::PK . '=b.' . Event::PK,
                [],
                $select::JOIN_LEFT
            );

            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                $managed = array_map('intval', $this->login->managed_groups);
                $groups = array_unique(array_merge(
                    array_map('intval', Groups::loadGroups((int)$this->login->id, false, false)),
                    $managed
                ));

                $visible = [new Predicate\IsNull('e.' . Group::PK)];
                if (count($groups)) {
                    $visible[] = new Predicate\In('e.' . Group::PK, $groups);
                }
                $visible = new PredicateSet($visible, PredicateSet::OP_OR);

                $booked = new Predicate\Operator('b.' . Adherent::PK, '=', $this->login->id);

                if ($this->filters->calendar_filter) {
                    $set = [$visible, $booked];
                } else {
                    $set = [new PredicateSet(
                        [
                            new Predicate\Operator('e.is_open', '=', true),
                            new Predicate\Operator('e.begin_date', '>=', date('Y-m-d')),
                            $visible
                        ]
                    )];
                    if (!$bookable) {
                        if (count($managed)) {
                            //managers get events of their groups, whatever their state
                            $set[] = new Predicate\In('e.' . Group::PK, $managed);
                        }
                        $set[] = $booked;
                    }
                }

                $select->where(new PredicateSet($set, PredicateSet::OP_OR));
            }

            if ($this->filters->raw_start_date_filter !== null) {
                if ($this->filters->calendar_filter) {
                    //events overlapping requested dates
                    $select->where->greaterThanOrEqualTo('e.end_date', $this->filters->raw_start_date_filter);
                } else {
                    $select->where->greaterThanOrEqualTo('e.begin_date', $this->filters->raw_start_date_filter);
                }
            }
            if ($this->filters->raw_end_date_filter !== null) {
                $select->where->lessThanOrEqualTo('e.begin_date', $this->filters->raw_end_date_filter);
            }

            $select->group(['e.' . Event::PK]);
            $select->order($this->buildOrderClause());

            $this->proceedCount($select);

            if (!$this->filters->calendar_filter && !$bookable && !$full) {
                $this->filters->setLimits($select);
            }
            $results = $this->zdb->execute($select);
            $this->filters->query = $this->zdb->query_string;

            $events = [];
            foreach ($results as $row) {
                $event = new Event($this->zdb, $this->login, $this->history, $row);
                if (!$this->filters->calendar_filter) {
                    $events[] = $event;
                } else {
                    //required entries for fullcalendar
                    $row['title'] = $row['name'];
                    $row['can_edit'] = $event->canEdit($this->login);
                    $row['start'] = $row['begin_date'];
                    $end_date = new \DateTime($event->getEndDate());
                    if ($fullcalendar === true) {
                        $end_date = $end_date->modify('+1 day');
                        $row['textColor'] = $event->getForegroundColor();
                    }
                    $row['end'] = $end_date->format('Y-m-d');

                    //extended description
                    $row['begin_date_fmt'] = $this->formatDate($event->getBeginDate());
                    $row['end_date_fmt'] = $this->formatDate($event->getEndDate());
                    $description = '<h4>';
                    $description .= _T('Event information', 'events');
                    $description .= '</h4>';
                    $description .= '<ul class="ui bulleted list">';
                    $pattern = '<li><strong>%1$s</strong> %2$s</li>';
                    $description .= sprintf($pattern, _T("Start date:", "events"), $row['begin_date_fmt']);
                    $description .= sprintf($pattern, _T("End date:", "events"), $row['end_date_fmt']);
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
     * Format a date for the calendar
     *
     * @param string $date Date, as Y-m-d
     */
    private function formatDate(string $date): string
    {
        return (new \DateTime($date))->format(__('Y-m-d'));
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
