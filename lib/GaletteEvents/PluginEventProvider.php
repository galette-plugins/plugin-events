<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteEvents;

use Galette\Core\Db;
use Galette\Entity\Group;
use Galette\Events\GaletteEvent;
use Laminas\Db\Sql\Expression;
use League\Event\ListenerRegistry;
use League\Event\ListenerSubscriber;
use Psr\Container\ContainerInterface;

/**
 * Events listeners on core events
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginEventProvider implements ListenerSubscriber
{
    /**
     * Constructor
     *
     * Built while plugins are loaded: the database is resolved only
     * when an event is emitted.
     *
     * @param ContainerInterface $container Container
     */
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Set up listeners
     *
     * @param ListenerRegistry $acceptor Listener
     */
    public function subscribeListeners(ListenerRegistry $acceptor): void
    {
        $acceptor->subscribeTo(
            'group.before_remove',
            function (GaletteEvent $event): void {
                /** @var Group $group */
                $group = $event->getObject();
                $count = $this->countGroupEvents((int)$group->getId());
                if ($count > 0) {
                    $group->preventRemoval(
                        sprintf(
                            _Tn(
                                //TRANS: %1$s is the group name, %2$s the number of events
                                'Group "%1$s" is used by %2$s event, it cannot be deleted. Remove the event or change its group first.',
                                'Group "%1$s" is used by %2$s events, it cannot be deleted. Remove the events or change their group first.',
                                $count,
                                'events'
                            ),
                            $group->getName(),
                            $count
                        )
                    );
                }
            }
        );
    }

    /**
     * Count events of a group
     *
     * Events restrict the removal of their group, which would otherwise
     * make them visible to every member.
     *
     * @param int $group_id Group identifier
     */
    private function countGroupEvents(int $group_id): int
    {
        /** @var Db $zdb */
        $zdb = $this->container->get(Db::class);
        $select = $zdb->select(EVENTS_PREFIX . Event::TABLE);
        $select->columns(['counter' => new Expression('COUNT(' . Event::PK . ')')]);
        $select->where([Group::PK => $group_id]);
        return (int)$zdb->execute($select)->current()->counter;
    }
}
