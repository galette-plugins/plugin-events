<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\tests\units;

use Galette\Tests\GaletteTestCase;

use function PHPUnit\Framework\assertSame;

/**
 * Color tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activity extends GaletteTestCase
{
    protected int $seed = 20240517203521;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(EVENTS_PREFIX . \GaletteEvents\Activity::TABLE);
        $this->zdb->execute($delete);
        parent::tearDown();
    }

    /**
     * Test empty
     */
    public function testEmpty(): void
    {
        $activity = new \GaletteEvents\Activity($this->zdb, $this->login);

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
        $activity = new \GaletteEvents\Activity($this->zdb, $this->login);
        $activities = new \GaletteEvents\Repository\Activities($this->zdb, $this->login, $this->preferences);

        //ensure the table is empty
        $this->assertCount(0, $activities->getList());

        //required activity name
        $data = [
            'comment' => 'Test comment',
        ];
        $this->assertFalse($activity->check($data));
        $this->assertSame(['Name is mandatory'], $activity->getErrors());
        $this->expectLogEntry(
            \Analog::ERROR,
            'Name is mandatory',
        );

        //add new activity
        $data = [
            'name' => 'Test activity',
            'comment' => 'Test comment',
        ];
        $this->assertTrue($activity->check($data));
        $this->assertTrue($activity->store());
        $first_id = $activity->getId();
        $this->assertGreaterThan(0, $first_id);

        $this->assertTrue($activity->load($first_id));
        $this->assertSame('Test activity', $activity->getName());
        $this->assertSame('Test comment', $activity->getComment());
        $this->assertFalse($activity->isActive());
        $this->assertSame(0, $activity->countEvents());
        //FIXME: lang must be changed to have a different date format
        $this->assertNotSame('', $activity->getCreationDate());
        $this->assertNotSame('', $activity->getCreationDate(false));

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
        $this->assertTrue($activity->store());
        $this->assertTrue($activity->load($first_id));

        $this->assertSame('Test activity edited', $activity->getName());
        $this->assertTrue($activity->isActive());

        /*$color = new \GaletteAuto\Color($this->zdb);

        $this->assertCount(1, $color->getList());
        $listed_color = $color->getList()[0];
        $this->assertInstanceOf(\ArrayObject::class, $listed_color);
        $this->assertGreaterThan(0, $listed_color->id_color);
        $this->assertSame('Red', $listed_color->color);
        $this->assertSame('1 color', $color->displayCount());

        //add another one
        $color = new \GaletteAuto\Color($this->zdb);
        $color->value = 'Blu';
        $this->assertTrue($color->store(true));
        $id = $color->id;

        $this->assertCount(2, $color->getList());
        $this->assertSame('2 colors', $color->displayCount());

        $color = new \GaletteAuto\Color($this->zdb);
        $this->assertTrue($color->load($id));
        $color->value = 'Blue';
        $this->assertTrue($color->store());

        $this->assertCount(2, $color->getList());
        $this->assertSame('2 colors', $color->displayCount());

        $color = new \GaletteAuto\Color($this->zdb);
        $this->assertTrue($color->delete([$first_id]));
        $list = $color->getList();
        $this->assertCount(1, $list);
        $last_color = $list[0];
        $this->assertSame($id, $last_color->id_color);*/
    }

    /**
     * Test load error
     */
    public function testLoadError(): void
    {
        $activity = new \GaletteEvents\Activity($this->zdb, $this->login);
        $this->assertFalse($activity->load(999));
    }

    /**
     * Activities are stored without comment, and loaded with a NULL one
     */
    public function testNoComment(): void
    {
        $activity = new \GaletteEvents\Activity($this->zdb, $this->login);
        $this->assertTrue($activity->check(['name' => 'Dinner', 'active' => '1']));
        $this->assertTrue($activity->store());

        $update = $this->zdb->update(EVENTS_PREFIX . \GaletteEvents\Activity::TABLE);
        $update->set(['comment' => null])->where([\GaletteEvents\Activity::PK => $activity->getId()]);
        $this->zdb->execute($update);

        $activity = new \GaletteEvents\Activity($this->zdb, $this->login, (int)$activity->getId());
        $this->assertSame('Dinner', $activity->getName());
        $this->assertSame('', $activity->getComment());
    }
}
