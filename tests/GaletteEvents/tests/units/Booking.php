<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\tests\units;

use Galette\Tests\GaletteTestCase;
use GaletteEvents\tests\EventsFixtures;

/**
 * Booking entity tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Booking extends GaletteTestCase
{
    use EventsFixtures;

    protected int $seed = 20260926151512;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        $this->cleanEvents();
        parent::tearDown();
    }

    /**
     * Optional values may be NULL in database
     */
    public function testLoadNullValues(): void
    {
        $member_one = $this->getMemberOne();
        $id = $this->insertBooking(
            $this->insertEvent('Event'),
            $member_one->id,
            ['comment' => null, 'payment_amount' => null, 'number_people' => null, 'creation_date' => '2026-09-01']
        );

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $id);
        $this->assertSame('', $booking->getComment());
        $this->assertNull($booking->getAmount());
        $this->assertSame(1, $booking->getNumberPeople());
        $this->assertSame('2026-09-01', $booking->getCreationDate(false));
    }

    /**
     * Activities chosen on a booking are stored and changed
     */
    public function testActivitiesSync(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $event = $this->insertEvent('Event');
        $dinner = $this->insertActivity('Dinner');
        $lodging = $this->insertActivity('Lodging');
        $this->linkActivity($event, $dinner);
        $this->linkActivity($event, $lodging);

        $values = [
            'event'         => (string)$event,
            'member'        => (string)$member_one->id,
            'booking_date'  => date('Y-m-d'),
            'number_people' => '1',
        ];
        $booking = new \GaletteEvents\Booking($this->zdb, $this->login);
        $this->assertTrue($booking->check($values + ['activities' => [(string)$dinner]]));
        $this->assertTrue($booking->store());
        $id = (int)$booking->getId();
        $this->assertSame([$dinner => true, $lodging => false], $this->getBookingActivities($id));

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $id);
        $this->assertTrue($booking->check($values + ['activities' => [(string)$lodging]]));
        $this->assertTrue($booking->store());
        $this->assertSame([$dinner => false, $lodging => true], $this->getBookingActivities($id));

        //activity removed from event is removed from booking
        $delete = $this->zdb->delete(EVENTS_PREFIX . 'activitiesevents');
        $delete->where([\GaletteEvents\Event::PK => $event, \GaletteEvents\Activity::PK => $dinner]);
        $this->zdb->execute($delete);
        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $id);
        $this->assertTrue($booking->check($values + ['activities' => [(string)$lodging]]));
        $this->assertTrue($booking->store());
        $this->assertSame([$lodging => true], $this->getBookingActivities($id));
    }

    /**
     * Amounts are cleared, use comma as decimal separator, and may be zero once paid
     */
    public function testAmount(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $values = [
            'event'         => (string)$this->insertEvent('Event'),
            'member'        => (string)$member_one->id,
            'booking_date'  => date('Y-m-d'),
            'number_people' => '1',
        ];

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login);
        $this->assertTrue($booking->check($values + ['amount' => '12,50']));
        $this->assertSame(12.5, $booking->getAmount());
        $this->assertTrue($booking->store());
        $id = (int)$booking->getId();

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $id);
        $this->assertTrue($booking->check($values + ['amount' => '']));
        $this->assertTrue($booking->store());
        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $id);
        $this->assertNull($booking->getAmount());

        $this->assertTrue($booking->check($values + ['amount' => '0', 'paid' => '1']));
        $this->assertSame(0.0, $booking->getAmount());

        $this->assertSame(
            [_T('Please specify amount if booking has been paid ;)', 'events')],
            $booking->check($values + ['amount' => '', 'paid' => '1'])
        );
        $this->assertSame(
            [_T('Amount must be a number.', 'events')],
            $booking->check($values + ['amount' => 'ten'])
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been threw attempting to edit/store a booking');
    }
}
