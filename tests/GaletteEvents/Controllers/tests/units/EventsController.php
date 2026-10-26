<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\tests\units;

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

    protected int $seed = 20261026221512;
    protected bool $load_plugins = true;
    private string $instance_uuid;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->instance_uuid = $this->preferences->pref_instance_uuid;
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        //core tests expect the instance UUID they have, if any
        $update = $this->zdb->update(\Galette\Core\Preferences::TABLE)
            ->set(['val_pref' => $this->instance_uuid])
            ->where(['nom_pref' => 'pref_instance_uuid']);
        $this->zdb->execute($update);
        $this->cleanEvents();
        parent::tearDown();
    }

    /**
     * Download an event, check it is refused as a missing one
     *
     * @param int  $event   Event ID
     * @param bool $allowed Is download expected to be allowed
     */
    private function assertDownload(int $event, bool $allowed): void
    {
        $test_response = $this->app->handle(
            $this->createRequest('events_event_ics', ['id' => (string)$event])
        );

        if ($allowed) {
            $this->assertSame(200, $test_response->getStatusCode());
            $this->assertSame(['text/calendar; charset=utf-8'], $test_response->getHeader('Content-Type'));
            $this->assertStringContainsString('attachment; filename="', $test_response->getHeaderLine('Content-Disposition'));
            $this->assertStringContainsString('UID:event-' . $event . '@', (string)$test_response->getBody());
        } else {
            $this->assertSame(
                ['Location' => [$this->routeparser->urlFor('events_events')]],
                $test_response->getHeaders()
            );
            $this->expectFlashData(['error_detected' => [sprintf(_T('No event #%1$s.', 'events'), $event)]]);
        }
    }

    /**
     * Members download events they can see in the calendar only
     */
    public function testIcsDownload(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $own = $this->createGroup('Own group', [], [$member_one]);
        $other = $this->createGroup('Other group', [], [$member_two]);

        $public = $this->insertEvent('public', ['is_open' => false]);
        $own_event = $this->insertEvent('own group', ['id_group' => $own->getId()]);
        $other_event = $this->insertEvent('other group', ['id_group' => $other->getId()]);
        $booked_event = $this->insertEvent('booked other group', ['id_group' => $other->getId()]);
        $this->insertBooking($booked_event, $member_one->id);

        $this->logMember($this->dataAdherentOne());
        $this->assertDownload($public, true);
        $this->assertDownload($own_event, true);
        $this->assertDownload($booked_event, true);
        $this->assertDownload($other_event, false);
        $this->assertDownload($other_event + 100, false);
        $this->login->logout();

        $this->logSuperAdmin();
        $this->assertDownload($other_event, true);
        $this->login->logout();

        //visitors are sent to the login page
        $test_response = $this->app->handle(
            $this->createRequest('events_event_ics', ['id' => (string)$public])
        );
        $this->expectLogin($test_response);
    }
}
