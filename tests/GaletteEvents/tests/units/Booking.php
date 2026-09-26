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
}
