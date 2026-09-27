<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Repository\tests\units;

use Galette\Tests\GaletteTestCase;
use GaletteEvents\Filters\EventsList;
use GaletteEvents\tests\EventsFixtures;

/**
 * Events repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Events extends GaletteTestCase
{
    use EventsFixtures;

    protected int $seed = 20260926151512;

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
     * Get names of listed events
     *
     * @param bool $bookable Bookable events only
     *
     * @return array<string>
     */
    private function getListed(bool $bookable = false): array
    {
        $events = new \GaletteEvents\Repository\Events($this->zdb, $this->login, $this->history);
        $names = [];
        foreach ($events->getList($bookable) as $event) {
            $this->assertInstanceOf(\GaletteEvents\Event::class, $event);
            $names[] = (string)$event->getName();
        }
        sort($names);
        return $names;
    }

    /**
     * Get names of events displayed in calendar, from last month to next one
     *
     * @return array<string>
     */
    private function getCalendar(): array
    {
        $filters = new EventsList();
        $filters->calendar_filter = true;
        $filters->start_date_filter = date(__('Y-m-d'), strtotime('-1 month'));
        $filters->end_date_filter = date(__('Y-m-d'), strtotime('+1 month'));
        $events = new \GaletteEvents\Repository\Events($this->zdb, $this->login, $this->history, $filters);
        $names = [];
        foreach ($events->getList(false, true) as $event) {
            $this->assertInstanceOf(\ArrayObject::class, $event);
            $names[] = $event['name'];
        }
        sort($names);
        return $names;
    }

    /**
     * Events listed, bookable and displayed in calendar, depending on who is logged in
     */
    public function testVisibility(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $own = $this->createGroup('Own group', [], [$member_one]);
        $other = $this->createGroup('Other group', [], [$member_two]);
        $managed = $this->createGroup('Managed group', [$member_two]);
        $past = [
            'begin_date'    => date('Y-m-d', strtotime('-10 days')),
            'end_date'      => date('Y-m-d', strtotime('-9 days')),
        ];

        $this->insertEvent('public');
        $this->insertEvent('public closed', ['is_open' => false]);
        $this->insertBooking($this->insertEvent('public past', $past), $member_one->id);
        $this->insertEvent(
            'public far',
            ['begin_date' => date('Y-m-d', strtotime('+3 months')), 'end_date' => date('Y-m-d', strtotime('+3 months'))]
        );
        $this->insertEvent('own group', ['id_group' => $own->getId()]);
        $this->insertEvent('other group', ['id_group' => $other->getId()]);
        $this->insertEvent('managed', ['id_group' => $managed->getId()]);
        $this->insertEvent('managed past closed', ['id_group' => $managed->getId(), 'is_open' => false] + $past);

        $this->logMember($this->dataAdherentOne());
        $this->assertSame(['own group', 'public', 'public far', 'public past'], $this->getListed());
        $this->assertSame(['own group', 'public', 'public far'], $this->getListed(true));
        $this->assertSame(['own group', 'public', 'public closed', 'public past'], $this->getCalendar());
        $this->login->logout();

        //member two manages a group, and belongs to another one
        $this->logMember($this->dataAdherentTwo());
        $this->assertSame(
            ['managed', 'managed past closed', 'other group', 'public', 'public far'],
            $this->getListed()
        );
        $this->assertSame(['managed', 'other group', 'public', 'public far'], $this->getListed(true));
        $this->assertSame(
            ['managed', 'managed past closed', 'other group', 'public', 'public closed', 'public past'],
            $this->getCalendar()
        );
        $this->login->logout();

        $this->logSuperAdmin();
        $all = [
            'managed', 'managed past closed', 'other group', 'own group',
            'public', 'public closed', 'public far', 'public past'
        ];
        $this->assertSame($all, $this->getListed());
        $this->assertSame($all, $this->getListed(true));
        $this->assertSame(array_values(array_diff($all, ['public far'])), $this->getCalendar());
    }
}
