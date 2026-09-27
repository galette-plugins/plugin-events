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
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Entity\PaymentType;
use Galette\Repository\Groups;
use Analog\Analog;

/**
 * Booking entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Booking
{
    use EntityTrait;

    public const string TABLE = 'bookings';
    public const string PK = 'id_booking';

    private Db $zdb;
    private Login $login;
    private History $history;
    /** @var array<string> */
    private array $errors = [];

    private ?int $id = null;
    private ?int $event = null;
    private ?int $member = null;
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
    private ?string $creation_date = null;

    /**
     * Default constructor
     *
     * @param Db                                  $zdb     Database instance
     * @param Login                               $login   Login instance
     * @param History                             $history History instance
     * @param null|int|ArrayObject<string, mixed> $args    Either a ResultSet row or its id for to load
     *                                                     a specific event, or null to just
     *                                                     instanciate object
     */
    public function __construct(Db $zdb, Login $login, History $history, int|ArrayObject|null $args = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;
        $this->history = $history;
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args !== null) {
            $this->loadFromRS($args);
        } else {
            $this->date = date('Y-m-d');
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, mixed> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = (int)$r['id_booking'];
        $this->event = (int)$r['id_event'];
        $this->member = (int)$r['id_adh'];
        $this->date = $r['booking_date'];
        $this->paid = (bool)$r['is_paid'];
        $this->amount = $r['payment_amount'] === null ? null : (float)$r['payment_amount'];
        $this->payment_method = (int)$r['payment_method'];
        $this->bank_name = $r['bank_name'];
        $this->check_number = $r['check_number'];
        $this->number_people = (int)($r['number_people'] ?? 1);
        $this->comment = $r['comment'] ?? '';
        $this->creation_date = $r['creation_date'];
        $this->loadActivities();
    }

    /**
     * Check posted values validity
     *
     * @param array<string,mixed> $values All values to check, basically the $_POST array
     *                                    after sending the form
     */
    public function check(array $values): bool
    {
        $this->errors = [];

        //event and activities
        if (!isset($values['event']) || empty($values['event']) || $values['event'] == -1) {
            $this->errors[] = _T('Event is mandatory', 'events');
        } else {
            $event_changed = $this->getId() === null || $this->getEventId() !== (int)$values['event'];
            try {
                $event = new Event($this->zdb, $this->login, $this->history, (int)$values['event']);
            } catch (NotFoundException) {
                $event = null;
            }
            if ($event === null || ($event_changed && !$this->canBook($event))) {
                $this->errors[] = _T('This event cannot be booked.', 'events');
            }
            if ($event !== null) {
                $this->event = (int)$values['event'];
                $this->checkActivities($event, $values['activities'] ?? []);
            }
        }

        //financial information
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            if (isset($values['paid'])) {
                $this->paid = true;
            } else {
                $this->paid = false;
            }

            if (isset($values['amount'])) {
                //accept comma as decimal separator
                $amount = strtr(trim((string)$values['amount']), ',', '.');
                if ($amount === '') {
                    $this->amount = null;
                } elseif (is_numeric($amount)) {
                    $this->amount = (float)$amount;
                } else {
                    $this->errors[] = _T('Amount must be a number.', 'events');
                }
            }

            if ($this->paid && $this->amount === null) {
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
        if (!$this->login->isAdmin() && !$this->login->isStaff() && !$this->login->isGroupManager()) {
            //members book for themselves only
            $this->member = $this->login->id;
        } elseif (!isset($values['member']) || empty($values['member'])) {
            $this->errors[] = _T('Member is mandatory', 'events');
        } else {
            $member = (int)$values['member'];
            if (
                !$this->login->isAdmin()
                && !$this->login->isStaff()
                && $member !== $this->login->id
                && $member !== $this->getMemberId()
            ) {
                //group managers book for members of the groups they manage, on events of those groups
                $group = $this->getEvent()?->getGroup();
                if (!(new Adherent($this->zdb, $member))->canShow($this->login)) {
                    $this->errors[] = _T("- Please select a member from a group you manage.");
                } elseif ($group === null || !$this->login->isGroupManager($group)) {
                    $this->errors[] = _T('You can only book other members on events of groups you manage.', 'events');
                }
            }
            $this->member = $member;
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
            $date = $this->parseDate((string)$values['booking_date'], __('booking date', 'events'));
            if ($date !== null) {
                $this->date = $date;
            }
        }

        if (count($this->errors) == 0) {
            //check uniqueness
            $select = $this->zdb->select($this->getTableName());
            $select->where([
                Event::PK       => $this->event,
                Adherent::PK    => $this->member
            ]);
            if ($this->id !== null) {
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
            return false;
        } else {
            Analog::log(
                'Event checked successfully.',
                Analog::DEBUG
            );
            return true;
        }
    }

    /**
     * Check activities of the booking against the ones of its event
     *
     * @param Event        $event   Booked event
     * @param array<mixed> $checked Checked activities identifiers
     */
    private function checkActivities(Event $event, array $checked): void
    {
        $activities = $event->getActivities();
        foreach ($activities as $aid => $entry) {
            if (
                $event->isActivityRequired($aid)
                && !in_array($aid, $checked)
            ) {
                $this->errors[] = sprintf(
                    //TRANS: %1$s is activity name
                    _T('%1$s is mandatory for this event!', 'events'),
                    $entry['activity']->getName()
                );
            } else {
                $act = [
                    'activity'  => $entry['activity'],
                    'checked'   => in_array($aid, $checked)
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

    /**
     * Store the booking
     */
    public function store(): void
    {
        $this->transactional(function (): void {
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

            if ($this->id === null) {
                //we're inserting a new booking
                $this->creation_date = date("Y-m-d");
                $values['creation_date'] = $this->creation_date;

                $insert = $this->zdb->insert($this->getTableName());
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() === 0) {
                    $this->history->add(_T("Fail to add new booking.", "events"));
                    throw new \RuntimeException(
                        'An error occurred inserting new booking!'
                    );
                }
                $this->id = $this->getLastInsertId();

                // logging
                $this->history->add(
                    _T("Booking added", "events"),
                    $this->getEvent()->getName()
                );
            } else {
                //we're editing an existing booking
                $update = $this->zdb->update($this->getTableName());
                $update
                    ->set($values)
                    ->where([self::PK => $this->id]);

                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $this->history->add(
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
        });
    }

    /**
     * Get event id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get event id
     */
    public function getEventId(): ?int
    {
        return $this->event;
    }

    /**
     * Get event
     */
    public function getEvent(): ?Event
    {
        if ($this->event !== null) {
            return new Event($this->zdb, $this->login, $this->history, $this->event);
        }
        return null;
    }

    /**
     * Get member id
     */
    public function getMemberId(): ?int
    {
        return $this->member;
    }

    /**
     * Get member, empty if booking has no member yet
     */
    public function getMember(): Adherent
    {
        return new Adherent($this->zdb, $this->member);
    }

    /**
     * Get booking date, as Y-m-d
     */
    public function getDate(): string
    {
        return $this->date;
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
        //payment method may be missing: 0 is the column default, and types can be removed
        $select = $this->zdb->select(PaymentType::TABLE);
        $select->where([PaymentType::PK => $this->payment_method]);
        if ($this->zdb->execute($select)->count() === 0) {
            return '';
        }
        $pt = new PaymentType($this->zdb, $this->payment_method);
        return $pt->getName();
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
     * Get creation date, as Y-m-d
     */
    public function getCreationDate(): string
    {
        return $this->creation_date ?? '';
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
        $this->activities = [];
        $select = $this->zdb->select(EVENTS_PREFIX . 'activitiesbookings', 'acb');
        $select->where([self::PK => $this->id]);
        $results = $this->zdb->execute($select);
        foreach ($results as $result) {
            $this->activities[$result[Activity::PK]] = [
                'activity'  => new Activity(
                    $this->zdb,
                    $this->history,
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
     * Can current logged-in user book an event
     *
     * Admins and staff members can book any event, others open events
     * that are public or restricted to one of their groups.
     *
     * @param Event $event Event
     */
    private function canBook(Event $event): bool
    {
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            return $event->getId() !== null;
        }

        if ($event->getId() === null || !$event->isOpen()) {
            return false;
        }

        $group = $event->getGroup();
        return $group === null
            || $this->login->isGroupManager($group)
            || in_array($group, array_map('intval', Groups::loadGroups($this->login->id, false, false)), true);
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
     * Get errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
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
