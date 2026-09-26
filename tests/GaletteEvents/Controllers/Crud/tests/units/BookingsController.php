<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\Crud\tests\units;

use Analog\Analog;
use Galette\Tests\GaletteRoutingTestCase;
use GaletteEvents\tests\EventsFixtures;

/**
 * Bookings controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class BookingsController extends GaletteRoutingTestCase
{
    use EventsFixtures;

    protected int $seed = 20260926101512;
    protected bool $load_plugins = true;

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
     * Get booking form
     *
     * @param int $id Booking ID
     */
    private function getBookingForm(int $id): \Psr\Http\Message\ResponseInterface
    {
        return $this->app->handle($this->createRequest('events_booking_edit', ['id' => (string)$id]));
    }

    /**
     * Post a booking
     *
     * @param ?int                 $id   Booking ID, null to add a new one
     * @param array<string,string> $data Posted data
     */
    private function postBooking(?int $id, array $data): \Psr\Http\Message\ResponseInterface
    {
        if ($id === null) {
            $request = $this->createRequest('events_storebooking_add', [], 'POST');
        } else {
            $request = $this->createRequest('events_storebooking_edit', ['id' => (string)$id], 'POST');
            $data += ['id' => (string)$id];
        }
        return $this->app->handle($request->withParsedBody($data + [
            'booking_date'  => date('Y-m-d'),
            'number_people' => '1',
            'comment'       => '',
            'save'          => '1',
        ]));
    }

    /**
     * Assert access to a booking has been refused
     *
     * @param \Psr\Http\Message\ResponseInterface $test_response Response
     * @param int                                 $id            Booking ID
     */
    private function expectBookingRefused(\Psr\Http\Message\ResponseInterface $test_response, int $id): void
    {
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('events_bookings', ['event' => 'all'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        //message comes in the language of the logged-in member
        $this->expectFlashData(['error_detected' => [_T('You do not have permission for requested URL.')]]);
        $this->expectLogEntry(Analog::WARNING, 'has tried to edit booking #' . $id);
        $this->expectNoLogEntry();
    }

    /**
     * Assert booking form is displayed
     *
     * @param \Psr\Http\Message\ResponseInterface $test_response Response
     */
    private function expectBookingForm(\Psr\Http\Message\ResponseInterface $test_response): void
    {
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('name="save"', (string)$test_response->getBody());
        $this->expectNoLogEntry();
    }

    /**
     * Visitors cannot list bookings
     */
    public function testVisitorCannotListBookings(): void
    {
        $member_one = $this->getMemberOne();
        $this->insertBooking($this->insertEvent('Public event'), $member_one->id);

        foreach (['all', 'guess'] as $event) {
            $request = $this->createRequest('events_bookings', ['event' => $event]);
            $this->expectLogin($this->app->handle($request));
        }
    }

    /**
     * Members can neither display nor change bookings of other members
     */
    public function testMemberCannotEditOtherMemberBooking(): void
    {
        //member two speaks Catalan, member one gets messages in English
        $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $event = $this->insertEvent('Public event');
        $booking = $this->insertBooking($event, $member_two->id, ['comment' => 'Vegetarian']);

        $this->logMember($this->dataAdherentOne());
        $this->expectBookingRefused($this->getBookingForm($booking), $booking);
        $this->expectBookingRefused(
            $this->postBooking(
                $booking,
                [
                    'event'         => (string)$event,
                    'member'        => (string)$member_two->id,
                    'number_people' => '5',
                    'comment'       => 'Changed',
                ]
            ),
            $booking
        );

        $row = $this->getBookingRow($booking);
        $this->assertSame('Vegetarian', $row['comment']);
        $this->assertSame(1, (int)$row['number_people']);
    }

    /**
     * Members display and change their own bookings
     */
    public function testMemberEditsOwnBooking(): void
    {
        $member_one = $this->getMemberOne();
        $event = $this->insertEvent('Public event');
        $booking = $this->insertBooking($event, $member_one->id);

        $this->logMember($this->dataAdherentOne());
        $this->expectBookingForm($this->getBookingForm($booking));

        $test_response = $this->postBooking($booking, ['event' => (string)$event, 'comment' => 'Changed']);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('events_bookings', ['event' => (string)$event])]],
            $test_response->getHeaders()
        );
        $this->expectFlashData(['success_detected' => ['Booking has been modified.']]);
        $this->assertSame('Changed', $this->getBookingRow($booking)['comment']);
    }

    /**
     * Group managers display bookings on events of the groups they manage only
     */
    public function testManagerEditsBookingsOfManagedGroupsOnly(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $managed = $this->createGroup('Managed group', [$member_two], [$member_one]);
        //member two belongs to this one, but does not manage it
        $other = $this->createGroup('Other group', [], [$member_one, $member_two]);

        $managed_booking = $this->insertBooking(
            $this->insertEvent('Managed event', ['id_group' => $managed->getId()]),
            $member_one->id
        );
        $other_booking = $this->insertBooking(
            $this->insertEvent('Other event', ['id_group' => $other->getId()]),
            $member_one->id
        );
        $public_booking = $this->insertBooking($this->insertEvent('Public event'), $member_one->id);

        $this->logMember($this->dataAdherentTwo());
        $this->expectBookingForm($this->getBookingForm($managed_booking));
        $this->expectBookingRefused($this->getBookingForm($other_booking), $other_booking);
        $this->expectBookingRefused($this->getBookingForm($public_booking), $public_booking);
    }

    /**
     * Staff members display any booking
     */
    public function testStaffEditsAnyBooking(): void
    {
        $staff = $this->getStaffMember($this->getMemberOne());
        $member_two = $this->getMemberTwo();
        $group = $this->createGroup('Group', [], [$member_two]);
        $booking = $this->insertBooking(
            $this->insertEvent('Group event', ['id_group' => $group->getId()]),
            $member_two->id
        );

        $this->logMember($this->dataAdherentOne());
        $this->assertTrue($this->login->isStaff());
        $this->expectBookingForm($this->getBookingForm($booking));
        $this->resetStaffStatus($staff, $this->getMemberTwo());
    }
}
