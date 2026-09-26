<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents\tests\units;

use Galette\Tests\GaletteTestCase;

/**
 * Plugin class tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginGaletteEvents extends GaletteTestCase
{
    protected int $seed = 20260926101512;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        parent::tearDown();
    }

    /**
     * Get plugin instance
     */
    private function getPlugin(): \GaletteEvents\PluginGaletteEvents
    {
        return $this->container->get(\GaletteEvents\PluginGaletteEvents::class);
    }

    /**
     * Get routes names of menus entries
     *
     * @param array<string|int, mixed> $menus Menus
     *
     * @return array<string, array<string>>
     */
    private function getMenusRoutes(array $menus): array
    {
        $routes = [];
        foreach ($menus as $section => $menu) {
            $routes[$section] = array_map(
                fn(array $item): string => $item['route']['name'],
                $menu['items']
            );
        }
        return $routes;
    }

    /**
     * Test menus
     */
    public function testMenus(): void
    {
        $plugin = $this->getPlugin();
        $this->assertSame([], $plugin->getMenus());

        $this->getMemberOne();
        $this->assertTrue($this->login->login($this->dataAdherentOne()['login_adh'], $this->dataAdherentOne()['mdp_adh']));
        $this->assertSame(
            ['plugin_events' => ['events_events', 'events_calendar', 'events_bookings']],
            $this->getMenusRoutes($plugin->getMenus())
        );
        $this->login->logout();

        $this->logSuperAdmin();
        $this->assertSame(
            ['plugin_events' => ['events_events', 'events_calendar', 'events_bookings', 'events_activities']],
            $this->getMenusRoutes($plugin->getMenus())
        );
    }
}
