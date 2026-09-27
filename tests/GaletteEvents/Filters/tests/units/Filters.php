<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Filters\tests\units;

use Analog\Analog;
use Galette\Tests\GaletteTestCase;
use GaletteEvents\Filters\ActivitiesList;
use GaletteEvents\Filters\BookingsList;
use GaletteEvents\Filters\EventsList;
use GaletteEvents\Repository\Bookings;

/**
 * Lists filters tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Filters extends GaletteTestCase
{
    protected bool $db_transactions = false;

    /**
     * Only known properties are read and written
     */
    public function testWhitelist(): void
    {
        $filters = new BookingsList();
        $this->assertTrue($filters->__isset('event_filter'));
        $this->assertTrue($filters->__isset('show'));
        $this->assertFalse($filters->__isset('query'));

        foreach ([new BookingsList(), new EventsList(), new ActivitiesList()] as $filters) {
            try {
                $filters->__set('list_fields', []);
                $this->fail('Unknown property must not be set on ' . $filters::class);
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Unable to set property', $e->getMessage());
            }
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to get property');
        (new EventsList())->__get('query');
    }

    /**
     * Bookings filters are typed, invalid values are ignored
     */
    public function testBookingsValues(): void
    {
        $filters = new BookingsList();
        $this->assertNull($filters->event_filter);

        $filters->event_filter = '12';
        $this->assertSame(12, $filters->event_filter);
        $filters->event_filter = 'all';
        $this->assertNull($filters->event_filter);
        $filters->group_filter = '3';
        $this->assertSame(3, $filters->group_filter);
        $filters->group_filter = '0';
        $this->assertNull($filters->group_filter);

        $filters->paid_filter = (string)Bookings::FILTER_PAID;
        $this->assertSame(Bookings::FILTER_PAID, $filters->paid_filter);
        $filters->payment_type_filter = '2';
        $this->assertSame(2, $filters->payment_type_filter);
        $filters->selected = ['4', '5'];
        $this->assertSame([4, 5], $filters->selected);

        $filters->event_filter = 'not a number';
        $this->assertNull($filters->event_filter);
        $filters->paid_filter = 42;
        $this->assertSame(Bookings::FILTER_PAID, $filters->paid_filter);
        $this->expectLogEntry(Analog::WARNING, 'Invalid value for event_filter');
        $this->expectLogEntry(Analog::WARNING, 'Invalid value for paid_filter');

        $filters->reinit();
        $this->assertNull($filters->event_filter);
        $this->assertSame([], $filters->selected);
    }

    /**
     * Events dates filters take a year, a month or a day
     */
    public function testEventsDates(): void
    {
        $filters = new EventsList();
        $filters->start_date_filter = '2026';
        $filters->end_date_filter = '2026';
        $this->assertSame('2026-01-01', $filters->raw_start_date_filter);
        $this->assertSame('2026-12-31', $filters->raw_end_date_filter);

        $filters->start_date_filter = '2026-02';
        $filters->end_date_filter = '2026-02';
        $this->assertSame('2026-02-01', $filters->raw_start_date_filter);
        $this->assertSame('2026-02-28', $filters->raw_end_date_filter);

        $filters->start_date_filter = '2026-03-15';
        $this->assertSame('2026-03-15', $filters->raw_start_date_filter);
        $this->assertSame('2026-03-15', $filters->start_date_filter);
        $filters->end_date_filter = '';
        $this->assertNull($filters->raw_end_date_filter);

        $filters->calendar_filter = 1;
        $this->assertTrue($filters->calendar_filter);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown date format for start date filter');
        $filters->start_date_filter = 'yesterday';
    }
}
