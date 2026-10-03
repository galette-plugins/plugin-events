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
     * New bookings are fully initialized
     */
    public function testEmpty(): void
    {
        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history);
        $this->assertNull($booking->getId());
        $this->assertNull($booking->getEventId());
        $this->assertNull($booking->getEvent());
        $this->assertNull($booking->getMemberId());
        $this->assertNull($booking->getMember()->id);
        $this->assertSame(date('Y-m-d'), $booking->getDate());
        $this->assertSame('', $booking->getCreationDate());
        $this->assertSame([], $booking->getActivities());
        $this->assertSame([], $booking->getErrors());
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

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history, $id);
        $this->assertSame('', $booking->getComment());
        $this->assertNull($booking->getAmount());
        $this->assertSame(1, $booking->getNumberPeople());
        $this->assertSame('2026-09-01', $booking->getCreationDate());
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
        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history);
        $this->assertTrue($booking->check($values + ['activities' => [(string)$dinner]]));
        $booking->store();
        $id = (int)$booking->getId();
        $this->assertSame([$dinner => true, $lodging => false], $this->getBookingActivities($id));

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history, $id);
        $this->assertTrue($booking->check($values + ['activities' => [(string)$lodging]]));
        $booking->store();
        $this->assertSame([$dinner => false, $lodging => true], $this->getBookingActivities($id));

        //activity removed from event is removed from booking
        $delete = $this->zdb->delete(EVENTS_PREFIX . 'activitiesevents');
        $delete->where([\GaletteEvents\Event::PK => $event, \GaletteEvents\Activity::PK => $dinner]);
        $this->zdb->execute($delete);
        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history, $id);
        $this->assertTrue($booking->check($values + ['activities' => [(string)$lodging]]));
        $booking->store();
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

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history);
        $this->assertTrue($booking->check($values + ['amount' => '12,50']));
        $this->assertSame(12.5, $booking->getAmount());
        $booking->store();
        $id = (int)$booking->getId();

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history, $id);
        $this->assertTrue($booking->check($values + ['amount' => '']));
        $booking->store();
        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history, $id);
        $this->assertNull($booking->getAmount());

        $this->assertTrue($booking->check($values + ['amount' => '0', 'paid' => '1']));
        $this->assertSame(0.0, $booking->getAmount());

        $this->assertFalse($booking->check($values + ['amount' => '', 'paid' => '1']));
        $this->assertSame([_T('Please specify amount if booking has been paid ;)', 'events')], $booking->getErrors());
        $this->assertFalse($booking->check($values + ['amount' => 'ten']));
        $this->assertSame([_T('Amount must be a number.', 'events')], $booking->getErrors());
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been threw attempting to edit/store a booking');
    }

    /**
     * Get values posted from booking form
     *
     * @param array<string,mixed> $values Values to override
     *
     * @return array<string,mixed>
     */
    private function getFormValues(array $values = []): array
    {
        return $values + [
            'booking_date'  => date('Y-m-d'),
            'number_people' => '1',
            'comment'       => '',
        ];
    }

    /**
     * Posted values are checked
     */
    public function testCheck(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $event = $this->insertEvent('Event');
        $this->insertBooking($event, $member_one->id);

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history);
        $this->assertFalse($booking->check(['number_people' => '1']));
        $this->assertSame(['Event is mandatory', 'Member is mandatory', 'Booking date is mandatory!'], $booking->getErrors());
        $this->assertFalse($booking->check($this->getFormValues([
            'event'         => (string)$event,
            'member'        => (string)$member_one->id,
            'number_people' => '0',
            'booking_date'  => 'today',
        ])));
        $this->assertSame(['There must be at least one person', '- Wrong date format (Y-m-d) for booking date!'], $booking->getErrors());
        $this->assertFalse($booking->check($this->getFormValues(['event' => (string)$event, 'member' => (string)$member_one->id])));
        $this->assertSame([sprintf('A booking already exists for %1$s in %2$s', $member_one->sfullname, 'Event')], $booking->getErrors());
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been threw attempting to edit/store a booking');
    }

    /**
     * Members cannot set financial information
     */
    public function testMemberFinancialValues(): void
    {
        $member_one = $this->getMemberOne();
        $event = $this->insertEvent('Event');
        $this->logMember($this->dataAdherentOne());

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history);
        $this->assertTrue($booking->check($this->getFormValues([
            'event'             => (string)$event,
            'paid'              => '1',
            'amount'            => '10',
            'bank_name'         => 'Bank',
            'check_number'      => '123',
        ])));
        $this->assertFalse($booking->isPaid());
        $this->assertNull($booking->getAmount());
        $this->assertNull($booking->getBankName());
        $this->assertNull($booking->getCheckNumber());
        $this->assertSame($member_one->id, $booking->getMemberId());
    }

    /**
     * Bookings are removed with their activities
     */
    public function testRemove(): void
    {
        $member_one = $this->getMemberOne();
        $event = $this->insertEvent('Event');
        $dinner = $this->insertActivity('Dinner');
        $this->linkActivity($event, $dinner);
        $id = $this->insertBooking($event, $member_one->id);
        $insert = $this->zdb->insert(EVENTS_PREFIX . 'activitiesbookings');
        $insert->values([\GaletteEvents\Activity::PK => $dinner, \GaletteEvents\Booking::PK => $id]);
        $this->zdb->execute($insert);

        $booking = new \GaletteEvents\Booking($this->zdb, $this->login, $this->history, $id);
        $booking->remove();
        $this->assertSame(0, $this->countBookings($event));
        $this->assertSame([], $this->getBookingActivities($id));
    }
}
