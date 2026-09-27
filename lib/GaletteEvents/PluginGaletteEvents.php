<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents;

use DI\Attribute\Inject;
use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Plugins\DashboardProviderInterface;
use Galette\Core\Plugins\InstallableInterface;
use Galette\Core\Plugins\MemberActionProviderInterface;
use Galette\Core\Plugins\MenuProviderInterface;
use Galette\Core\Plugins\NewsProviderInterface;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Galette\Core\GalettePlugin;
use Galette\IO\News\Entry;
use Galette\IO\News\Post;
use GaletteEvents\Filters\EventsList;
use GaletteEvents\Repository\Events;
use Laminas\Db\Metadata\Object\ConstraintObject;
use Laminas\Db\Metadata\Source\Factory;

/**
 * Galette Events plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PluginGaletteEvents extends GalettePlugin implements InstallableInterface, NewsProviderInterface, MenuProviderInterface, DashboardProviderInterface, MemberActionProviderInterface
{
    #[Inject]
    protected Db $zdb;

    #[Inject]
    protected Login $login;

    #[Inject]
    protected History $history;

    /**
     * Extra menus entries
     *
     * @return array<string, string|array<string, mixed>>
     */
    public function getMenus(): array
    {
        $menus = [];

        if ($this->login->isLogged()) {
            $menus['plugin_events'] = [
                'title' => _T("Events", "events"),
                'icon' => 'calendar alternate',
                'items' => [
                    [
                        'label' => _T('Events', 'events'),
                        'route' => [
                            'name' => 'events_events',
                            'aliases' => ['events_event_add', 'events_event_edit']
                        ]
                    ],
                    [
                        'label' => _T('Calendar', 'events'),
                        'route' => [
                            'name' => 'events_calendar',
                        ]
                    ],
                    [
                        'label' => _T('Bookings', 'events'),
                        'route' => [
                            'name' => 'events_bookings',
                            'args' => [
                                'event' => 'all'
                            ],
                            'aliases' => ['events_booking_add', 'events_booking_edit']
                        ]
                    ],
                ]
            ];
        }

        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $menus['plugin_events']['items'] = array_merge(
                $menus['plugin_events']['items'],
                [
                    [
                        'label' => _T('Activities', 'events'),
                        'route' => [
                            'name' => 'events_activities',
                            'aliases' => ['events_activity_add', 'events_activity_edit']
                        ]
                    ]
                ]
            );
        }

        return $menus;
    }

    /**
     * Extra public menus entries
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function getPublicMenus(): array
    {
        return [];
    }

    /**
     * Get current logged-in user dashboards contents
     *
     * @return array<int, string|array<string,mixed>>
     */
    public function getMyDashboards(): array
    {
        return [];
    }

    /**
     * Get dashboards contents
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function getDashboards(): array
    {
        return [
            [
                'label' => _T("Calendar", "events"),
                'title' => _T("Events calendar", "events"),
                'route' => [
                    'name' => 'events_calendar'
                ],
                'icon' => 'calendar_spiral'
            ]
        ];
    }

    /**
     * Get actions contents
     *
     * @param Adherent $member Member instance
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function getListActions(Adherent $member): array
    {
        return [
            [
                'label' => _T("New event booking", "events"),
                'route' => [
                    'name' => 'events_booking_add',
                    'args' => ['id_adh' => $member->id]
                ],
                'icon' => 'calendar alternate grey'
            ],
        ];
    }

    /**
     * Get detailed actions contents
     *
     * @param Adherent $member Member instance
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function getDetailedActions(Adherent $member): array
    {
        return $this->getListActions($member);
    }

    /**
     * Get batch actions contents
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function getBatchActions(): array
    {
        return [];
    }

    /**
     * Get plugin upcoming events
     */
    public function getNews(): ?Entry
    {
        $filters = new EventsList();
        $now = new \DateTime();
        $filters->start_date_filter = $now->format(__('Y-m-d'));
        $events = new Events($this->zdb, $this->login, $this->history, $filters);

        $posts = [];
        $list = $events->getList();
        if (!count($list)) {
            return null;
        }

        foreach ($list as $event) {
            $posts[] = new Post(
                title: $event->getName(),
                date: (new \DateTime($event->getBeginDate()))->format(__('Y-m-d'))
            );
        }

        return new Entry(
            _T('Upcoming events', 'events'),
            $posts
        );
    }

    /**
     * Is the plugin fully installed (including database, extra configuration, etc)?
     */
    public function isInstalled(): bool
    {
        return $this->zdb->tableExists(EVENTS_PREFIX . Event::TABLE);
    }

    /**
     * Version of tables installed before plugins versions were recorded
     *
     * Since 1.1, amounts are no longer floating point numbers on PostgreSQL,
     * and foreign keys are named on MySQL.
     */
    public function getLegacyDbVersion(): ?float
    {
        $metadata = Factory::createSourceFromAdapter($this->zdb->db);
        if ($this->zdb->isPostgres()) {
            $amount = $metadata->getColumn('payment_amount', PREFIX_DB . EVENTS_PREFIX . Booking::TABLE);
            return $amount->getDataType() === 'real' ? 1.0 : null;
        }

        $table = PREFIX_DB . EVENTS_PREFIX . Event::TABLE;
        /** @var ConstraintObject $constraint */
        foreach ($metadata->getConstraints($table) as $constraint) {
            if ($constraint->isForeignKey() && $constraint->getColumns() === [Group::PK]) {
                return $constraint->getName() === $table . '_id_group_fkey' ? null : 1.0;
            }
        }
        return null;
    }
}
