<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Repository\tests\units;

use Galette\Tests\GaletteTestCase;
use GaletteEvents\Booking;
use GaletteEvents\tests\EventsFixtures;

/**
 * Bookings repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Bookings extends GaletteTestCase
{
    use EventsFixtures;

    protected int $seed = 20260926101512;

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
     * Get IDs of bookings current logged-in user can list, and their sum
     *
     * @return array{ids: array<int>, sum: float}
     */
    private function getVisibleBookings(): array
    {
        $bookings = new \GaletteEvents\Repository\Bookings($this->zdb, $this->login);
        $ids = array_map(fn(Booking $booking): ?int => $booking->getId(), $bookings->getList(true));
        sort($ids);
        return ['ids' => $ids, 'sum' => $bookings->getSum()];
    }

    /**
     * Members list their own bookings, group managers the ones on events of groups they manage as well
     */
    public function testListScope(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $managed = $this->createGroup('Managed group', [$member_two], [$member_one]);
        //member two belongs to this one, but does not manage it
        $other = $this->createGroup('Other group', [], [$member_one, $member_two]);

        $managed_event = $this->insertEvent('Managed event', ['id_group' => $managed->getId()]);
        $other_event = $this->insertEvent('Other event', ['id_group' => $other->getId()]);
        $public_event = $this->insertEvent('Public event');

        $one_managed = $this->insertBooking($managed_event, $member_one->id, ['payment_amount' => 1]);
        $one_other = $this->insertBooking($other_event, $member_one->id, ['payment_amount' => 10]);
        $one_public = $this->insertBooking($public_event, $member_one->id, ['payment_amount' => 100]);
        $two_public = $this->insertBooking($public_event, $member_two->id, ['payment_amount' => 1000]);

        $this->logMember($this->dataAdherentOne());
        $this->assertSame(
            ['ids' => [$one_managed, $one_other, $one_public], 'sum' => 111.0],
            $this->getVisibleBookings()
        );
        $this->login->logout();

        $this->logMember($this->dataAdherentTwo());
        $this->assertTrue($this->login->isGroupManager());
        $this->assertSame(
            ['ids' => [$one_managed, $two_public], 'sum' => 1001.0],
            $this->getVisibleBookings()
        );
        $this->login->logout();

        $this->logSuperAdmin();
        $this->assertSame(
            ['ids' => [$one_managed, $one_other, $one_public, $two_public], 'sum' => 1111.0],
            $this->getVisibleBookings()
        );
    }

    /**
     * Listed bookings keep their own values, not the ones of their event
     */
    public function testListKeepsBookingValues(): void
    {
        $member_one = $this->getMemberOne();
        $event = $this->insertEvent('Event', ['comment' => 'Event comment', 'creation_date' => '2026-01-01']);
        $this->insertBooking($event, $member_one->id, ['comment' => 'Booking comment', 'creation_date' => '2026-02-01']);

        $this->logSuperAdmin();
        $list = (new \GaletteEvents\Repository\Bookings($this->zdb, $this->login))->getList();
        $this->assertCount(1, $list);
        $this->assertSame('Booking comment', $list[0]->getComment());
        $this->assertSame('2026-02-01', $list[0]->getCreationDate(false));
    }

    /**
     * Bookings are filtered on payment, event and group, and ordered
     */
    public function testFilters(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $group = $this->createGroup('Group', [], [$member_one]);
        $group_event = $this->insertEvent('Group event', ['id_group' => $group->getId()]);
        $public_event = $this->insertEvent('Public event');
        $paid = $this->zdb->isPostgres() ? 'true' : 1;

        $one_group = $this->insertBooking(
            $group_event,
            $member_one->id,
            ['is_paid' => $paid, 'payment_method' => \Galette\Entity\PaymentType::CASH, 'payment_amount' => 5]
        );
        $one_public = $this->insertBooking($public_event, $member_one->id, ['payment_amount' => 7]);
        $two_public = $this->insertBooking(
            $public_event,
            $member_two->id,
            ['is_paid' => $paid, 'payment_amount' => 11]
        );

        $this->logSuperAdmin();
        $list = function (array $filters): array {
            $bookings_filters = new \GaletteEvents\Filters\BookingsList();
            foreach ($filters as $name => $value) {
                $bookings_filters->$name = $value;
            }
            $bookings = new \GaletteEvents\Repository\Bookings($this->zdb, $this->login, $bookings_filters);
            $ids = array_map(fn(Booking $booking): ?int => $booking->getId(), $bookings->getList());
            return ['ids' => $ids, 'count' => $bookings->getCount(), 'sum' => $bookings->getSum()];
        };

        $by_id = fn(array $result): array => ['ids' => $this->sorted($result['ids'])] + $result;
        $this->assertSame(
            ['ids' => [$one_group, $two_public], 'count' => 2, 'sum' => 16.0],
            $by_id($list(['paid_filter' => \GaletteEvents\Repository\Bookings::FILTER_PAID]))
        );
        $this->assertSame(
            ['ids' => [$one_public], 'count' => 1, 'sum' => 7.0],
            $by_id($list(['paid_filter' => \GaletteEvents\Repository\Bookings::FILTER_NOT_PAID]))
        );
        $this->assertSame(
            ['ids' => [$one_group], 'count' => 1, 'sum' => 5.0],
            $by_id($list(['payment_type_filter' => \Galette\Entity\PaymentType::CASH]))
        );
        $this->assertSame(
            ['ids' => [$one_public, $two_public], 'count' => 2, 'sum' => 18.0],
            $by_id($list(['event_filter' => $public_event]))
        );
        $this->assertSame(
            ['ids' => [$one_group], 'count' => 1, 'sum' => 5.0],
            $by_id($list(['group_filter' => $group->getId()]))
        );

        //order by member name, descending by default
        $ordered = $list(['event_filter' => $public_event, 'orderby' => \GaletteEvents\Repository\Bookings::ORDERBY_MEMBER])['ids'];
        $expected = strcmp($member_one->name . $member_one->surname, $member_two->name . $member_two->surname) > 0
            ? [$one_public, $two_public]
            : [$two_public, $one_public];
        $this->assertSame($expected, $ordered);
    }

    /**
     * Sort IDs
     *
     * @param array<?int> $ids IDs
     *
     * @return array<?int>
     */
    private function sorted(array $ids): array
    {
        sort($ids);
        return $ids;
    }
}
