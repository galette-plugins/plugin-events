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
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

/**
 * iCalendar export tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Ics extends GaletteTestCase
{
    use EventsFixtures;

    protected int $seed = 20261026211512;
    protected bool $load_plugins = true;
    private string $timezone;
    private string $instance_uuid;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->timezone = date_default_timezone_get();
        $this->instance_uuid = $this->preferences->pref_instance_uuid;
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
        //core tests expect the instance UUID they have, if any
        $update = $this->zdb->update(\Galette\Core\Preferences::TABLE)
            ->set(['val_pref' => $this->instance_uuid])
            ->where(['nom_pref' => 'pref_instance_uuid']);
        $this->zdb->execute($update);
        $this->cleanEvents();
        parent::tearDown();
    }

    /**
     * Get events as parsed iCalendar
     *
     * @param array<int> $ids Events identifiers
     */
    private function getCalendar(array $ids): VCalendar
    {
        $events = array_map(
            fn(int $id): \GaletteEvents\Event => new \GaletteEvents\Event($this->zdb, $this->login, $this->history, $id),
            $ids
        );
        $ics = new \GaletteEvents\Ics($this->preferences, $this->routeparser);
        $calendar = Reader::read($ics->getCalendar($events)->serialize());
        $this->assertInstanceOf(VCalendar::class, $calendar);
        $this->assertSame([], $calendar->validate());
        return $calendar;
    }

    /**
     * Get the only event of a calendar
     *
     * @param VCalendar $calendar Calendar
     */
    private function getEvent(VCalendar $calendar): VEvent
    {
        $events = $calendar->select('VEVENT');
        $this->assertCount(1, $events);
        $this->assertInstanceOf(VEvent::class, $events[0]);
        return $events[0];
    }

    /**
     * Get a property of a component
     *
     * @param Component $component Component
     * @param string    $name      Property name
     */
    private function getProperty(Component $component, string $name): ?Property
    {
        $properties = $component->select($name);
        $this->assertLessThanOrEqual(1, count($properties));
        $property = $properties[0] ?? null;
        $this->assertTrue($property === null || $property instanceof Property);
        return $property;
    }

    /**
     * Get the value of a property of a component, as a string
     *
     * @param Component $component Component
     * @param string    $name      Property name
     */
    private function getValue(Component $component, string $name): ?string
    {
        $property = $this->getProperty($component, $name);
        return $property === null ? null : (string)$property;
    }

    /**
     * All day events end the day after their last one
     */
    public function testAllDay(): void
    {
        $one_day = $this->insertEvent('One day', ['begin_date' => '2026-11-05', 'end_date' => '2026-11-05']);
        $two_days = $this->insertEvent('Two days', ['begin_date' => '2026-11-05', 'end_date' => '2026-11-06']);

        $vevent = $this->getEvent($this->getCalendar([$one_day]));
        $this->assertSame('One day', $this->getValue($vevent, 'SUMMARY'));
        $this->assertSame('DATE', (string)$this->getProperty($vevent, 'DTSTART')?->offsetGet('VALUE'));
        $this->assertSame('20261105', $this->getValue($vevent, 'DTSTART'));
        $this->assertSame('20261106', $this->getValue($vevent, 'DTEND'));

        $vevent = $this->getEvent($this->getCalendar([$two_days]));
        $this->assertSame('20261105', $this->getValue($vevent, 'DTSTART'));
        $this->assertSame('20261107', $this->getValue($vevent, 'DTEND'));
    }

    /**
     * Times are local to the server, given in UTC
     */
    public function testTimes(): void
    {
        date_default_timezone_set('Europe/Paris');
        $same_day = ['begin_date' => '2026-11-05', 'end_date' => '2026-11-05'];
        $timed = $this->insertEvent('Timed', ['begin_time' => '18:30', 'end_time' => '20:00'] + $same_day);
        $begin_only = $this->insertEvent('Begin only', ['begin_time' => '19:00'] + $same_day);
        $begin_only_days = $this->insertEvent(
            'Begin only, two days',
            ['begin_date' => '2026-11-05', 'end_date' => '2026-11-06', 'begin_time' => '19:00']
        );
        //summer time
        $summer = $this->insertEvent(
            'Summer',
            ['begin_date' => '2026-07-01', 'end_date' => '2026-07-01', 'begin_time' => '10:00', 'end_time' => '12:00']
        );

        $vevent = $this->getEvent($this->getCalendar([$timed]));
        $this->assertSame('20261105T173000Z', $this->getValue($vevent, 'DTSTART'));
        $this->assertSame('20261105T190000Z', $this->getValue($vevent, 'DTEND'));

        $vevent = $this->getEvent($this->getCalendar([$begin_only]));
        $this->assertSame('20261105T180000Z', $this->getValue($vevent, 'DTSTART'));
        $this->assertNull($this->getValue($vevent, 'DTEND'));

        $vevent = $this->getEvent($this->getCalendar([$begin_only_days]));
        $this->assertSame('20261105T180000Z', $this->getValue($vevent, 'DTSTART'));
        $this->assertSame('20261106T230000Z', $this->getValue($vevent, 'DTEND'));

        $vevent = $this->getEvent($this->getCalendar([$summer]));
        $this->assertSame('20260701T080000Z', $this->getValue($vevent, 'DTSTART'));
        $this->assertSame('20260701T100000Z', $this->getValue($vevent, 'DTEND'));
    }

    /**
     * Location, description, group and link
     */
    public function testDetails(): void
    {
        $group = $this->createGroup('Board');
        $full = $this->insertEvent('Full', [
            'address'   => '12 rue de la Soif',
            'zip'       => '35000',
            'town'      => 'Rennes',
            'country'   => 'France',
            'comment'   => "Bring your own bike, tools; and \"good\" mood.\nSecond line",
            'id_group'  => $group->getId(),
        ]);
        $this->linkActivity($full, $this->insertActivity('Meal'));
        $this->linkActivity($full, $this->insertActivity('Lodging'), \GaletteEvents\Activity::REQUIRED);
        $this->linkActivity($full, $this->insertActivity('Not offered'), \GaletteEvents\Activity::NO);
        $town_only = $this->insertEvent('Town only', ['address' => '', 'zip' => '', 'country' => null, 'comment' => '']);

        $vevent = $this->getEvent($this->getCalendar([$full]));
        $this->assertSame('12 rue de la Soif, 35000 Rennes, France', $this->getValue($vevent, 'LOCATION'));
        $this->assertSame(
            "Bring your own bike, tools; and \"good\" mood.\nSecond line\n\nActivities\n- Meal\n- Lodging",
            $this->getValue($vevent, 'DESCRIPTION')
        );
        $this->assertSame('Board', $this->getValue($vevent, 'CATEGORIES'));
        $this->assertStringEndsWith(
            $this->routeparser->urlFor('events_booking_add') . '?event=' . $full,
            $this->getValue($vevent, 'URL')
        );

        $vevent = $this->getEvent($this->getCalendar([$town_only]));
        $this->assertSame('Lille', $this->getValue($vevent, 'LOCATION'));
        $this->assertNull($this->getValue($vevent, 'DESCRIPTION'));
        $this->assertNull($this->getValue($vevent, 'CATEGORIES'));
    }

    /**
     * Events UIDs are stable for an instance
     */
    public function testUid(): void
    {
        $this->preferences->pref_instance_uuid = '';
        $first = $this->insertEvent('First');
        $second = $this->insertEvent('Second');

        $calendar = $this->getCalendar([$first, $second]);
        $uuid = $this->preferences->pref_instance_uuid;
        $this->assertNotEmpty($uuid);
        $uids = array_map(fn(VEvent $vevent): ?string => $this->getValue($vevent, 'UID'), $calendar->select('VEVENT'));
        $this->assertSame(['event-' . $first . '@' . $uuid, 'event-' . $second . '@' . $uuid], $uids);

        $calendar = $this->getCalendar([$first]);
        $this->assertSame('event-' . $first . '@' . $uuid, $this->getValue($this->getEvent($calendar), 'UID'));
        $this->assertSame('PUBLISH', $this->getValue($calendar, 'METHOD'));
    }

    /**
     * File names
     */
    public function testFilename(): void
    {
        $event = new \GaletteEvents\Event($this->zdb, $this->login, $this->history, $this->insertEvent('Fête de fin d\'année'));
        $this->assertSame('fete-de-fin-d-annee.ics', \GaletteEvents\Ics::getFilename($event));
        $event = new \GaletteEvents\Event($this->zdb, $this->login, $this->history, $this->insertEvent('???'));
        $this->assertSame('event-' . $event->getId() . '.ics', \GaletteEvents\Ics::getFilename($event));
    }
}
