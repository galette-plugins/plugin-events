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
}
