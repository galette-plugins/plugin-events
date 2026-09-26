<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents;

use ArrayObject;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Group;
use Analog\Analog;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Expression;

/**
 * Event entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Event
{
    public const string TABLE = 'events';
    public const string PK = 'id_event';

    private Db $zdb;
    private Login $login;
    /** @var array<string> */
    private array $errors;

    private int $id;
    private string $name;
    private string $address = '';
    private string $zip = '';
    private string $town = '';
    private ?string $country = null;
    private string $begin_date;
    private string $end_date;
    private string $creation_date;
    private bool $open = true;
    private ?int $group = null;
    private string $comment = '';
    private ?string $color = null;

    /** @var array<int, array<string, mixed>> */
    private array $activities = [];

    /**
     * Default constructor
     *
     * @param Db                                      $zdb   Database instance
     * @param Login                                   $login Login instance
     * @param null|int|ArrayObject<string,int|string> $args  Either a ResultSet row or its id for to load
     *                                                       a specific event, or null to just
     *                                                       instanciate object
     */
    public function __construct(Db $zdb, Login $login, int|ArrayObject|null $args = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;
        if ($args == null || is_int($args)) {
            if (is_int($args) && $args > 0) {
                $this->load($args);
            } else {
                $now = date('Y-m-d');
                $this->begin_date = $now;
                $this->end_date = $now;
            }
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
            $this->loadActivities();
        }
    }

    /**
     * Loads an event from its id
     *
     * @param int $id the identifiant for the event to load
     *
     * @return bool true if query succeed, false otherwise
     */
    public function load(int $id): bool
    {
        try {
            $select = $this->zdb->select($this->getTableName());
            $select->where([self::PK => $id]);

            $results = $this->zdb->execute($select);

            if ($results->count() > 0) {
                $this->loadFromRS($results->current());
                $this->loadActivities();
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load event form id `' . $id . '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, int|string> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = (int)$r->id_event;
        $this->name = $r->name;
        $this->address = $r->address;
        $this->zip = $r->zip;
        $this->town = $r->town;
        $this->country = $r->country;
        $this->begin_date = $r->begin_date;
        $this->end_date = $r->end_date;
        $this->creation_date = $r->creation_date;
        $this->open = (bool)$r->is_open;
        $this->group = $r->id_group === null ? null : (int)$r->id_group;
        $this->comment = $r->comment ?? '';
        $this->color = $r->color;
    }

    /**
     * Remove specified event
     */
    public function remove(): bool
    {
        $transaction = false;

        try {
            if (!$this->zdb->connection->inTransaction()) {
                $this->zdb->connection->beginTransaction();
                $transaction = true;
            }

            $delete = $this->zdb->delete($this->getTableName());
            $delete->where([self::PK => $this->id]);
            $this->zdb->execute($delete);

            //commit all changes
            if ($transaction) {
                $this->zdb->connection->commit();
            }

            return true;
        } catch (\Exception $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'Unable to delete event ' . $this->name
                . ' (' . $this->id . ') |' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Check posted values validity
     *
     * @param array<string, mixed> $values All values to check, basically the $_POST array
     *                                     after sending the form
     *
     * @return true|array<string>
     */
    public function check(array $values): bool|array
    {
        $this->errors = [];

        if (empty($values['begin_date'])) {
            $this->errors[] = _T('Begin date is mandatory', 'events');
        } else {
            //handle dates
            foreach (['begin_date', 'end_date'] as $datefield) {
                if (isset($values[$datefield])) {
                    $value = $values[$datefield];
                    try {
                        $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                        if ($d === false) {
                            //try with non localized date
                            $d = \DateTime::createFromFormat("Y-m-d", $value);
                            if ($d === false) {
                                throw new \Exception('Incorrect format');
                            }
                        }
                        $this->$datefield = $d->format('Y-m-d');
                    } catch (\Exception $e) {
                        Analog::log(
                            'Wrong date format. field: ' . $datefield
                            . ', value: ' . $value . ', expected fmt: '
                            . __("Y-m-d") . ' | ' . $e->getMessage(),
                            Analog::INFO
                        );
                        if ($datefield == 'begin_date') {
                            $label = _T('Begin date', 'events');
                        } else {
                            $label = _T('End date', 'events');
                        }
                        $this->errors[] = sprintf(
                            //TRANS %1$s is the expected date format, %2$s is the field label
                            _T('- Wrong date format (%1$s) for %2$s!'),
                            __("Y-m-d"),
                            $label
                        );
                    }
                }
            }

            if (!isset($values['end_date'])) {
                $this->end_date = $this->begin_date;
            } elseif (!count($this->errors)) {
                $dend = new \DateTime($this->end_date);
                $dbegin = new \DateTime($this->begin_date);
                if ($dend < $dbegin) {
                    $this->errors[] = _T('End date must be later or equal to begin date', 'events');
                }
            }
        }

        if (empty($values['name'])) {
            $this->errors[] = _T('Name is mandatory', 'events');
        } else {
            $this->name = $values['name'];
        }

        if ($this->login->isAdmin() || $this->login->isStaff()) {
            if (isset($values['group']) && !empty($values['group'])) {
                $this->group = (int)$values['group'];
            } else {
                $this->group = null;
            }
        } else {
            if (
                empty($values['group'])
                || !in_array((int)$values['group'], array_map('intval', $this->login->managed_groups), true)
            ) {
                $this->errors[] = _T('Please select a group you own!', 'events');
            } else {
                $this->group = (int)$values['group'];
            }
        }

        if (empty($values['town'])) {
            $this->errors[] = _T('Town is mandatory', 'events');
        } else {
            $this->town = $values['town'];
        }

        $otherfields = [
            'address',
            'zip',
            'country',
            'comment',
            'color'
        ];
        foreach ($otherfields as $otherfield) {
            if (isset($values[$otherfield])) {
                $this->$otherfield = $values[$otherfield];
            }
        }

        //the form posts every linked activity: posted list replaces the current one
        $detached = null;
        if (
            isset($values['remove_activity'])
            && !empty($values['detach_activity'])
        ) {
            $detached = (int)$values['detach_activity'];
        }

        $activities = [];
        foreach ($values['activities_ids'] ?? [] as $row => $activity_id) {
            $activity_id = (int)$activity_id;
            $status = (int)($values['activities_status'][$row] ?? Activity::YES);
            if ($activity_id === $detached || !in_array($status, [Activity::NO, Activity::YES, Activity::REQUIRED], true)) {
                continue;
            }
            //already linked activities stay, even if they have been deactivated since
            $activity = $this->activities[$activity_id]['activity'] ?? $this->getActiveActivity($activity_id);
            if ($activity !== null) {
                $activities[$activity_id] = [
                    'activity'  => $activity,
                    'status'    => $status
                ];
            }
        }

        if (
            isset($values['add_activity'])
            && !empty($values['attach_activity'])
            && !isset($activities[(int)$values['attach_activity']])
        ) {
            $activity = $this->getActiveActivity((int)$values['attach_activity']);
            if ($activity !== null) {
                $activities[(int)$values['attach_activity']] = [
                    'activity'  => $activity,
                    'status'    => Activity::YES
                ];
            }
        }
        $this->activities = $activities;

        if (isset($values['open'])) {
            $this->open = true;
        } else {
            $this->open = false;
        }

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been threw attempting to edit/store an event' . "\n"
                . print_r($this->errors, true),
                Analog::ERROR
            );
            return $this->errors;
        } else {
            Analog::log(
                'Event checked successfully.',
                Analog::DEBUG
            );
            return true;
        }
    }

    /**
     * Store the event
     */
    public function store(): bool
    {
        global $hist;

        try {
            $this->zdb->connection->beginTransaction();
            $values = [
                'name'                  => $this->name,
                'address'               => $this->address,
                'zip'                   => $this->zip,
                'town'                  => $this->town,
                'country'               => ($this->country ?: new Expression('NULL')),
                'begin_date'            => $this->begin_date,
                'end_date'              => $this->end_date,
                'is_open'               => ($this->open
                                                ?: ($this->zdb->isPostgres() ? 'false' : 0)),
                Group::PK               => ($this->group ?: new Expression('NULL')),
                'comment'               => $this->comment,
                'color'                 => $this->color
            ];

            if (empty($this->id)) {
                //we're inserting a new event
                $this->creation_date = date("Y-m-d H:i:s");
                $values['creation_date'] = $this->creation_date;

                $insert = $this->zdb->insert($this->getTableName());
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() > 0) {
                    if ($this->zdb->isPostgres()) {
                        /** @phpstan-ignore-next-line */
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue(
                            PREFIX_DB . EVENTS_PREFIX . Event::TABLE . '_id_seq'
                        );
                    } else {
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue();
                    }

                    // logging
                    $hist->add(
                        _T("Event added", "events"),
                        $this->name
                    );
                } else {
                    $hist->add(_T("Fail to add new event.", "events"));
                    throw new \Exception(
                        'An error occurred inserting new event!'
                    );
                }
            } else {
                $values['id_event'] = $this->id;
                //we're editing an existing event
                $update = $this->zdb->update($this->getTableName());
                $update
                    ->set($values)
                    ->where([self::PK => $this->id]);

                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $hist->add(
                        _T("Event updated", "events"),
                        $this->name
                    );
                }
            }

            $this->storeActivities();

            $this->zdb->connection->commit();
            return true;
        } catch (\Exception $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n"
                . $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get an activity that can be attached to the event
     *
     * @param int $id Activity ID
     */
    private function getActiveActivity(int $id): ?Activity
    {
        $activity = new Activity($this->zdb, $this->login, $id);
        return $activity->getId() !== null && $activity->isActive() ? $activity : null;
    }

    /**
     * Store activities linked to the event, compared to the stored ones
     */
    private function storeActivities(): void
    {
        $table = EVENTS_PREFIX . 'activitiesevents';

        $stored = [];
        $select = $this->zdb->select($table);
        $select->where([self::PK => $this->id]);
        foreach ($this->zdb->execute($select) as $row) {
            $stored[(int)$row[Activity::PK]] = (int)$row['status'];
        }

        $counts = ['added' => 0, 'updated' => 0, 'removed' => 0];
        foreach ($this->activities as $aid => $data) {
            $status = (int)$data['status'];
            if (!isset($stored[$aid])) {
                $insert = $this->zdb->insert($table);
                $insert->values([
                    self::PK        => $this->id,
                    Activity::PK    => $aid,
                    'status'        => $status
                ]);
                $this->zdb->execute($insert);
                ++$counts['added'];
            } elseif ($stored[$aid] !== $status) {
                $update = $this->zdb->update($table);
                $update->set(['status' => $status])->where([
                    self::PK        => $this->id,
                    Activity::PK    => $aid
                ]);
                $this->zdb->execute($update);
                ++$counts['updated'];
            }
        }

        foreach (array_keys($stored) as $aid) {
            if (!isset($this->activities[$aid])) {
                $delete = $this->zdb->delete($table);
                $delete->where([
                    self::PK        => $this->id,
                    Activity::PK    => $aid
                ]);
                $this->zdb->execute($delete);
                ++$counts['removed'];
            }
        }

        foreach ($counts as $action => $count) {
            if ($count > 0) {
                Analog::log(
                    sprintf('%1$s activities %2$s', $count, $action),
                    Analog::INFO
                );
            }
        }
    }

    /**
     * Get event id
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get event name
     */
    public function getName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * Get event address
     */
    public function getAddress(): ?string
    {
        return $this->address ?? null;
    }

    /**
     * Get event zip
     */
    public function getZip(): ?string
    {
        return $this->zip ?? null;
    }

    /**
     * Get event town
     */
    public function getTown(): ?string
    {
        return $this->town ?? null;
    }

    /**
     * Get event country
     */
    public function getCountry(): ?string
    {
        return $this->country ?? null;
    }

    /**
     * Get event group
     */
    public function getGroup(): ?int
    {
        return $this->group ?? null;
    }

    /**
     * Get group name
     */
    public function getGroupName(): string
    {
        $name = '-';
        if ($this->group) {
            $group = new Group($this->group);
            $name = $group->getFullName();
        }
        return $name;
    }

    /**
     * Get date
     *
     * @param string $prop      Property to use
     * @param bool   $formatted Return date formatted, raw if false
     */
    private function getDate(string $prop, bool $formatted = true): string
    {
        if ($formatted === true) {
            $date = new \DateTime($this->$prop);
            return $date->format(__("Y-m-d"));
        } else {
            return $this->$prop;
        }
    }

    /**
     * Get creation date
     *
     * @param bool $formatted Return date formatted, raw if false
     */
    public function getCreationDate(bool $formatted = true): string
    {
        return $this->getDate('creation_date', $formatted);
    }

    /**
     * Get begin date
     *
     * @param bool $formatted Return date formatted, raw if false
     */
    public function getBeginDate(bool $formatted = true): string
    {
        return $this->getDate('begin_date', $formatted);
    }

    /**
     * Get end date
     *
     * @param bool $formatted Return date formatted, raw if false
     */
    public function getEndDate(bool $formatted = true): string
    {
        return $this->getDate('end_date', $formatted);
    }

    /**
     * Is activity required
     *
     * @param int $activity Activity ID
     */
    public function isActivityRequired(int $activity): bool
    {
        return $this->activities[$activity]['status'] == Activity::REQUIRED;
    }

    /**
     * Does current event propose activity
     *
     * @param int $activity Activity ID
     */
    public function hasActivity(int $activity): bool
    {
        return $this->activities[$activity]['status'] != Activity::NO;
    }

    /**
     * Has event been flagged as open?
     * Unlike isOpen(), whatever its dates
     */
    public function isOpenFlag(): bool
    {
        return $this->open;
    }

    /**
     * Is event open?
     * Will return false once the begin date has been exceeded
     */
    public function isOpen(): bool
    {
        if ($this->open) {
            try {
                $date = new \DateTime($this->begin_date);
                $now  = new \DateTime();
                $now->setTime(0, 0);
                return $date >= $now;
            } catch (\Exception $e) {
                //no begin date, or invalid date...
                return true;
            }
        }
        return false;
    }

    /**
     * Get table's name
     */
    protected function getTableName(): string
    {
        return EVENTS_PREFIX . self::TABLE;
    }

    /**
     * Get activities list
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableActivities(): array
    {
        $select = $this->zdb->select(EVENTS_PREFIX . Activity::TABLE, 'ac');
        $select->where->equalTo('is_active', true);
        $results = $this->zdb->execute($select);

        $activities = [];
        foreach ($results as $result) {
            if (!isset($this->activities[$result->{Activity::PK}])) {
                $activities[] = $result;
            }
        }

        return $activities;
    }

    /**
     * Load linked activities
     */
    public function loadActivities(): void
    {
        $this->activities = [];
        $select = $this->zdb->select(EVENTS_PREFIX . 'activitiesevents', 'ace');
        $select->where([self::PK => $this->id]);
        $results = $this->zdb->execute($select);
        foreach ($results as $result) {
            $this->activities[$result[Activity::PK]] = [
                'activity'  => new Activity(
                    $this->zdb,
                    $this->login,
                    (int)$result[Activity::PK]
                ),
                'status'    => $result['status']
            ];
        }
    }


    /**
     * Get linked activities
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActivities(): array
    {
        return $this->activities;
    }

    /**
     * Get comment
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Get color
     */
    public function getColor(): string
    {
        return $this->color ?? '';
    }

    /**
     * Count attendees per event
     */
    public function countAttendees(): ResultSet
    {
        $select = $this->zdb->select(EVENTS_PREFIX . Booking::TABLE, 'b');
        $select->columns(
            [
                'count' => new Expression('SUM(b.number_people)'),
                'is_paid'
            ]
        );
        $select->where([
            self::PK    => $this->id,
        ]);

        $select->group('is_paid');

        $results = $this->zdb->execute($select);

        return $results;
    }

    /**
     * Can member edit event
     *
     * @param Login $login Login instance
     */
    public function canEdit(Login $login): bool
    {
        if ($login->isAdmin() || $login->isStaff()) {
            return true;
        }

        if (!$login->isGroupManager()) {
            return false;
        }

        return $this->group !== null && $login->isGroupManager($this->group);
    }

    /**
     * Can memebr create an event
     *
     * @param Login $login Login instance
     */
    public function canCreate(Login $login): bool
    {
        return ($login->isAdmin() || $login->isStaff() || $login->isGroupManager());
    }

    /**
     * Get foreground contrasted color for current background color
     */
    public function getForegoundColor(): string
    {
        $bgcolor = trim($this->color ?? '#ffffff', '#');
        $r = hexdec(substr($bgcolor, 0, 2));
        $g = hexdec(substr($bgcolor, 2, 2));
        $b = hexdec(substr($bgcolor, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return ($yiq >= 128) ? 'black' : 'white';
    }
}
