--
-- This file is part of Galette Events plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Events times; none for all day events
ALTER TABLE galette_events_events
  ADD begin_time time default NULL AFTER end_date,
  ADD end_time time default NULL AFTER begin_time;

-- Align column types with Galette core: integers without display width,
-- unsigned identifiers, dates without default values.
-- Columns linked by a foreign key cannot change type: links between plugin
-- tables are dropped first, then created again with the same names.

ALTER TABLE galette_events_bookings
  DROP FOREIGN KEY galette_events_bookings_id_event_fkey;
ALTER TABLE galette_events_activitiesevents
  DROP FOREIGN KEY galette_events_activitiesevents_id_event_fkey,
  DROP FOREIGN KEY galette_events_activitiesevents_id_activity_fkey;
ALTER TABLE galette_events_activitiesbookings
  DROP FOREIGN KEY galette_events_activitiesbookings_id_activity_fkey,
  DROP FOREIGN KEY galette_events_activitiesbookings_id_booking_fkey;

ALTER TABLE galette_events_events
  MODIFY id_event int unsigned NOT NULL auto_increment,
  MODIFY begin_date date NOT NULL,
  MODIFY end_date date NOT NULL,
  MODIFY creation_date date NOT NULL;

ALTER TABLE galette_events_bookings
  MODIFY id_booking int unsigned NOT NULL auto_increment,
  MODIFY id_event int unsigned NOT NULL,
  MODIFY id_adh int unsigned NOT NULL,
  MODIFY booking_date date NOT NULL,
  MODIFY payment_method int unsigned NOT NULL,
  MODIFY number_people smallint default NULL,
  MODIFY creation_date date NOT NULL;

ALTER TABLE galette_events_activities
  MODIFY id_activity int unsigned NOT NULL auto_increment,
  MODIFY creation_date date NOT NULL;

ALTER TABLE galette_events_activitiesevents
  MODIFY id_event int unsigned NOT NULL,
  MODIFY id_activity int unsigned NOT NULL,
  MODIFY status smallint NOT NULL;

ALTER TABLE galette_events_activitiesbookings
  MODIFY id_activitybooking int unsigned NOT NULL auto_increment,
  MODIFY id_activity int unsigned NOT NULL,
  MODIFY id_booking int unsigned NOT NULL;

ALTER TABLE galette_events_bookings
  ADD CONSTRAINT galette_events_bookings_id_event_fkey FOREIGN KEY (id_event)
    REFERENCES galette_events_events (id_event) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE galette_events_activitiesevents
  ADD CONSTRAINT galette_events_activitiesevents_id_event_fkey FOREIGN KEY (id_event)
    REFERENCES galette_events_events (id_event) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT galette_events_activitiesevents_id_activity_fkey FOREIGN KEY (id_activity)
    REFERENCES galette_events_activities (id_activity) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE galette_events_activitiesbookings
  ADD CONSTRAINT galette_events_activitiesbookings_id_activity_fkey FOREIGN KEY (id_activity)
    REFERENCES galette_events_activities (id_activity) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT galette_events_activitiesbookings_id_booking_fkey FOREIGN KEY (id_booking)
    REFERENCES galette_events_bookings (id_booking) ON DELETE CASCADE ON UPDATE CASCADE;
