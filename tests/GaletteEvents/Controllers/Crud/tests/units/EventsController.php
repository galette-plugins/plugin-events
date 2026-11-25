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
        $this->preferences->pref_galette_url = '';
        //iCalendar export generates the instance UUID, core tests expect the one they have, if any
        $update = $this->zdb->update(\Galette\Core\Preferences::TABLE)
            ->set(['val_pref' => $this->instance_uuid])
            ->where(['nom_pref' => 'pref_instance_uuid']);
        $this->zdb->execute($update);
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

        $events = json_decode((string)$test_response->getBody(), associative: true);
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
            'success_detected' => ['Activity has been detached from event.'],
            'warning_detected' => ['Do not forget to store the event'],
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

        $test_response = $this->postEvent(
            id: null,
            data: $this->getFormValues([
                'name'              => '',
                'add_activity'      => '1',
                'attach_activity'   => (string)$dinner,
            ])
        );
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('events_event_add')]],
            $test_response->getHeaders()
        );
        $this->expectFlashData([
            'success_detected' => ['Activity has been attached to event.'],
            'warning_detected' => ['Do not forget to store the event'],
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
        $this->postEvent(
            id: null,
            data: $this->getFormValues([
                'add_activity'      => '1',
                'attach_activity'   => (string)($dinner + 1000),
            ])
        );
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
        $test_response = $this->postEvent(id: null, data: $this->getFormValues(['group' => '999999', 'save' => '1']));
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
     * Sub groups are listed with their full name, without HTML entities
     */
    public function testFormGroupsFullNames(): void
    {
        $this->logSuperAdmin();
        $parent = $this->createGroup('Parent group');
        $child = new \Galette\Entity\Group();
        $child->setName('Child group');
        $child->setParentGroup((int)$parent->getId());
        $this->assertTrue($child->store());

        $body = (string)$this->app->handle($this->createRequest('events_event_add'))->getBody();
        $this->assertMatchesRegularExpression(
            sprintf('|<option\s+value="%1$s"\s*>\s*Parent group / Child group\s*</option>|', $child->getId()),
            $body
        );
        $this->assertStringNotContainsString('&amp;raquo;', $body);
        $this->assertStringNotContainsString('&amp;nbsp;', $body);
    }

    /**
     * Staff members choose who can see an event, group managers choose one of their groups
     */
    public function testFormVisibility(): void
    {
        $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $this->createGroup('Managed group', [$member_two]);
        $event = $this->insertEvent('Public event', ['is_public' => true, 'begin_time' => '18:30', 'end_time' => '20:00']);

        $this->logSuperAdmin();
        $body = (string)$this->app->handle($this->createRequest('events_event_edit', ['id' => (string)$event]))->getBody();
        $this->assertMatchesRegularExpression(
            '@<input type="radio" name="visibility" id="visibility_public" value="public"[^>]*checked="checked"/>@',
            $body
        );
        $this->assertDoesNotMatchRegularExpression('@id="visibility_(members|group)"[^>]*checked@', $body);
        $this->assertStringContainsString('id="group_field" class="field displaynone"', $body);
        $this->assertDoesNotMatchRegularExpression('@id="all_day"[^>]*checked@', $body);
        $this->assertMatchesRegularExpression('@<strong id="event_period">[^<]+ 18:30 – [^<]+ 20:00</strong>@', $body);
        $this->login->logout();

        $this->logMember($this->dataAdherentTwo());
        $body = (string)$this->app->handle($this->createRequest('events_event_add'))->getBody();
        $this->assertStringNotContainsString('name="visibility"', $body);
        $this->assertMatchesRegularExpression('@<select\s+name="group"\s+id="group"\s+required="required"@', $body);
        //new events last all day
        $this->assertMatchesRegularExpression('@id="all_day" value="1" checked="checked"@', $body);
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
        $events = json_decode((string)$this->app->handle($request)->getBody(), associative: true);
        $this->assertIsArray($events);
        $can_edit = array_column($events, 'can_edit', 'name');
        ksort($can_edit);
        $this->assertSame(['Managed event' => true, 'Public event' => false], $can_edit);

        //links of the event modal come with the event
        $by_name = array_column($events, column_key: null, index_key: 'name');
        $managed_id = (string)$by_name['Managed event']['id_event'];
        $public_id = (string)$by_name['Public event']['id_event'];
        $this->assertSame(
            $this->routeparser->urlFor('events_event_edit', ['id' => $managed_id]),
            $by_name['Managed event']['edit_url']
        );
        $this->assertArrayNotHasKey('edit_url', $by_name['Public event']);
        $this->assertSame(
            $this->routeparser->urlFor('events_booking_add') . '?event=' . $public_id,
            $by_name['Public event']['booking_url']
        );
        $this->assertSame(
            $this->routeparser->urlFor('events_event_ics', ['id' => $public_id]),
            $by_name['Public event']['ics_url']
        );
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
     * Calendar counts attendees of each event
     */
    public function testCalendarAttendees(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $paid = $this->zdb->isPostgres() ? 'true' : 1;
        $first = $this->insertEvent('First event');
        $this->insertBooking($first, $member_one->id, ['number_people' => 3, 'is_paid' => $paid]);
        $this->insertBooking($first, $member_two->id, ['number_people' => 2]);
        $this->insertEvent('Second event');
        $this->logSuperAdmin();

        $request = $this->createRequest(
            'ajax-events_calendar',
            query_params: [
                'start' => date('Y-m-d'),
                'end'   => date('Y-m-d', strtotime('+1 month')),
            ]
        );
        $events = json_decode((string)$this->app->handle($request)->getBody(), associative: true);
        $this->assertIsArray($events);
        $descriptions = array_column($events, 'description', 'name');
        $this->assertStringContainsString('<strong>Attendees:</strong> 5 (3 paid)', $descriptions['First event']);
        $this->assertStringContainsString('<strong>Attendees:</strong> 0</li>', $descriptions['Second event']);
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
        $filter = (fn(array $data): \Psr\Http\Message\ResponseInterface => $this->app->handle(
            $this->createRequest('filter-eventslist', [], 'POST')->withParsedBody($data)
        ));

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
        $this->assertStringContainsString('<div class="ui small modal" id="calendar_event">', $body);

        //options are given as JSON, that cannot close the script element
        $this->assertSame(1, preg_match('#<script [^>]*id="calendar_options">(.+?)</script>#s', $body, $matches));
        $options = json_decode($matches[1], associative: true);
        $this->assertSame($this->routeparser->urlFor('ajax-events_calendar'), $options['dataurl']);
        $this->assertSame('Daily list', $options['buttons']['listDay']['text']);
        $this->assertSame('en-US', $options['locale']);
    }

    /**
     * Plugin pages load their script from a file, other pages do not
     */
    public function testScriptFile(): void
    {
        $this->logSuperAdmin();
        $this->linkActivity($this->insertEvent('Event'), $this->insertActivity('Dinner'));

        $body = (string)$this->app->handle($this->createRequest('events_event_add'))->getBody();
        $this->assertStringContainsString('events.js', $body);
        $this->assertStringNotContainsString('alert(', $body);
        $this->assertStringContainsString('aria-controls="attach_activity"', $body);

        //linked activities status are labelled
        $event = $this->insertEvent('Linked');
        $dinner = $this->insertActivity('Supper');
        $this->linkActivity($event, $dinner, \GaletteEvents\Activity::REQUIRED);
        $body = (string)$this->app->handle($this->createRequest('events_event_edit', ['id' => (string)$event]))->getBody();
        $this->assertMatchesRegularExpression('#<label\s+for="activities_status_' . $dinner . '"\s*>\s*Supper\s*</label>#', $body);
        $this->assertMatchesRegularExpression(
            '#<select\s+name="activities_status\[\]"\s+id="activities_status_' . $dinner . '"#',
            $body
        );
        $this->assertMatchesRegularExpression(
            '#<option\s+value="' . \GaletteEvents\Activity::REQUIRED . '"\s+selected="selected"#',
            $body
        );
        $this->assertStringNotContainsString('{$activity', $body);

        $body = (string)$this->app->handle($this->createRequest('dashboard'))->getBody();
        $this->assertStringNotContainsString('events.js', $body);
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
        $this->assertDownload($public, allowed: true);
        $this->assertDownload($own_event, allowed: true);
        $this->assertDownload($booked_event, allowed: true);
        $this->assertDownload($other_event, allowed: false);
        $this->assertDownload($other_event + 100, allowed: false);
        $this->login->logout();

        $this->logSuperAdmin();
        $this->assertDownload($other_event, allowed: true);
        $this->login->logout();

        //visitors are sent to the login page
        $test_response = $this->app->handle(
            $this->createRequest('events_event_ics', ['id' => (string)$public])
        );
        $this->expectLogin($test_response);
    }

    /**
     * Get the calendar subscription of a member, as calendar applications do
     *
     * @param string $token Subscription token
     */
    private function getFeed(string $token): \Psr\Http\Message\ResponseInterface
    {
        return $this->app->handle($this->createRequest('events_feed', ['token' => $token]));
    }

    /**
     * Set member active state
     *
     * @param int  $member Member ID
     * @param bool $active Active state
     */
    private function setActive(int $member, bool $active): void
    {
        $update = $this->zdb->update(\Galette\Entity\Adherent::TABLE)
            ->set(['activite_adh' => $this->zdb->isPostgres() ? ($active ? 'true' : 'false') : (int)$active])
            ->where([\Galette\Entity\Adherent::PK => $member]);
        $this->zdb->execute($update);
    }

    /**
     * Members get, change and use their calendar subscription
     */
    public function testSubscription(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $other = $this->createGroup('Other group', [], [$member_two]);
        $this->insertEvent('Public event', ['begin_time' => '18:30', 'end_time' => '20:00']);
        $this->insertEvent('Other group event', ['id_group' => $other->getId()]);
        $feeds = new \GaletteEvents\Feeds($this->zdb);
        //instance URL cannot be guessed without request host
        $this->preferences->pref_galette_url = 'https://galette.test/';

        $this->logMember($this->dataAdherentOne());
        $body = (string)$this->app->handle($this->createRequest('events_calendar'))->getBody();
        $this->assertStringContainsString('id="calendar_subscribe"', $body);
        $this->assertStringContainsString($this->routeparser->urlFor('events_feed_subscribe'), $body);
        //help is there before any subscription, it gets the address from there too
        $this->assertStringContainsString('id="subscription_help"', $body);
        $this->assertStringContainsString('form="feed_subscribe"', $body);
        //no subscription until asked for
        $this->assertNull((new \GaletteEvents\Feeds($this->zdb))->getToken((int)$member_one->id));

        $test_response = $this->app->handle($this->createRequest('events_feed_subscribe', [], 'POST'));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('events_calendar') . '?subscription']],
            $test_response->getHeaders()
        );
        $token = $feeds->getToken((int)$member_one->id);
        $this->assertNotNull($token);

        //subscribing again keeps the address
        $this->app->handle($this->createRequest('events_feed_subscribe', [], 'POST'));
        $this->assertSame($token, $feeds->getToken((int)$member_one->id));

        $body = (string)$this->app->handle(
            $this->createRequest(
                route_name: 'events_calendar',
                route_args: [],
                method: 'GET',
                content_type: 'text/html',
                query_params: ['subscription' => '']
            )
        )->getBody();
        $url = 'galette.test' . $this->routeparser->urlFor('events_feed', ['token' => $token]);
        $this->assertStringContainsString('href="webcal://' . $url . '"', $body);
        $this->assertStringContainsString('value="https://' . $url . '"', $body);
        $this->assertStringContainsString('Your subscription address is ready.', $body);
        $this->assertStringContainsString('id="subscription_help_url" value="https://' . $url . '"', $body);
        $this->assertStringNotContainsString('id="feed_subscribe"', $body);
        $this->assertStringContainsString('id="feed_regenerate"', $body);
        //no reminder once seen
        $body = (string)$this->app->handle($this->createRequest('events_calendar'))->getBody();
        $this->assertStringNotContainsString('Your subscription address is ready.', $body);
        $this->login->logout();

        //calendar applications have no session
        $test_response = $this->getFeed($token);
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame(['text/calendar; charset=utf-8'], $test_response->getHeader('Content-Type'));
        $ics = (string)$test_response->getBody();
        $this->assertStringContainsString('SUMMARY:Public event', $ics);
        $this->assertStringNotContainsString('Other group event', $ics);
        $this->assertStringContainsString('X-WR-CALNAME:', $ics);
        $this->assertStringContainsString('REFRESH-INTERVAL;VALUE=DURATION:PT1H', $ics);

        //unknown token and inactive member get the same answer
        $this->assertSame(404, $this->getFeed(str_repeat('a', 64))->getStatusCode());
        $this->setActive((int)$member_one->id, active: false);
        try {
            $this->assertSame(404, $this->getFeed($token)->getStatusCode());
        } finally {
            $this->setActive((int)$member_one->id, active: true);
        }

        //previous address no longer works once changed
        $this->logMember($this->dataAdherentOne());
        $test_response = $this->app->handle($this->createRequest('events_feed_regenerate', [], 'POST'));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('events_calendar') . '?subscription']],
            $test_response->getHeaders()
        );
        $this->expectFlashData(['success_detected' => [_T(
            'Your subscription address has been changed: calendar applications subscribed to the previous one no longer get updates.',
            'events'
        )]]);
        $this->login->logout();
        $new = $feeds->getToken((int)$member_one->id);
        $this->assertNotSame($token, $new);
        $this->assertSame(404, $this->getFeed($token)->getStatusCode());
        $this->assertSame(200, $this->getFeed((string)$new)->getStatusCode());
    }

    /**
     * Calendar script gets subscription addresses, to open the calendar application at once
     */
    public function testSubscriptionFromScript(): void
    {
        $member_one = $this->getMemberOne();
        $this->preferences->pref_galette_url = 'https://galette.test/';
        $this->logMember($this->dataAdherentOne());

        $request = $this->createRequest('events_feed_subscribe', [], 'POST')
            ->withHeader('X-Requested-With', 'XMLHttpRequest');
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $token = (new \GaletteEvents\Feeds($this->zdb))->getToken((int)$member_one->id);
        $this->assertNotNull($token);
        $url = 'galette.test' . $this->routeparser->urlFor('events_feed', ['token' => $token]);
        $this->assertSame(
            ['https' => 'https://' . $url, 'webcal' => 'webcal://' . $url],
            json_decode((string)$test_response->getBody(), associative: true)
        );

        //same address when asked again
        $this->assertSame(
            ['https' => 'https://' . $url, 'webcal' => 'webcal://' . $url],
            json_decode((string)$this->app->handle($request)->getBody(), associative: true)
        );
        $this->login->logout();

        $this->logSuperAdmin();
        $this->assertSame(403, $this->app->handle($request)->getStatusCode());
    }

    /**
     * Superadmin is not a member, it has no subscription
     */
    public function testSuperAdminCannotSubscribe(): void
    {
        $this->logSuperAdmin();
        $body = (string)$this->app->handle($this->createRequest('events_calendar'))->getBody();
        $this->assertStringNotContainsString('id="calendar_subscribe"', $body);

        $test_response = $this->app->handle($this->createRequest('events_feed_subscribe', [], 'POST'));
        $this->assertSame(['Location' => [$this->routeparser->urlFor('events_calendar')]], $test_response->getHeaders());
        $this->expectFlashData(['error_detected' => [_T('You do not have permission for requested URL.')]]);
    }

    /**
     * Visitors cannot subscribe
     */
    public function testVisitorCannotSubscribe(): void
    {
        foreach (['events_feed_subscribe', 'events_feed_regenerate'] as $route) {
            $this->expectLogin($this->app->handle($this->createRequest($route, [], 'POST')));
        }
    }

    /**
     * Visitors get public events, and their calendar subscription, as public pages allow
     */
    public function testPublicEvents(): void
    {
        $public = $this->insertEvent(
            'Public event',
            ['is_public' => true, 'begin_date' => '2031-03-14', 'end_date' => '2031-03-14', 'comment' => "First line\nSecond <b>line</b>"]
        );
        $this->insertEvent('Summed up event', ['is_public' => true, 'summary' => 'Short <i>presentation</i>']);
        $members = $this->insertEvent('Members event');
        $this->setRawPreference('pref_bool_publicpages', value: true);
        $this->setRawPreference('pref_events_publicpages_visibility_events', \Galette\Enums\PublicPageVisibility::Everyone->value);
        //instance URL cannot be guessed without request host
        $this->preferences->pref_galette_url = 'https://galette.test/';

        $test_response = $this->app->handle($this->createRequest('events_public'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Public event', $body);
        $this->assertStringNotContainsString('Members event', $body);
        $this->assertStringContainsString('First line<br />' . "\n" . 'Second &lt;b&gt;line&lt;/b&gt;', $body);
        //list shows the summary, or the beginning of the comment
        $this->assertStringContainsString('<p>Short &lt;i&gt;presentation&lt;/i&gt;</p>', $body);
        $this->assertStringContainsString('<p>First line Second &lt;b&gt;line&lt;/b&gt;</p>', $body);
        //events are grouped by month
        $this->assertStringContainsString('<h2 class="ui small dividing header public-events-month">March 2031</h2>', $body);
        $this->assertStringContainsString(
            'data-ics="' . $this->routeparser->urlFor('events_public_event_ics', ['id' => (string)$public]) . '"',
            $body
        );
        $this->assertStringContainsString(
            'href="webcal://galette.test' . $this->routeparser->urlFor('events_public_feed') . '"',
            $body
        );

        $test_response = $this->app->handle(
            $this->createRequest('events_public_event_ics', ['id' => (string)$public])
        );
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('SUMMARY:Public event', (string)$test_response->getBody());
        //other events cannot be told from missing ones
        foreach ([$members, 999999] as $id) {
            $test_response = $this->app->handle(
                $this->createRequest('events_public_event_ics', ['id' => (string)$id])
            );
            $this->assertSame(404, $test_response->getStatusCode());
        }

        $test_response = $this->app->handle($this->createRequest('events_public_feed'));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame(['text/calendar; charset=utf-8'], $test_response->getHeader('Content-Type'));
        $ics = (string)$test_response->getBody();
        $this->assertStringContainsString('SUMMARY:Public event', $ics);
        $this->assertStringNotContainsString('Members event', $ics);

        //members only
        $this->setRawPreference(
            'pref_events_publicpages_visibility_events',
            \Galette\Enums\PublicPageVisibility::UpToDateMembers->value
        );
        foreach (['events_public', 'events_public_feed', 'events_public_event_ics'] as $route) {
            $test_response = $this->app->handle($this->createRequest($route, ['id' => (string)$public]));
            $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
            $this->expectFlashData(['error_detected' => ['Unauthorized']]);
        }
    }
}
