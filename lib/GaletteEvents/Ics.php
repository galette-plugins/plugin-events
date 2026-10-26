<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents;

use DateTimeImmutable;
use DateTimeZone;
use Galette\Core\Preferences;
use Galette\Util\Text;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Slim\Interfaces\RouteParserInterface;

/**
 * Events as iCalendar (RFC 5545) data
 *
 * Events without times last all day long, with an exclusive end date.
 * Times are local to the server, they are given in UTC.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Ics
{
    public const string CONTENT_TYPE = 'text/calendar; charset=utf-8';

    /**
     * Default constructor
     *
     * @param Preferences          $preferences Preferences instance
     * @param RouteParserInterface $routeparser Route parser
     */
    public function __construct(
        private readonly Preferences $preferences,
        private readonly RouteParserInterface $routeparser
    ) {
    }

    /**
     * Build calendar
     *
     * @param array<Event> $events Events to include
     */
    public function getCalendar(array $events): VCalendar
    {
        $calendar = new VCalendar([
            'PRODID'    => '-//Galette//Galette Events//EN',
            'CALSCALE'  => 'GREGORIAN',
            'METHOD'    => 'PUBLISH',
        ]);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $domain = $this->getUidDomain();
        foreach ($events as $event) {
            $this->addEvent($calendar, $event, $domain, $now);
        }

        return $calendar;
    }

    /**
     * Add an event to the calendar
     *
     * @param VCalendar         $calendar Calendar
     * @param Event             $event    Event to add
     * @param string            $domain   UID domain
     * @param DateTimeImmutable $now      Calendar generation time
     */
    private function addEvent(VCalendar $calendar, Event $event, string $domain, DateTimeImmutable $now): void
    {
        /** @var VEvent $vevent */
        $vevent = $calendar->add('VEVENT', [
            'UID'       => 'event-' . $event->getId() . '@' . $domain,
            'DTSTAMP'   => $now,
            'SUMMARY'   => $event->getName(),
        ]);

        if ($event->isAllDay()) {
            $vevent->add('DTSTART', $this->formatDate($event->getBeginDate()), ['VALUE' => 'DATE']);
            $vevent->add('DTEND', $this->formatDate($event->getEndDate(), '+1 day'), ['VALUE' => 'DATE']);
        } else {
            $vevent->add('DTSTART', $this->toUtc($event->getBeginDate() . ' ' . $event->getBeginTime()));
            if ($event->getEndTime() !== null) {
                $vevent->add('DTEND', $this->toUtc($event->getEndDate() . ' ' . $event->getEndTime()));
            } elseif ($event->getEndDate() !== $event->getBeginDate()) {
                //no end time: event lasts until the end of its last day
                $vevent->add('DTEND', $this->toUtc($event->getEndDate() . ' 00:00', '+1 day'));
            }
        }

        $location = $this->getLocation($event);
        if ($location !== '') {
            $vevent->add('LOCATION', $location);
        }

        $description = $this->getDescription($event);
        if ($description !== '') {
            $vevent->add('DESCRIPTION', $description);
        }

        if ($event->getGroup() !== null) {
            $vevent->add('CATEGORIES', $event->getGroupName());
        }

        $vevent->add(
            'URL',
            rtrim($this->preferences->getURL(), '/')
                . $this->routeparser->urlFor('events_booking_add')
                . '?event=' . $event->getId()
        );
    }

    /**
     * Get event location, from its non empty address parts
     *
     * @param Event $event Event
     */
    private function getLocation(Event $event): string
    {
        $town = trim($event->getZip() . ' ' . $event->getTown());
        return implode(', ', array_filter(
            [trim($event->getAddress()), $town, trim($event->getCountry() ?? '')],
            fn(string $part): bool => $part !== ''
        ));
    }

    /**
     * Get event description, as plain text
     *
     * @param Event $event Event
     */
    private function getDescription(Event $event): string
    {
        $parts = [];
        if (trim($event->getComment()) !== '') {
            $parts[] = trim($event->getComment());
        }

        $activities = array_map(
            fn(array $activity): string => '- ' . $activity['activity']->getName(),
            $event->getActivities()
        );
        if (count($activities)) {
            $parts[] = _T('Activities', 'events') . "\n" . implode("\n", $activities);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Domain part of events UIDs, that must not change for an instance
     */
    private function getUidDomain(): string
    {
        $uuid = $this->preferences->pref_instance_uuid;
        if (empty($uuid)) {
            $uuid = $this->preferences->generateUUID('instance');
        }
        return $uuid;
    }

    /**
     * Format a date for an all day event
     *
     * @param string $date   Date, as Y-m-d
     * @param string $modify Modifier to apply, if any
     */
    private function formatDate(string $date, string $modify = ''): string
    {
        $date = new DateTimeImmutable($date);
        if ($modify !== '') {
            $date = $date->modify($modify);
        }
        return $date->format('Ymd');
    }

    /**
     * Server local date and time to UTC
     *
     * @param string $datetime Date and time, as Y-m-d H:i
     * @param string $modify   Modifier to apply, if any
     */
    private function toUtc(string $datetime, string $modify = ''): DateTimeImmutable
    {
        $date = new DateTimeImmutable($datetime);
        if ($modify !== '') {
            $date = $date->modify($modify);
        }
        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Get file name for an event
     *
     * @param Event $event Event
     */
    public static function getFilename(Event $event): string
    {
        try {
            $name = Text::slugify($event->getName());
        } catch (\RuntimeException) {
            $name = 'event-' . $event->getId();
        }
        return $name . '.ics';
    }
}
