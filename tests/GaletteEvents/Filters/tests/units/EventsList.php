<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Filters\tests\units;

use Galette\Tests\GaletteTestCase;

/**
 * Events list filters tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class EventsList extends GaletteTestCase
{
    protected int $seed = 20260926151512;

    /**
     * Date filters accept a year, a month or a day
     */
    public function testDateFilters(): void
    {
        $filters = new \GaletteEvents\Filters\EventsList();

        $filters->start_date_filter = '2026';
        $filters->end_date_filter = '2026';
        $this->assertSame('2026-01-01', $filters->raw_start_date_filter);
        $this->assertSame('2026-12-31', $filters->raw_end_date_filter);

        $filters->start_date_filter = '2026-02';
        $filters->end_date_filter = '2026-02';
        $this->assertSame('2026-02-01', $filters->raw_start_date_filter);
        $this->assertSame('2026-02-28', $filters->raw_end_date_filter);

        $filters->start_date_filter = '2026-02-10';
        $this->assertSame('2026-02-10', $filters->raw_start_date_filter);

        $filters->end_date_filter = '';
        $this->assertNull($filters->raw_end_date_filter);
    }
}
