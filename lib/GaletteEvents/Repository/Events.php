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
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Galette\Core\Login;
use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Preferences;
use Galette\Entity\Group;
use Galette\Repository\Groups;
use GaletteEvents\Event;
use GaletteEvents\Filters\EventsList;

/**
 * Events
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Events extends AbstractRepository
{
    protected const string PK = Event::PK;
    protected const string ALIAS = 'e';

    /** @var EventsList */
    protected \Galette\Core\Pagination $filters;

    public const int ORDERBY_DATE = 0;
    public const int ORDERBY_NAME = 1;
    public const int ORDERBY_TOWN = 2;

    /**
     * Constructor
     *
     * @param Db          $zdb         Database instance
     * @param Login       $login       Login instance
     * @param History     $history     History instance
     * @param Preferences $preferences Preferences instance
     * @param ?EventsList $filters     Filtering
     */
    public function __construct(
        Db $zdb,
        Login $login,
        History $history,
        Preferences $preferences,
        ?EventsList $filters = null
    ) {
        parent::__construct($zdb, $login, $history, $preferences, 'Event', $filters ?? new EventsList());
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
                $groups = self::getVisibleGroups($this->login);

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
                            new Predicate\Operator('e.is_open', '=', 1),
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

            $rows = [];
            foreach ($results as $row) {
                $rows[] = $row;
            }
            $attendees = [];
            if ($this->filters->calendar_filter) {
                $attendees = $this->countAttendees(array_map(fn(ArrayObject $row): int => (int)$row[Event::PK], $rows));
            }

            $events = [];
            foreach ($rows as $row) {
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

                    $total_attendees = $attendees[$event->getId()]['total'] ?? 0;
                    $paid_attendees = $attendees[$event->getId()]['paid'] ?? 0;

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
     * Groups whose events a member can see and book: the ones they belong to or manage
     *
     * Events without group are visible to every member.
     *
     * @param Login $login Logged-in member
     *
     * @return array<int>
     */
    public static function getVisibleGroups(Login $login): array
    {
        return array_values(array_unique(array_merge(
            array_map('intval', Groups::loadGroups((int)$login->id, false, false)),
            array_map('intval', $login->managed_groups)
        )));
    }

    /**
     * Is an event of this group visible to a member
     *
     * @param ?int  $group Event group, if any
     * @param Login $login Logged-in member
     */
    public static function isVisible(?int $group, Login $login): bool
    {
        return $login->isAdmin()
            || $login->isStaff()
            || $group === null
            || in_array($group, self::getVisibleGroups($login), true);
    }

    /**
     * Count attendees of events, and the paid ones
     *
     * @param array<int> $ids Events identifiers
     *
     * @return array<int, array{total: int, paid: int}>
     */
    private function countAttendees(array $ids): array
    {
        if (count($ids) === 0) {
            return [];
        }

        $select = $this->zdb->select(EVENTS_PREFIX . Booking::TABLE, 'b');
        $select->columns([
            Event::PK,
            'is_paid',
            'count' => new Expression('SUM(b.number_people)')
        ]);
        $select->where([Event::PK => $ids]);
        $select->group([Event::PK, 'is_paid']);

        $attendees = [];
        foreach ($this->zdb->execute($select) as $row) {
            $id = (int)$row[Event::PK];
            $attendees[$id] ??= ['total' => 0, 'paid' => 0];
            $attendees[$id]['total'] += (int)$row['count'];
            if ($row['is_paid']) {
                $attendees[$id]['paid'] += (int)$row['count'];
            }
        }
        return $attendees;
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
     * Builds the order clause
     *
     * @return array<string> SQL ORDER clauses
     */
    private function buildOrderClause(): array
    {
        $column = match ($this->filters->orderby) {
            self::ORDERBY_NAME => 'e.name',
            self::ORDERBY_TOWN => 'e.town',
            default => 'e.begin_date',
        };
        return [$column . ' ' . $this->filters->getDirection()];
    }
}
