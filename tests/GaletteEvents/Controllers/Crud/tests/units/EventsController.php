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
     * Post an event
     *
     * @param ?int                $id   Event ID, null to add a new one
     * @param array<string,mixed> $data Posted data
     */
    private function postEvent(?int $id, array $data): \Psr\Http\Message\ResponseInterface
    {
        if ($id === null) {
            $request = $this->createRequest('events_storeevent_add', [], 'POST');
        } else {
            $request = $this->createRequest('events_storeevent_edit', ['id' => (string)$id], 'POST');
            $data += ['id' => (string)$id];
        }
        return $this->app->handle($request->withParsedBody($data));
    }

    /**
     * Get values posted from event form
     *
     * @param array<string,mixed> $values Values to override
     *
     * @return array<string,mixed>
     */
    private function getFormValues(array $values = []): array
    {
        return $values + [
            'name'          => 'Event',
            'address'       => '',
            'zip'           => '',
            'town'          => 'Lille',
            'country'       => '',
            'comment'       => '',
            'color'         => '',
            'begin_date'    => date('Y-m-d', strtotime('+10 days')),
            'end_date'      => date('Y-m-d', strtotime('+11 days')),
            'open'          => '1',
        ];
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

    /**
     * Detaching an activity waits for the event to be stored, as attaching does
     */
    public function testDetachActivityWaitsForStore(): void
    {
        $this->logSuperAdmin();
        $dinner = $this->insertActivity('Dinner');
        $lodging = $this->insertActivity('Lodging');
        $event = $this->insertEvent('Event');
        $this->linkActivity($event, $dinner);
        $this->linkActivity($event, $lodging);

        $test_response = $this->postEvent($event, $this->getFormValues([
            'remove_activity'   => '1',
            'detach_activity'   => (string)$dinner,
            'activities_ids'    => [(string)$dinner, (string)$lodging],
            'activities_status' => ['1', '1'],
        ]));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('events_event_edit', ['id' => (string)$event])]],
            $test_response->getHeaders()
        );
        $this->expectFlashData([
            'warning_detected' => ['Do not forget to store the event'],
            'success_detected' => ['Activity has been detached from event.'],
        ]);
        $this->assertSame([$dinner => 1, $lodging => 1], $this->getEventActivities($event));

        $this->postEvent($event, $this->getFormValues([
            'save'              => '1',
            'activities_ids'    => [(string)$lodging],
            'activities_status' => ['1'],
        ]));
        $this->expectFlashData(['success_detected' => ['Event has been modified.']]);
        $this->assertSame([$lodging => 1], $this->getEventActivities($event));
    }

    /**
     * Activities are attached to an event whose form is not complete yet
     */
    public function testAttachActivityOnIncompleteEvent(): void
    {
        $this->logSuperAdmin();
        $dinner = $this->insertActivity('Dinner');

        $test_response = $this->postEvent(null, $this->getFormValues([
            'name'              => '',
            'add_activity'      => '1',
            'attach_activity'   => (string)$dinner,
        ]));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('events_event_add')]],
            $test_response->getHeaders()
        );
        $this->expectFlashData([
            'warning_detected' => ['Do not forget to store the event'],
            'success_detected' => ['Activity has been attached to event.'],
        ]);
        $this->expectLogEntry(Analog::ERROR, 'Some errors has been threw attempting to edit/store an event');
        //form shows posted values, with the attached activity
        $test_response = $this->app->handle($this->createRequest('events_event_add'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('name="activities_ids[]" value="' . $dinner . '"', $body);
        $this->assertStringContainsString('value="Lille"', $body);
        $this->expectLogEntry(Analog::ERROR, 'Some errors has been threw attempting to edit/store an event');
        $this->assertNull($this->session->plugin_events_event_data ?? null);

        //an unknown activity is not attached
        $this->postEvent(null, $this->getFormValues([
            'add_activity'      => '1',
            'attach_activity'   => (string)($dinner + 1000),
        ]));
        $this->expectFlashData(['error_detected' => ['Please choose an activity to add']]);
    }

    /**
     * Values posted on an event are not shown on another one
     */
    public function testPostedValuesStayOnTheirEvent(): void
    {
        $this->logSuperAdmin();
        $first = $this->insertEvent('First event');
        $second = $this->insertEvent('Second event');

        $this->postEvent($first, $this->getFormValues(['name' => 'Renamed', 'town' => '', 'save' => '1']));
        $this->expectFlashData(['error_detected' => ['Town is mandatory']]);
        $this->expectLogEntry(Analog::ERROR, 'Some errors has been threw attempting to edit/store an event');

        $body = (string)$this->app->handle($this->createRequest('events_event_edit', ['id' => (string)$second]))->getBody();
        $this->assertStringContainsString('value="Second event"', $body);
        $this->assertStringNotContainsString('Renamed', $body);
        $this->expectNoLogEntry();
    }

    /**
     * Group managers cannot store events of groups they do not manage
     */
    public function testManagerCannotStoreOtherEvent(): void
    {
        $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $this->createGroup('Managed group', [$member_two]);
        $event = $this->insertEvent('Public event');
        $this->logMember($this->dataAdherentTwo());

        $test_response = $this->postEvent($event, $this->getFormValues(['name' => 'Changed', 'save' => '1']));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('events_events')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        //message comes in the language of the logged-in member
        $this->expectFlashData(['error_detected' => [_T('You do not have permission for requested URL.')]]);
        $this->expectLogEntry(Analog::WARNING, 'has tried to edit event #' . $event);
        $this->expectNoLogEntry();
    }

    /**
     * Storage errors are logged, and reported on the form
     */
    public function testStoreError(): void
    {
        $this->logSuperAdmin();
        //leave the test transaction: storage opens its own one, as it does outside of tests
        $this->zdb->connection->rollBack();

        //an unknown group breaks the foreign key
        $test_response = $this->postEvent(null, $this->getFormValues(['group' => '999999', 'save' => '1']));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('events_event_add')]],
            $test_response->getHeaders()
        );
        $this->expectFlashData(['error_detected' => ['An error occurred while storing the event.']]);
        $this->expectLogEntry(Analog::ERROR, 'Query error');
        $this->expectLogEntry(Analog::ERROR, 'Unable to store event #new | ');
        $this->expectNoLogEntry();
    }

    /**
     * Past events stay open in their form, so storing them does not close them
     */
    public function testPastEventFormKeepsOpenFlag(): void
    {
        $this->logSuperAdmin();
        $event = $this->insertEvent(
            'Past event',
            ['begin_date' => date('Y-m-d', strtotime('-2 days')), 'end_date' => date('Y-m-d', strtotime('-1 day'))]
        );

        $test_response = $this->app->handle($this->createRequest('events_event_edit', ['id' => (string)$event]));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame(
            1,
            preg_match('@<input[^>]*id="open"[^>]*>@s', (string)$test_response->getBody(), $matches)
        );
        $this->assertStringContainsString(' checked', $matches[0]);
        $this->assertStringContainsString('value="1"', $matches[0]);
    }

    /**
     * Calendar requires dates
     */
    public function testCalendarRequiresDates(): void
    {
        $this->getMemberOne();
        $this->logMember($this->dataAdherentOne());

        foreach ([[], ['start' => 'soon', 'end' => 'later']] as $query) {
            $test_response = $this->app->handle($this->createRequest('ajax-events_calendar', query_params: $query));
            $this->assertSame(400, $test_response->getStatusCode());
            $this->assertSame('[]', (string)$test_response->getBody());
        }
        $this->expectNoLogEntry();
    }

    /**
     * Calendar tells which events can be edited
     */
    public function testCalendarCanEdit(): void
    {
        $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $managed = $this->createGroup('Managed group', [$member_two]);
        $this->insertEvent('Managed event', ['id_group' => $managed->getId()]);
        $this->insertEvent('Public event');
        $this->logMember($this->dataAdherentTwo());

        $request = $this->createRequest(
            'ajax-events_calendar',
            query_params: [
                'start' => date('Y-m-d'),
                'end'   => date('Y-m-d', strtotime('+1 month')),
            ]
        );
        $events = json_decode((string)$this->app->handle($request)->getBody(), true);
        $this->assertIsArray($events);
        $can_edit = array_column($events, 'can_edit', 'name');
        ksort($can_edit);
        $this->assertSame(['Managed event' => true, 'Public event' => false], $can_edit);
    }

    /**
     * Removal confirmation page names the event
     */
    public function testRemovalTitle(): void
    {
        $this->logSuperAdmin();
        $event = $this->insertEvent('Party');

        $test_response = $this->app->handle($this->createRequest('events_remove_event', ['id' => (string)$event]));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = html_entity_decode((string)$test_response->getBody(), ENT_QUOTES);
        $this->assertStringContainsString("<title>Remove event 'Party' - ", $body);
    }

    /**
     * Unknown events are reported, instead of showing an empty form
     */
    public function testUnknownEvent(): void
    {
        $this->logSuperAdmin();
        $id = $this->insertEvent('Removed event');
        $this->cleanEvents();
        $expected = ['Location' => [$this->routeparser->urlFor('events_events')]];

        $test_response = $this->app->handle($this->createRequest('events_event_edit', ['id' => (string)$id]));
        $this->assertSame($expected, $test_response->getHeaders());
        $this->expectFlashData(['error_detected' => ['No event #' . $id . '.']]);

        $test_response = $this->postEvent($id, $this->getFormValues(['save' => '1']));
        $this->assertSame($expected, $test_response->getHeaders());
        $this->expectFlashData(['error_detected' => ['No event #' . $id . '.']]);

        $test_response = $this->app->handle($this->createRequest('events_remove_event', ['id' => (string)$id]));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('<title>No event #' . $id . '. - ', (string)$test_response->getBody());
        $this->expectNoLogEntry();
    }

    /**
     * Events list shows events current user can see
     */
    public function testList(): void
    {
        $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $other = $this->createGroup('Other group', [], [$member_two]);
        $this->insertEvent('Public event');
        $this->insertEvent('Other group event', ['id_group' => $other->getId()]);

        $this->logMember($this->dataAdherentOne());
        $test_response = $this->app->handle($this->createRequest('events_events'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Public event', $body);
        $this->assertStringNotContainsString('Other group event', $body);
        $this->expectNoLogEntry();
        $this->login->logout();

        $this->logSuperAdmin();
        $body = (string)$this->app->handle($this->createRequest('events_events'))->getBody();
        $this->assertStringContainsString('Public event', $body);
        $this->assertStringContainsString('Other group event', $body);
    }

    /**
     * Events list filters are stored in session
     */
    public function testFilter(): void
    {
        $this->logSuperAdmin();
        $filter = function (array $data): \Psr\Http\Message\ResponseInterface {
            return $this->app->handle(
                $this->createRequest('filter-eventslist', [], 'POST')->withParsedBody($data)
            );
        };

        $test_response = $filter(['nbshow' => '50']);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('events_events')]], $test_response->getHeaders());
        $this->assertSame(50, $this->session->plugin_events_events_filter->show);

        $filter(['clear_filter' => '1']);
        $this->assertSame((int)$this->preferences->pref_numrows, $this->session->plugin_events_events_filter->show);
    }

    /**
     * Calendar page loads its script
     */
    public function testCalendarPage(): void
    {
        $this->getMemberOne();
        $this->logMember($this->dataAdherentOne());

        $test_response = $this->app->handle($this->createRequest('events_calendar'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('<div id=\'calendar\'></div>', $body);
        $this->assertStringContainsString('js/calendar.bundle.js', $body);
        $this->assertStringContainsString($this->routeparser->urlFor('ajax-events_calendar'), $body);
    }
}
