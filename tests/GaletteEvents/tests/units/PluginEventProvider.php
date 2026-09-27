<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\tests\units;

use Galette\Entity\Group;
use Galette\Tests\GaletteRoutingTestCase;
use GaletteEvents\tests\EventsFixtures;

/**
 * Listeners on core events tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginEventProvider extends GaletteRoutingTestCase
{
    use EventsFixtures;

    protected int $seed = 20260927101512;
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
     * Remove a group from core route
     *
     * @param int $group_id Group ID
     */
    private function removeGroup(int $group_id): void
    {
        $this->logSuperAdmin();
        $request = $this->createRequest('doRemoveGroup', ['id' => (string)$group_id], 'POST');
        $request = $request->withParsedBody(['id' => (string)$group_id, 'confirm' => true, 'cascade' => true]);
        $response = $this->app->handle($request);
        $this->login->logout();
        $this->assertSame(301, $response->getStatusCode());
    }

    /**
     * Test a group holding events cannot be removed
     */
    public function testGroupWithEventsIsNotRemoved(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        $group = $this->createGroup('Jedi');
        $group_id = $group->getId();
        $this->insertEvent('Council', ['id_group' => $group_id]);
        $this->insertEvent('Training', ['id_group' => $group_id]);
        $this->insertEvent('Public event');

        $this->removeGroup($group_id);
        $message = 'Group "Jedi" is used by 2 events, it cannot be deleted. Remove the events or change their group first.';
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Group "Jedi" cannot be removed: ' . $message);
        $this->expectFlashData(['error_detected' => [$message]]);
        $this->assertTrue((new Group())->load($group_id));

        //a subgroup holding an event prevents cascade removal of its parent
        $parent = $this->createGroup('Order');
        $group->setParentGroup($parent->getId());
        $this->assertTrue($group->store());
        $this->zdb->execute(
            $this->zdb->delete(EVENTS_PREFIX . \GaletteEvents\Event::TABLE)->where(['name' => 'Training'])
        );

        $this->removeGroup($parent->getId());
        $message = 'Group "Jedi" is used by 1 event, it cannot be deleted. Remove the event or change its group first.';
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Group "Order" cannot be removed: ' . $message);
        $this->expectFlashData(['error_detected' => [$message]]);
        $this->assertTrue((new Group())->load($parent->getId()));
    }

    /**
     * Test a group without events is removed
     */
    public function testGroupWithoutEventsIsRemoved(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        $group_id = $this->createGroup('Sith')->getId();
        $this->insertEvent('Public event');

        $this->removeGroup($group_id);
        $this->expectFlashData(['success_detected' => ['Successfully deleted!']]);
        $this->assertFalse((new Group())->load($group_id));
    }
}
