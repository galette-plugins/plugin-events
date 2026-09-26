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
 * Plugin class tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginGaletteEvents extends GaletteTestCase
{
    use EventsFixtures;

    protected int $seed = 20260926101512;

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

    /**
     * Dashboards and actions
     */
    public function testDashboardsAndActions(): void
    {
        $plugin = $this->getPlugin();
        $this->assertSame([], $plugin->getPublicMenus());
        $this->assertSame([], $plugin->getMyDashboards());
        $this->assertSame([], $plugin->getBatchActions());

        $dashboards = $plugin->getDashboards();
        $this->assertCount(1, $dashboards);
        $this->assertSame(['name' => 'events_calendar'], $dashboards[0]['route']);

        $member = $this->getMemberOne();
        $expected = [
            'name' => 'events_booking_add',
            'args' => ['id_adh' => $member->id]
        ];
        $actions = $plugin->getListActions($member);
        $this->assertCount(1, $actions);
        $this->assertSame($expected, $actions[0]['route']);
        $this->assertSame($actions, $plugin->getDetailedActions($member));
    }

    /**
     * News list upcoming events current user can see
     */
    public function testNews(): void
    {
        $plugin = $this->getPlugin();
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $other = $this->createGroup('Other group', [], [$member_two]);

        $this->logMember($this->dataAdherentOne());
        $this->assertNull($plugin->getNews());

        $this->insertEvent('Upcoming event', ['begin_date' => date('Y-m-d', strtotime('+2 days'))]);
        $this->insertEvent('Closed event', ['is_open' => false]);
        $this->insertEvent('Other group event', ['id_group' => $other->getId()]);
        $this->insertEvent(
            'Past event',
            ['begin_date' => date('Y-m-d', strtotime('-2 days')), 'end_date' => date('Y-m-d', strtotime('-1 day'))]
        );

        $news = $plugin->getNews();
        $this->assertInstanceOf(\Galette\IO\News\Entry::class, $news);
        $this->assertSame('Upcoming events', $news->getTitle());
        $this->assertSame(
            [['Upcoming event', date('Y-m-d', strtotime('+2 days'))]],
            array_map(fn(\Galette\IO\News\Post $post): array => [$post->getTitle(), $post->getDate()], $news->getPosts())
        );
    }

    /**
     * Plugin is installed once its tables exist
     */
    public function testIsInstalled(): void
    {
        $this->assertTrue($this->getPlugin()->isInstalled());
    }
}
