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
use Galette\Entity\Adherent;
use Galette\Entity\PaymentType;
use Analog\Analog;

/**
 * Booking entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Booking
{
    public const string TABLE = 'bookings';
    public const string PK = 'id_booking';

    private Db $zdb;
    private Login $login;
    /** @var array<string> */
    private array $errors;

    private int $id;
    private int $event;
    private int $member;
    private string $date = '';
    private bool $paid = false;
    private ?float $amount = null;
    private int $payment_method = PaymentType::OTHER;
    private ?string $bank_name = null;
    private ?string $check_number = null;
    private int $number_people = 1;
    private string $comment = '';

    /** @var array<int, array<string,mixed>> */
    private array $activities = [];
    /** @var array<int, array<string,mixed>> */
    private array $activities_removed = [];
    private string $creation_date;

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
        if (is_int($args)) {
            $this->load($args);
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
                'Cannot load booking form id `' . $id . '` | ' . $e->getMessage(),
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
        $this->id = (int)$r->id_booking;
        $this->event = (int)$r->id_event;
        $this->member = (int)$r->id_adh;
        $this->date = $r->booking_date;
        $this->paid = (bool)$r->is_paid;
        $this->amount = (float)$r->payment_amount;
        $this->payment_method = (int)$r->payment_method;
        $this->bank_name = $r->bank_name;
        $this->check_number = $r->check_number;
        $this->number_people = (int)$r->number_people;
        $this->comment = $r->comment;
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
                'Unable to delete booking '
                . ' (' . $this->id . ') |' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Check posted values validity
     *
     * @param array<string,mixed> $values All values to check, basically the $_POST array
     *                                    after sending the form
     *
     * @return true|array<string>
     */
    public function check(array $values): array|bool
    {
        $this->errors = [];

        //event and activities
        if (!isset($values['event']) || empty($values['event']) || $values['event'] == -1) {
            $this->errors[] = _T('Event is mandatory', 'events');
        } else {
            $this->event = (int)$values['event'];
            $event = $this->getEvent();
            $activities = $event->getActivities();
            foreach ($activities as $aid => $entry) {
                if (
                    $event->isActivityRequired($aid)
                    && (!isset($values['activities']) || !in_array($aid, $values['activities']))
                ) {
                    $this->errors[] = sprintf(
                        //TRANS: %1$s is activity name
                        _T('%1$s is mandatory for this event!', 'events'),
                        $entry['activity']->getName()
                    );
                } else {
                    $act = [
                        'activity'  => $entry['activity'],
                        'checked'   => (isset($values['activities']) && in_array($aid, $values['activities']))
                    ];
                    $this->activities[$aid] = $act;
                }
            }
            foreach (array_keys($this->activities) as $aid) {
                if (!isset($activities[$aid])) {
                    $this->activities_removed[$aid] = [
                        Activity::PK    => $aid,
                        self::PK        => $this->id
                    ];
                    unset($this->activities[$aid]);
                }
            }
        }

        //financial information
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            if (isset($values['paid'])) {
                $this->paid = true;
            } else {
                $this->paid = false;
            }

            if (isset($values['amount']) && !empty($values['amount'])) {
                $this->amount = (float)$values['amount'];
            }

            if ($this->paid && !$this->amount) {
                $this->errors[] = _T('Please specify amount if booking has been paid ;)', 'events');
            }

            if (isset($values['payment_method'])) {
                $this->payment_method = (int)$values['payment_method'];
            }

            if (isset($values['bank_name'])) {
                $this->bank_name = $values['bank_name'];
            }

            if (isset($values['check_number'])) {
                $this->check_number = $values['check_number'];
            }
        }

        //booking information
        if (!isset($values['member']) || empty($values['member'])) {
            if (
                $this->login->isAdmin()
                || $this->login->isStaff()
                || $this->login->isGroupManager()
            ) {
                $this->errors[] = _T('Member is mandatory', 'events');
            } else {
                $this->member = $this->login->id;
            }
        } else {
            $this->member = (int)$values['member'];
        }

        if (isset($values['number_people'])) {
            if ((int)$values['number_people'] > 0) {
                $this->number_people = (int)$values['number_people'];
            } else {
                $this->errors[] = _T('There must be at least one person', 'events');
            }
        }

        if (isset($values['comment'])) {
            $this->comment = $values['comment'];
        }

        if (!isset($values['booking_date']) || empty($values['booking_date'])) {
            $this->errors[] = _T('Booking date is mandatory!', 'events');
        } else {
            $value = $values['booking_date'];
            try {
                $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                if ($d === false) {
                    //try with non localized date
                    $d = \DateTime::createFromFormat("Y-m-d", $value);
                    if ($d === false) {
                        throw new \Exception('Incorrect format');
                    }
                }
                $this->date = $d->format('Y-m-d');
            } catch (\Exception $e) {
                Analog::log(
                    'Wrong date format. field: booking_date'
                    . ', value: ' . $value . ', expected fmt: '
                    . __("Y-m-d") . ' | ' . $e->getMessage(),
                    Analog::INFO
                );
                $this->errors[] = sprintf(
                    //TRANS %1$s is the expected date format, %2$s is the field label
                    _T('- Wrong date format (%1$s) for %2$s!'),
                    __("Y-m-d"),
                    __('booking date', 'events')
                );
            }
        }

        if (count($this->errors) == 0) {
            //check uniqueness
            $select = $this->zdb->select($this->getTableName());
            $select->where([
                Event::PK       => $this->event,
                Adherent::PK    => $this->member
            ]);
            if (isset($this->id)) {
                $select->where->notEqualTo(
                    self::PK,
                    $this->id
                );
            }
            $results = $this->zdb->execute($select);
            if ($results->count()) {
                $this->errors[] = sprintf(
                    //TRANS: first replacement is member name, second is event name
                    _T('A booking already exists for %1$s in %2$s', 'events'),
                    $this->getMember()->sfullname,
                    $this->getEvent()->getName()
                );
            }
        }

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been threw attempting to edit/store a booking' . "\n"
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
     * Store the booking
     */
    public function store(): bool
    {
        global $hist;

        try {
            $this->zdb->connection->beginTransaction();
            $values = [
                Event::PK           => $this->event,
                Adherent::PK        => $this->member,
                'booking_date'      => $this->date,
                'is_paid'           => ($this->paid ? $this->paid
                                            : ($this->zdb->isPostgres() ? 'false' : 0)),
                'payment_method'    => $this->payment_method,
                'payment_amount'    => $this->amount,
                'bank_name'         => $this->bank_name,
                'check_number'      => $this->check_number,
                'number_people'     => $this->number_people,
                'comment'           => $this->comment
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
                            PREFIX_DB . EVENTS_PREFIX . Booking::TABLE . '_id_seq'
                        );
                    } else {
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue();
                    }

                    // logging
                    $hist->add(
                        _T("Booking added", "events"),
                        $this->getEvent()->getName()
                    );
                } else {
                    $hist->add(_T("Fail to add new booking.", "events"));
                    throw new \Exception(
                        'An error occurred inserting new booking!'
                    );
                }
            } else {
                //we're editing an existing booking
                $values[self::PK] = $this->id;
                $update = $this->zdb->update($this->getTableName());
                $update
                    ->set($values)
                    ->where([self::PK => $this->id]);

                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $hist->add(
                        _T("Booking updated", "events")
                    );
                }
            }

            //store booking activities
            $void   = [];
            $update = [];
            $insert = [];
            $delete = $this->activities_removed;

            foreach ($this->activities as $aid => $data) {
                $activity = $data['activity'];
                $checked = $data['checked'];
                $key_values = [
                    self::PK        => $this->id,
                    $activity::PK   => $activity->getId()
                ];

                $select = $this->zdb->select(EVENTS_PREFIX . 'activitiesbookings', 'acb');
                $select->where($key_values);
                $results = $this->zdb->execute($select);

                foreach ($results as $result) {
                    if (!isset($this->activities[$result[Activity::PK]])) {
                        $delete[$result[Activity::PK]] = [
                            Activity::PK    => $result[Activity::PK],
                            self::PK        => $this->id,
                        ];
                    } elseif ($result['checked'] != $this->activities[$result[Activity::PK]]['checked']) {
                        $update[$result[Activity::PK]] = [
                            'checked'   => ($checked ? $checked
                                            : ($this->zdb->isPostgres() ? 'false' : 0))
                        ];
                    } else {
                        $void[$result[Activity::PK]] = true;
                    }
                }

                if (!isset($void[$aid]) && !isset($update[$aid]) && !isset($delete[$aid])) {
                    $insert[$aid] = [
                        Activity::PK    => $aid,
                        self::PK        => $this->id,
                        'checked'       => ($checked ? $checked
                                            : ($this->zdb->isPostgres() ? 'false' : 0))
                    ];
                }
            }

            if (count($delete)) {
                $prepare = $this->zdb->delete(EVENTS_PREFIX . 'activitiesbookings');
                $prepare->where([
                    self::PK        => $this->id,
                    Activity::PK    => ':aid'
                ]);
                $stmt = $this->zdb->sql->prepareStatementForSqlObject($prepare);

                $count = 0;
                foreach ($delete as $values) {
                    $stmt->execute([':aid' => $values[Activity::PK]]);
                    ++$count;
                }
                Analog::log(
                    sprintf('%1$s activities removed', $count),
                    Analog::INFO
                );
            }

            if (count($update)) {
                $prepare = $this->zdb->update(EVENTS_PREFIX . 'activitiesbookings');
                $prepare->set([
                    'checked'       => ':checked'
                ])->where([
                    self::PK        => $this->id,
                    Activity::PK    => ':aid'
                ]);
                $stmt = $this->zdb->sql->prepareStatementForSqlObject($prepare);
                $count = 0;
                foreach ($update as $aid => $values) {
                    $params = [
                        'where2'    => $aid,
                        ':checked'  => $values['checked']
                    ];
                    $stmt->execute($params);
                    ++$count;
                }
                Analog::log(
                    sprintf('%1$s activities updated', $count),
                    Analog::INFO
                );
            }

            if (count($insert)) {
                $prepare = $this->zdb->insert(EVENTS_PREFIX . 'activitiesbookings');
                $prepare->values([
                    self::PK        => ':id',
                    Activity::PK    => ':aid',
                    'checked'       => ':checked'
                ]);
                $stmt = $this->zdb->sql->prepareStatementForSqlObject($prepare);
                $count = 0;
                foreach ($insert as $aid => $values) {
                    $params = [
                        $this->id,
                        $aid,
                        $values['checked']
                    ];
                    $stmt->execute($params);
                    ++$count;
                }
                Analog::log(
                    sprintf('%1$s activities added', $count),
                    Analog::INFO
                );
            }

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
     * Get event id
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get event id
     */
    public function getEventId(): ?int
    {
        return $this->event ?? null;
    }

    /**
     * Get event
     */
    public function getEvent(): ?Event
    {
        if (isset($this->event)) {
            return new Event($this->zdb, $this->login, $this->event);
        }
        return null;
    }

    /**
     * Get member id
     */
    public function getMemberId(): ?int
    {
        return $this->member ?? null;
    }

    /**
     * Get member
     */
    public function getMember(): Adherent
    {
        return new Adherent($this->zdb, $this->member);
    }

    /**
     * Get date
     *
     * @param bool $formatted Return date formatted, raw if false
     */
    public function getDate(bool $formatted = true): string
    {
        if ($formatted === true) {
            $date = new \DateTime($this->date);
            return $date->format(__("Y-m-d"));
        } else {
            return $this->date;
        }
    }

    /**
     * Is booking paid?
     */
    public function isPaid(): bool
    {
        return $this->paid;
    }

    /**
     * Get amount
     */
    public function getAmount(): ?float
    {
        return $this->amount;
    }

    /**
     * Get payment method
     */
    public function getPaymentMethod(): int
    {
        return $this->payment_method;
    }

    /**
     * Get payment method name
     */
    public function getPaymentMethodName(): string
    {
        $pt = new PaymentType($this->zdb, (int)$this->payment_method);
        return $pt->getname();
    }

    /**
     * Get bank name
     */
    public function getBankName(): ?string
    {
        return $this->bank_name;
    }

    /**
     * Get check number
     */
    public function getCheckNumber(): ?string
    {
        return $this->check_number;
    }

    /**
     * Get number of persons
     */
    public function getNumberPeople(): int
    {
        return $this->number_people;
    }

    /**
     * Get creation date
     *
     * @param bool $formatted Return date formatted, raw if false
     */
    public function getCreationDate(bool $formatted = true): string
    {
        if ($formatted === true) {
            $date = new \DateTime($this->creation_date);
            return $date->format(__("Y-m-d"));
        } else {
            return $this->creation_date;
        }
    }

    /**
     * Set event
     *
     * @param int $event Event id
     */
    public function setEvent(int $event): self
    {
        $this->event = $event;
        return $this;
    }

    /**
     * Set member
     *
     * @param int $member Member id
     */
    public function setMember(int $member): self
    {
        $this->member = $member;
        return $this;
    }

    /**
     * Get table's name
     */
    protected function getTableName(): string
    {
        return EVENTS_PREFIX . self::TABLE;
    }

    /**
     * Get comment
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Has Activity
     *
     * @param int $activity Activity
     */
    public function has(int $activity): bool
    {
        return isset($this->activities[$activity]) && $this->activities[$activity]['checked'];
    }

    /**
     * Load linked activities
     */
    public function loadActivities(): void
    {
        $select = $this->zdb->select(EVENTS_PREFIX . 'activitiesbookings', 'acb');
        $select->where([self::PK => $this->id]);
        $results = $this->zdb->execute($select);
        foreach ($results as $result) {
            $this->activities[$result[Activity::PK]] = [
                'activity'  => new Activity(
                    $this->zdb,
                    $this->login,
                    (int)$result[Activity::PK]
                ),
                'checked'    => $result['checked']
            ];
        }
    }

    /**
     * Get activities
     *
     * @return array<int, array<string,mixed>>
     */
    public function getActivities(): array
    {
        return $this->activities;
    }

    /**
     * Can current logged-in user edit booking
     *
     * Admins and staff members can edit any booking, members their own ones,
     * and group managers the ones on events of the groups they manage.
     *
     * @param Login $login Login instance
     */
    public function canEdit(Login $login): bool
    {
        if ($login->isAdmin() || $login->isStaff()) {
            return true;
        }

        if ($this->getMemberId() !== null && $this->getMemberId() === $login->id) {
            return true;
        }

        $group = $this->getEvent()?->getGroup();
        return $group !== null && $login->isGroupManager($group);
    }

    /**
     * Get row class related to current fee status
     *
     * @param bool $public we want the class for public pages
     *
     * @return string the class to apply
     */
    public function getRowClass(bool $public = false): string
    {
        $strclass = 'event-'
            . ($this->isPaid() ? 'paid' : 'notpaid');
        return $strclass;
    }
}
