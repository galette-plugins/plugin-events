<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\tests\units;

use Galette\Core\Plugins\FixturesContext;
use Galette\Tests\GaletteTestCase;
use GaletteEvents\tests\EventsFixtures;

/**
 * Fixtures tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Fixtures extends GaletteTestCase
{
    use EventsFixtures;

    protected int $seed = 20261004150000;

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
     * Count rows of a plugin table
     *
     * @param string $table Table name, without prefixes
     */
    private function countRows(string $table): int
    {
        return $this->zdb->execute($this->zdb->select(EVENTS_PREFIX . $table))->count();
    }

    /**
     * Test seeding and cleaning fixtures
     */
    public function testFixtures(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $bureau = $this->createGroup('Fixtures bureau');
        $conseil = $this->createGroup('Fixtures conseil');
        $this->logSuperAdmin();

        //an event of the user, using an activity fixtures also use
        $activity = new \GaletteEvents\Activity($this->zdb, $this->history);
        $this->assertTrue($activity->check(['name' => 'Repas', 'active' => '1']));
        $activity->store();
        $event = new \GaletteEvents\Event($this->zdb, $this->login, $this->history);
        $this->assertTrue($event->check([
            'name' => 'User event',
            'town' => 'Lille',
            'begin_date' => date('Y-m-d', strtotime('+3 days')),
            'activities_ids' => [(string)$activity->getId()],
            'activities_status' => ['1'],
        ]));
        $event->store();

        $context = new FixturesContext(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            history: $this->history,
            plugins: $this->plugins,
            members: ['one' => $member_one->id, 'two' => $member_two->id],
            groups: ['bureau' => (int)$bureau->getId(), 'conseil' => (int)$conseil->getId()]
        );

        $fixtures = new \GaletteEvents\Fixtures();
        $summary = $fixtures->seedFixtures($context);
        $this->assertSame(sprintf('Created 10 events and %d bookings', $this->countRows('bookings')), $summary);
        $this->assertSame(11, $this->countRows('events'));
        //existing activity is reused
        $this->assertSame(4, $this->countRows('activities'));

        $today = date('Y-m-d');
        $select = $this->zdb->select(EVENTS_PREFIX . \GaletteEvents\Event::TABLE);
        $select->where->lessThan('begin_date', $today);
        $this->assertSame(3, $this->zdb->execute($select)->count());

        //events are announced, and bookings made, before they begin
        foreach ($this->zdb->execute($this->zdb->select(EVENTS_PREFIX . \GaletteEvents\Event::TABLE)) as $row) {
            if ($row->name === 'User event') {
                continue;
            }
            $this->assertLessThan($row->begin_date, $row->creation_date, $row->name);
            $this->assertLessThan($today, $row->creation_date, $row->name);
        }
        $select = $this->zdb->select(EVENTS_PREFIX . \GaletteEvents\Booking::TABLE, 'b')
            ->join(['e' => PREFIX_DB . EVENTS_PREFIX . \GaletteEvents\Event::TABLE], 'b.id_event = e.id_event', ['begin_date']);
        $bookings = $this->zdb->execute($select);
        $this->assertGreaterThan(0, $bookings->count());
        foreach ($bookings as $row) {
            $this->assertContains((int)$row->id_adh, [$member_one->id, $member_two->id]);
            $this->assertLessThanOrEqual($today, $row->booking_date);
            $this->assertLessThanOrEqual($row->begin_date, $row->booking_date);
        }

        $select = $this->zdb->select(EVENTS_PREFIX . \GaletteEvents\Event::TABLE)
            ->where(['name' => 'Réunion du bureau']);
        $this->assertSame($bureau->getId(), (int)$this->zdb->execute($select)->current()->id_group);

        $select = $this->zdb->select(EVENTS_PREFIX . \GaletteEvents\Event::TABLE)
            ->where(['name' => 'Voyage à Rome']);
        $this->assertFalse((bool)$this->zdb->execute($select)->current()->is_open);

        $fixtures->cleanFixtures($context);
        //only the user event and its activity remain
        $this->assertSame(1, $this->countRows('events'));
        $this->assertSame(0, $this->countRows('bookings'));
        $this->assertSame(1, $this->countRows('activities'));
        $this->assertSame($activity->getId(), (int)$this->zdb->execute(
            $this->zdb->select(EVENTS_PREFIX . \GaletteEvents\Activity::TABLE)
        )->current()->id_activity);
    }
}
