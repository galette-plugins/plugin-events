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

/**
 * Activity entity tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activity extends GaletteTestCase
{
    use EventsFixtures;

    protected int $seed = 20240517203521;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->cleanEvents();
        parent::tearDown();
    }

    /**
     * Test empty
     */
    public function testEmpty(): void
    {
        $activity = new \GaletteEvents\Activity($this->zdb, $this->history);

        $this->assertNull($activity->getId());
        $this->assertSame('', $activity->getName());
        $this->assertSame('', $activity->getCreationDate());
        $this->assertFalse($activity->isActive());
        $this->assertSame('', $activity->getComment());
        $this->assertSame(0, $activity->countEvents());
    }

    /**
     * Test add and update
     */
    public function testCrud(): void
    {
        $activity = new \GaletteEvents\Activity($this->zdb, $this->history);
        $activities = new \GaletteEvents\Repository\Activities($this->zdb, $this->login, $this->history, $this->preferences);

        //ensure the table is empty
        $this->assertCount(0, $activities->getList());

        //required activity name
        $data = [
            'comment' => 'Test comment',
        ];
        $this->assertFalse($activity->check($data));
        $this->assertSame(['Name is mandatory'], $activity->getErrors());
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Name is mandatory',
        );

        //add new activity
        $data = [
            'name' => 'Test activity',
            'comment' => 'Test comment',
        ];
        $this->assertTrue($activity->check($data));
        $activity->store();
        $first_id = $activity->getId();
        $this->assertGreaterThan(0, $first_id);
        //creation date column holds no time
        $this->assertSame(date('Y-m-d'), $activity->getCreationDate());

        $activity->load($first_id);
        $this->assertSame('Test activity', $activity->getName());
        $this->assertSame('Test comment', $activity->getComment());
        $this->assertFalse($activity->isActive());
        $this->assertSame(0, $activity->countEvents());
        $this->assertSame(date('Y-m-d'), $activity->getCreationDate());

        $activities_list = $activities->getList();
        $this->assertCount(1, $activities_list);
        $this->assertSame(1, $activities->getCount());
        $lactivity = $activities_list[0];
        $this->assertInstanceOf(\GaletteEvents\Activity::class, $lactivity);
        $this->assertEquals($activity, $lactivity);

        //edit activity
        $data['active'] = true;
        $data['name'] = 'Test activity edited';
        $this->assertTrue($activity->check($data));
        $activity->store();
        $activity->load($first_id);

        $this->assertSame('Test activity edited', $activity->getName());
        $this->assertTrue($activity->isActive());
    }

    /**
     * Test load error
     */
    public function testLoadError(): void
    {
        $activity = new \GaletteEvents\Activity($this->zdb, $this->history);
        $this->expectException(\GaletteEvents\NotFoundException::class);
        $activity->load(999);
    }

    /**
     * Activities are stored without comment, and loaded with a NULL one
     */
    public function testNoComment(): void
    {
        $activity = new \GaletteEvents\Activity($this->zdb, $this->history);
        $this->assertTrue($activity->check(['name' => 'Dinner', 'active' => '1']));
        $activity->store();

        $update = $this->zdb->update(EVENTS_PREFIX . \GaletteEvents\Activity::TABLE);
        $update->set(['comment' => null])->where([\GaletteEvents\Activity::PK => $activity->getId()]);
        $this->zdb->execute($update);

        $activity = new \GaletteEvents\Activity($this->zdb, $this->history, (int)$activity->getId());
        $this->assertSame('Dinner', $activity->getName());
        $this->assertSame('', $activity->getComment());
    }

    /**
     * Activities count their events, and are removed with their links
     */
    public function testCountAndRemove(): void
    {
        $id = $this->insertActivity('Dinner');
        $this->linkActivity($this->insertEvent('First event'), $id);
        $this->linkActivity($this->insertEvent('Second event'), $id);

        $activity = new \GaletteEvents\Activity($this->zdb, $this->history, $id);
        $this->assertSame(2, $activity->countEvents());
        $activity->remove();
        $this->expectException(\GaletteEvents\NotFoundException::class);
        (new \GaletteEvents\Activity($this->zdb, $this->history))->load($id);

        $select = $this->zdb->select(EVENTS_PREFIX . 'activitiesevents');
        $select->where([\GaletteEvents\Activity::PK => $id]);
        $this->assertSame(0, $this->zdb->execute($select)->count());
    }
}
