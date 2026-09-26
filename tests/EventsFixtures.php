<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\tests;

use Galette\Entity\Adherent;
use Galette\Entity\Group;
use GaletteEvents\Activity;
use GaletteEvents\Booking;
use GaletteEvents\Event;

/**
 * Events, bookings and groups for plugin tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait EventsFixtures
{
    /**
     * Remove plugin data
     */
    protected function cleanEvents(): void
    {
        foreach (['activitiesbookings', 'activitiesevents', Booking::TABLE, Event::TABLE, Activity::TABLE] as $table) {
            $this->zdb->execute($this->zdb->delete(EVENTS_PREFIX . $table));
        }
    }

    /**
     * Log in given member
     *
     * @param array<string,mixed> $mdata Member data
     */
    protected function logMember(array $mdata): void
    {
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
    }

    /**
     * Create a group
     *
     * @param string     $name     Group name
     * @param Adherent[] $managers Group managers
     * @param Adherent[] $members  Group members
     */
    protected function createGroup(string $name, array $managers = [], array $members = []): Group
    {
        $group = new Group();
        $group->setName($name);
        $this->assertTrue($group->store());
        if (count($managers)) {
            $this->assertTrue($group->setManagers($managers));
        }
        if (count($members)) {
            $this->assertTrue($group->setMembers($members));
        }
        return $group;
    }

    /**
     * Insert an event, open and in the future by default
     *
     * @param string              $name Event name
     * @param array<string,mixed> $data Values to override
     *
     * @return int Event ID
     */
    protected function insertEvent(string $name, array $data = []): int
    {
        $values = $data + [
            'name'          => $name,
            'town'          => 'Lille',
            'begin_date'    => date('Y-m-d', strtotime('+10 days')),
            'end_date'      => date('Y-m-d', strtotime('+11 days')),
            'creation_date' => date('Y-m-d'),
            'is_open'       => true,
            'id_group'      => null,
            'comment'       => '',
        ];
        if (!$this->zdb->isPostgres()) {
            $values['is_open'] = (int)$values['is_open'];
        } else {
            $values['is_open'] = $values['is_open'] ? 'true' : 'false';
        }
        $insert = $this->zdb->insert(EVENTS_PREFIX . Event::TABLE);
        $insert->values($values);
        $this->zdb->execute($insert);

        return $this->getIdByName(Event::TABLE, Event::PK, $name);
    }

    /**
     * Insert an activity
     *
     * @param string $name Activity name
     *
     * @return int Activity ID
     */
    protected function insertActivity(string $name): int
    {
        $insert = $this->zdb->insert(EVENTS_PREFIX . Activity::TABLE);
        $insert->values([
            'name'          => $name,
            'creation_date' => date('Y-m-d'),
            'comment'       => '',
        ]);
        $this->zdb->execute($insert);

        return $this->getIdByName(Activity::TABLE, Activity::PK, $name);
    }

    /**
     * Link an activity to an event
     *
     * @param int $event    Event ID
     * @param int $activity Activity ID
     * @param int $status   One of Activity::YES or Activity::REQUIRED
     */
    protected function linkActivity(int $event, int $activity, int $status = Activity::YES): void
    {
        $insert = $this->zdb->insert(EVENTS_PREFIX . 'activitiesevents');
        $insert->values([
            Event::PK       => $event,
            Activity::PK    => $activity,
            'status'        => $status,
        ]);
        $this->zdb->execute($insert);
    }

    /**
     * Insert a booking
     *
     * @param int                 $event  Event ID
     * @param int                 $member Member ID
     * @param array<string,mixed> $data   Values to override
     *
     * @return int Booking ID
     */
    protected function insertBooking(int $event, int $member, array $data = []): int
    {
        $insert = $this->zdb->insert(EVENTS_PREFIX . Booking::TABLE);
        $insert->values($data + [
            Event::PK       => $event,
            Adherent::PK    => $member,
            'booking_date'  => date('Y-m-d'),
            'number_people' => 1,
            'creation_date' => date('Y-m-d'),
            'comment'       => '',
        ]);
        $this->zdb->execute($insert);

        $select = $this->zdb->select(EVENTS_PREFIX . Booking::TABLE);
        $select->where([Event::PK => $event, Adherent::PK => $member]);
        return (int)$this->zdb->execute($select)->current()[Booking::PK];
    }

    /**
     * Get a booking stored values
     *
     * @param int $id Booking ID
     *
     * @return array<string,mixed>
     */
    protected function getBookingRow(int $id): array
    {
        $select = $this->zdb->select(EVENTS_PREFIX . Booking::TABLE);
        $select->where([Booking::PK => $id]);
        return (array)$this->zdb->execute($select)->current();
    }

    /**
     * Count bookings of an event
     *
     * @param int $event Event ID
     */
    protected function countBookings(int $event): int
    {
        $select = $this->zdb->select(EVENTS_PREFIX . Booking::TABLE);
        $select->where([Event::PK => $event]);
        return $this->zdb->execute($select)->count();
    }

    /**
     * Get an ID from a name
     *
     * @param string $table Table name, without prefixes
     * @param string $pk    Primary key
     * @param string $name  Name
     */
    private function getIdByName(string $table, string $pk, string $name): int
    {
        $select = $this->zdb->select(EVENTS_PREFIX . $table);
        $select->where(['name' => $name]);
        return (int)$this->zdb->execute($select)->current()[$pk];
    }
}
