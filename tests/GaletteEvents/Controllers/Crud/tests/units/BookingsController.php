<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\Crud\tests\units;

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
}
