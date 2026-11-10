<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents;

use Galette\Core\Plugins\FixturesContext;
use Galette\Core\Plugins\FixturesProviderInterface;
use Galette\Entity\PaymentType;
use RuntimeException;

/**
 * Sample activities, events and bookings for fixture members, run by galette:seed-fixtures
 *
 * Activities are looked up by name and created if missing; events are found
 * back by their name. Dates are relative to the current day: a few events
 * are over, most of them are to come.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @phpstan-type EventData array{
 *     name: string,
 *     town: string,
 *     zip: string,
 *     address: string,
 *     begin: int,
 *     days: int,
 *     color: string,
 *     activities: array<string, int>,
 *     bookings: int,
 *     group?: int,
 *     closed?: bool,
 *     comment?: string
 * }
 */
class Fixtures implements FixturesProviderInterface
{
    /** @var array<string, string> Activities, with their comment */
    private const array ACTIVITIES = [
        'Repas' => 'Repas pris en commun',
        'Hébergement' => 'Nuitée en gîte ou en auberge',
        'Visite guidée' => 'Visite avec un guide local',
        'Covoiturage' => 'Départ groupé depuis le local',
    ];

    /** @var list<EventData> Events; begin in days from today, group and bookings members by position */
    private const array EVENTS = [
        ['name' => 'Pique-nique de rentrée', 'town' => 'Bordeaux', 'zip' => '33000',
            'address' => 'Parc bordelais', 'begin' => -35, 'days' => 0, 'color' => '#2e7d32',
            'activities' => ['Repas' => Activity::REQUIRED], 'bookings' => 12],
        ['name' => 'Atelier réparation de vélos', 'town' => 'Rennes', 'zip' => '35000',
            'address' => '12 rue de la Soif', 'begin' => -20, 'days' => 0, 'color' => '#6d4c41',
            'activities' => [], 'bookings' => 6],
        ['name' => 'Conseil d\'administration', 'town' => 'Lyon', 'zip' => '69002',
            'address' => 'Salle du conseil', 'begin' => -60, 'days' => 0, 'color' => '#455a64',
            'activities' => [], 'bookings' => 4, 'group' => 1],
        ['name' => 'Forum des associations', 'town' => 'Lille', 'zip' => '59000',
            'address' => 'Grand Palais', 'begin' => 7, 'days' => 1, 'color' => '#1565c0',
            'activities' => ['Covoiturage' => Activity::YES], 'bookings' => 8,
            'comment' => 'Venez tenir le stand avec nous !'],
        ['name' => 'Réunion du bureau', 'town' => 'Paris', 'zip' => '75011',
            'address' => 'Local associatif', 'begin' => 10, 'days' => 0, 'color' => '#455a64',
            'activities' => [], 'bookings' => 5, 'group' => 0],
        ['name' => 'Assemblée générale', 'town' => 'Paris', 'zip' => '75011',
            'address' => 'Salle des fêtes', 'begin' => 21, 'days' => 0, 'color' => '#c62828',
            'activities' => ['Repas' => Activity::YES], 'bookings' => 15,
            'comment' => 'Ordre du jour envoyé par courriel.'],
        ['name' => 'Sortie vélo en Camargue', 'town' => 'Arles', 'zip' => '13200',
            'address' => 'Place de la République', 'begin' => 45, 'days' => 2, 'color' => '#ef6c00',
            'activities' => [
                'Hébergement' => Activity::REQUIRED,
                'Repas' => Activity::YES,
                'Covoiturage' => Activity::YES,
            ], 'bookings' => 10],
        ['name' => 'Fête de fin d\'année', 'town' => 'Nantes', 'zip' => '44000',
            'address' => 'Hangar à bananes', 'begin' => 80, 'days' => 0, 'color' => '#ad1457',
            'activities' => ['Repas' => Activity::REQUIRED], 'bookings' => 14],
        ['name' => 'Week-end découverte à Strasbourg', 'town' => 'Strasbourg', 'zip' => '67000',
            'address' => 'Place Kléber', 'begin' => 120, 'days' => 1, 'color' => '#6a1b9a',
            'activities' => [
                'Hébergement' => Activity::YES,
                'Visite guidée' => Activity::YES,
                'Repas' => Activity::NO,
            ], 'bookings' => 7],
        ['name' => 'Voyage à Rome', 'town' => 'Rome', 'zip' => '00184',
            'address' => 'Colisée', 'begin' => 150, 'days' => 4, 'color' => '#f9a825',
            'activities' => ['Hébergement' => Activity::REQUIRED, 'Visite guidée' => Activity::REQUIRED],
            'bookings' => 9, 'closed' => true, 'comment' => 'Complet, inscriptions closes.'],
    ];

    /** @var array<string, int> Activities identifiers, by name */
    private array $activities = [];

    /**
     * Create activities, events and their bookings
     *
     * @param FixturesContext $context Fixtures context
     */
    public function seedFixtures(FixturesContext $context): string
    {
        foreach (self::ACTIVITIES as $name => $comment) {
            $this->activities[$name] = $this->getActivityId($context, $name, $comment);
        }

        $bookings = 0;
        foreach (self::EVENTS as $position => $data) {
            $event = $this->addEvent($context, $data);
            $bookings += $this->addBookings($context, $event, $data, $position);
        }

        return sprintf('Created %d events and %d bookings', count(self::EVENTS), $bookings);
    }

    /**
     * Get activity identifier, create it if missing
     *
     * @param FixturesContext $context Fixtures context
     * @param string          $name    Activity name
     * @param string          $comment Activity comment
     */
    private function getActivityId(FixturesContext $context, string $name, string $comment): int
    {
        $select = $context->zdb->select(EVENTS_PREFIX . Activity::TABLE)
            ->columns([Activity::PK])
            ->where(['name' => $name])
            ->limit(1);
        $row = $context->zdb->execute($select)->current();
        if ($row) {
            return (int)$row->{Activity::PK};
        }

        $activity = new Activity($context->zdb, $context->history);
        if (!$activity->check(['name' => $name, 'comment' => $comment, 'active' => '1'])) {
            throw new RuntimeException(sprintf('Invalid fixture activity %s', $name));
        }
        $activity->store();

        //created a while ago
        $update = $context->zdb->update(EVENTS_PREFIX . Activity::TABLE)
            ->set(['creation_date' => $this->getDate(-400)])
            ->where([Activity::PK => $activity->getId()]);
        $context->zdb->execute($update);

        return (int)$activity->getId();
    }

    /**
     * Create an event
     *
     * @param FixturesContext $context Fixtures context
     * @param EventData       $data    Event data
     */
    private function addEvent(FixturesContext $context, array $data): Event
    {
        $values = [
            'name' => $data['name'],
            'address' => $data['address'],
            'zip' => $data['zip'],
            'town' => $data['town'],
            'begin_date' => $this->getDate($data['begin']),
            'end_date' => $this->getDate($data['begin'] + $data['days']),
            'color' => $data['color'],
            'comment' => $data['comment'] ?? '',
            'activities_ids' => [],
            'activities_status' => [],
        ];
        if (!($data['closed'] ?? false)) {
            $values['open'] = '1';
        }
        if (isset($data['group'])) {
            $values['group'] = (string)$context->getGroupId($data['group']);
        }
        foreach ($data['activities'] as $name => $status) {
            $values['activities_ids'][] = (string)$this->activities[$name];
            $values['activities_status'][] = (string)$status;
        }

        $event = new Event($context->zdb, $context->login, $context->history);
        if (!$event->check($values)) {
            throw new RuntimeException(sprintf(
                'Invalid fixture event %s: %s',
                $data['name'],
                implode(', ', $event->getErrors())
            ));
        }
        $event->store();

        //announced a while before it begins
        $update = $context->zdb->update(EVENTS_PREFIX . Event::TABLE)
            ->set(['creation_date' => $this->getDate(min(-1, $data['begin'] - 60))])
            ->where([Event::PK => $event->getId()]);
        $context->zdb->execute($update);

        return $event;
    }

    /**
     * Book event for some fixture members
     *
     * @param FixturesContext $context  Fixtures context
     * @param Event           $event    Event
     * @param EventData       $data     Event data
     * @param int             $position Event position
     *
     * @return int Number of bookings created
     */
    private function addBookings(FixturesContext $context, Event $event, array $data, int $position): int
    {
        $members = [];
        for ($i = 0; $i < min($data['bookings'], count($context->members)); ++$i) {
            $member = $context->getMemberId($position * 5 + $i * 3);
            if ($member !== null) {
                $members[$member] = $i;
            }
        }

        //booked between the announce and the event, or today
        $announced = min(-1, $data['begin'] - 60);
        $latest = min(0, $data['begin'] - 1);
        foreach ($members as $member => $i) {
            $values = [
                'event' => (string)$event->getId(),
                'member' => (string)$member,
                'booking_date' => $this->getDate($announced + (($i * 7) % max(1, $latest - $announced + 1))),
                'number_people' => (string)(1 + ($i % 3 === 0 ? 1 : 0)),
                'activities' => [],
            ];
            foreach ($data['activities'] as $name => $status) {
                if ($status === Activity::REQUIRED || ($status === Activity::YES && $i % 2 === 0)) {
                    $values['activities'][] = (string)$this->activities[$name];
                }
            }
            //activities cost 15 each, a third of bookings are not paid yet
            $amount = 15 * count($values['activities']) * (int)$values['number_people'];
            if ($amount > 0) {
                $values['amount'] = (string)$amount;
                if ($i % 3 !== 2) {
                    $values['paid'] = '1';
                    $values['payment_method'] = (string)($i % 2 === 0 ? PaymentType::CHECK : PaymentType::CASH);
                }
            }

            $booking = new Booking($context->zdb, $context->login, $context->history);
            if (!$booking->check($values)) {
                throw new RuntimeException(sprintf(
                    'Invalid fixture booking for %s: %s',
                    $data['name'],
                    implode(', ', $booking->getErrors())
                ));
            }
            $booking->store();

            $update = $context->zdb->update(EVENTS_PREFIX . Booking::TABLE)
                ->set(['creation_date' => $values['booking_date']])
                ->where([Booking::PK => $booking->getId()]);
            $context->zdb->execute($update);
        }

        return count($members);
    }

    /**
     * Get date from a number of days relative to today
     *
     * @param int $days Days, negative in the past
     */
    private function getDate(int $days): string
    {
        return (new \DateTimeImmutable('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
    }

    /**
     * Remove fixture events, with their bookings, then activities nothing uses anymore
     *
     * @param FixturesContext $context Fixtures context
     */
    public function cleanFixtures(FixturesContext $context): void
    {
        $zdb = $context->zdb;

        //bookings and their activities follow
        $delete = $zdb->delete(EVENTS_PREFIX . Event::TABLE);
        $delete->where->in('name', array_column(self::EVENTS, 'name'));
        $zdb->execute($delete);

        $select = $zdb->select(EVENTS_PREFIX . 'activitiesevents')
            ->columns([Activity::PK])
            ->quantifier('DISTINCT');
        $used = [];
        foreach ($zdb->execute($select) as $row) {
            $used[] = (int)$row->{Activity::PK};
        }

        $delete = $zdb->delete(EVENTS_PREFIX . Activity::TABLE);
        $delete->where->in('name', array_keys(self::ACTIVITIES));
        if ($used !== []) {
            $delete->where->notIn(Activity::PK, $used);
        }
        $zdb->execute($delete);
    }
}
