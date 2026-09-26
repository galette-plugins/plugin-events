<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\Controllers\Crud\tests\units;

use Galette\Tests\GaletteRoutingTestCase;
use GaletteEvents\Activity;
use GaletteEvents\tests\EventsFixtures;

/**
 * Activities controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ActivitiesController extends GaletteRoutingTestCase
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
     * Count activities with given name
     *
     * @param string $name Activity name
     */
    private function countActivities(string $name): int
    {
        $select = $this->zdb->select(EVENTS_PREFIX . Activity::TABLE);
        $select->where(['name' => $name]);
        return $this->zdb->execute($select)->count();
    }

    /**
     * Post an activity
     *
     * @param array<string,string> $data Posted data
     */
    private function postActivity(array $data): \Psr\Http\Message\ResponseInterface
    {
        $request = $this->createRequest('events_storeactivity_add', [], 'POST')
            ->withParsedBody($data);
        return $this->app->handle($request);
    }

    /**
     * Visitors can neither create nor change activities
     */
    public function testVisitorCannotStoreActivity(): void
    {
        $id = $this->insertActivity('Dinner');

        $this->expectLogin($this->postActivity(['name' => 'Created by a visitor', 'active' => '1', 'comment' => '']));
        $this->expectLogin($this->postActivity(['id' => (string)$id, 'name' => 'Renamed by a visitor', 'comment' => '']));

        $this->assertSame(0, $this->countActivities('Created by a visitor'));
        $this->assertSame(0, $this->countActivities('Renamed by a visitor'));
        $this->assertSame(1, $this->countActivities('Dinner'));
    }

    /**
     * Members cannot create activities
     */
    public function testMemberCannotStoreActivity(): void
    {
        $this->getMemberOne();
        $this->logMember($this->dataAdherentOne());

        $this->expectAuthMiddlewareRefused($this->postActivity(['name' => 'Created by a member', 'active' => '1', 'comment' => '']));
        $this->assertSame(0, $this->countActivities('Created by a member'));
    }
}
