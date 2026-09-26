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
 * Events controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class EventsController extends GaletteRoutingTestCase
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
     * Calendar event description is HTML: values typed by users must be escaped
     */
    public function testCalendarDescriptionIsEscaped(): void
    {
        $this->getMemberOne();
        $event = $this->insertEvent(
            'Party <b>name</b>',
            [
                'town'      => '<b>Lille</b>',
                'comment'   => '<img src=x onerror=alert(1)>',
            ]
        );
        $this->linkActivity($event, $this->insertActivity('<script>alert(2)</script>'));
        $this->logMember($this->dataAdherentOne());

        $request = $this->createRequest(
            'ajax-events_calendar',
            query_params: [
                'start' => date('Y-m-d'),
                'end'   => date('Y-m-d', strtotime('+1 month')),
            ]
        );
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());

        $events = json_decode((string)$test_response->getBody(), true);
        $this->assertIsArray($events);
        $this->assertCount(1, $events);
        $description = $events[0]['description'];

        $this->assertStringNotContainsString('<img', $description);
        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('<b>', $description);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $description);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $description);
        $this->assertStringContainsString('&lt;b&gt;Lille&lt;/b&gt;', $description);
        //raw values stay raw in JSON, the script displays them as text
        $this->assertSame('Party <b>name</b>', $events[0]['name']);
        $this->assertSame('Party <b>name</b>', $events[0]['title']);
    }
}
