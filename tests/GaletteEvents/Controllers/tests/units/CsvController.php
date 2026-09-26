<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\tests\units;

use Analog\Analog;
use Galette\Tests\GaletteRoutingTestCase;
use GaletteEvents\tests\EventsFixtures;

/**
 * CSV controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CsvController extends GaletteRoutingTestCase
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
        $this->preferences->pref_bool_groupsmanagers_exports = true;
        $this->cleanEvents();
        parent::tearDown();
    }

    /**
     * Export bookings of an event
     *
     * @param int $event Event ID
     */
    private function exportEvent(int $event): string
    {
        $test_response = $this->app->handle(
            $this->createRequest('event_bookings_export', ['id' => (string)$event])
        );
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame(['text/csv'], $test_response->getHeader('Content-Type'));
        return (string)$test_response->getBody();
    }

    /**
     * Group managers export bookings on events of the groups they manage only
     */
    public function testManagerExportsManagedGroupsBookingsOnly(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $managed = $this->createGroup('Managed group', [$member_two], [$member_one]);
        //member two belongs to this one, but does not manage it
        $other = $this->createGroup('Other group', [], [$member_one, $member_two]);

        $managed_event = $this->insertEvent('Managed event', ['id_group' => $managed->getId()]);
        $other_event = $this->insertEvent('Other event', ['id_group' => $other->getId()]);
        $public_event = $this->insertEvent('Public event');
        foreach ([$managed_event, $other_event, $public_event] as $event) {
            $this->insertBooking($event, $member_one->id);
        }

        $this->logMember($this->dataAdherentTwo());
        $this->assertStringContainsString($member_one->email, $this->exportEvent($managed_event));
        $this->assertStringNotContainsString($member_one->email, $this->exportEvent($other_event));
        $this->assertStringNotContainsString($member_one->email, $this->exportEvent($public_event));
    }

    /**
     * Group managers export bookings as core preferences allow them to
     */
    public function testManagerExportsAsCoreAllows(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $managed = $this->createGroup('Managed group', [$member_two], [$member_one]);
        $event = $this->insertEvent('Managed event', ['id_group' => $managed->getId()]);
        $this->insertBooking($event, $member_one->id);
        $this->preferences->pref_bool_groupsmanagers_exports = false;

        $this->logMember($this->dataAdherentTwo());
        foreach (['event_bookings_export' => ['id' => (string)$event], 'events_bookings_export' => []] as $route => $args) {
            $test_response = $this->app->handle($this->createRequest($route, $args, $args === [] ? 'POST' : 'GET'));
            $this->assertSame(
                ['Location' => [$this->routeparser->urlFor('events_bookings', ['event' => 'all'])]],
                $test_response->getHeaders()
            );
            $this->expectFlashData(['error_detected' => [_T('You do not have permission for requested URL.')]]);
            $this->expectLogEntry(Analog::WARNING, 'has tried to export bookings without the right to do so');
            $this->expectNoLogEntry();
        }
        $this->login->logout();

        //preference is for group managers only
        $staff = $this->getStaffMember($member_one);
        $this->logMember($this->dataAdherentOne());
        $this->assertStringContainsString($member_one->email, $this->exportEvent($event));
        $this->resetStaffStatus($staff, $member_two);
    }
}
