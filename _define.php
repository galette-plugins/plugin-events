<?php

/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette Events',     //Name
    desc: 'Events management',  //Short description
    author: 'Johan Cwiklinski', //Author
    version: '2.3.0',           //Version
    compver: '1.3.0',           //Galette compatible version
    route: 'events',            //routing name and translation domain
    date: '2026-10-03',         //Release date
    acls: [                     //Permissions needed
        'events_events'             => 'member',
        'events_bookings'           => 'member',
        'filter-eventslist'         => 'member',
        'events_event_add'          => 'groupmanager',
        'events_event_edit'         => 'groupmanager',
        'events_storeevent_add'     => 'groupmanager',
        'events_storeevent_edit'    => 'groupmanager',
        'events_remove_event'       => 'staff',
        'events_do_remove_event'    => 'staff',
        'events_booking_add'        => 'member',
        'events_booking_edit'       => 'member',
        'events_storebooking_add'   => 'member',
        'events_storebooking_edit'  => 'member',
        'events_remove_booking'     => 'staff',
        'events_do_remove_booking'  => 'staff',
        'filter-bookingslist'       => 'member',
        'batch-eventslist'          => 'groupmanager',
        'events_activities'         => 'staff',
        'filter-activitieslist'     => 'staff',
        'events_activity_add'       => 'staff',
        'events_activity_edit'      => 'staff',
        'events_storeactivity_add'  => 'staff',
        'events_storeactivity_edit' => 'staff',
        'events_remove_activity'    => 'staff',
        'events_do_remove_activity' => 'staff',
        'event_bookings_export'     => 'groupmanager',
        'events_bookings_export'    => 'groupmanager',
        'events_calendar'           => 'member',
        'ajax-events_calendar'      => 'member'
    ],
    dbver: 1.1
);
