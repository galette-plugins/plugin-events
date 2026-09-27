--
-- This file is part of Galette Events plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Align schema with PostgreSQL one: utf8mb4, named foreign keys.
-- Foreign keys names depend on the MySQL version that created them, and
-- MySQL cannot drop them conditionally: tables holding some are rebuilt.
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE galette_events_activities CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
-- Conversion turns text into mediumtext to keep its capacity in bytes
ALTER TABLE galette_events_activities MODIFY comment text;

CREATE TABLE galette_events_events_new (
  id_event int(10) NOT NULL auto_increment,
  name varchar(150) NOT NULL,
  address varchar(150) NOT NULL default '',
  zip varchar(10) NOT NULL default '',
  town varchar(50) NOT NULL default '',
  country varchar(50) default NULL,
  begin_date date NOT NULL default '1901-01-01',
  end_date date NOT NULL default '1901-01-01',
  creation_date date NOT NULL default '1901-01-01',
  is_open tinyint(1) NOT NULL default 1,
  id_group int unsigned default NULL,
  comment text,
  color varchar(7),
  PRIMARY KEY (id_event),
  CONSTRAINT galette_events_events_id_group_fkey FOREIGN KEY (id_group)
    REFERENCES galette_groups (id_group) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_events_events_new
  (id_event, name, address, zip, town, country, begin_date, end_date, creation_date,
    is_open, id_group, comment)
SELECT id_event, name, address, zip, town, country, begin_date, end_date, creation_date,
    is_open, id_group, comment
FROM galette_events_events;

-- Color came with 2.1.0, but its update script never ran: installations
-- created before do not have the column.
SET @events_color = (
  SELECT IF(
    COUNT(*) > 0,
    'UPDATE galette_events_events_new n INNER JOIN galette_events_events o ON o.id_event = n.id_event SET n.color = o.color',
    'DO 0'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'galette_events_events' AND COLUMN_NAME = 'color'
);
PREPARE events_color FROM @events_color;
EXECUTE events_color;
DEALLOCATE PREPARE events_color;

CREATE TABLE galette_events_bookings_new (
  id_booking int(10) NOT NULL auto_increment,
  id_event int(10) NOT NULL,
  id_adh int(10) unsigned NOT NULL,
  booking_date date NOT NULL default '1901-01-01',
  is_paid tinyint(1) NOT NULL default 0,
  payment_amount decimal(15,2) default '0',
  payment_method tinyint(3) unsigned NOT NULL default '0',
  bank_name varchar(100) default NULL,
  check_number varchar(50) default NULL,
  number_people int(4) default NULL,
  creation_date date NOT NULL default '1901-01-01',
  comment text,
  PRIMARY KEY (id_booking),
  UNIQUE KEY galette_events_bookings_id_event_id_adh_key (id_event, id_adh),
  CONSTRAINT galette_events_bookings_id_event_fkey FOREIGN KEY (id_event)
    REFERENCES galette_events_events (id_event) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT galette_events_bookings_id_adh_fkey FOREIGN KEY (id_adh)
    REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_events_bookings_new
  (id_booking, id_event, id_adh, booking_date, is_paid, payment_amount, payment_method,
    bank_name, check_number, number_people, creation_date, comment)
SELECT id_booking, id_event, id_adh, booking_date, is_paid, payment_amount, payment_method,
    bank_name, check_number, number_people, creation_date, comment
FROM galette_events_bookings;

CREATE TABLE galette_events_activitiesevents_new (
  id_event int(10) NOT NULL,
  id_activity int(10) NOT NULL,
  status tinyint(1) NOT NULL,
  PRIMARY KEY (id_event, id_activity),
  CONSTRAINT galette_events_activitiesevents_id_event_fkey FOREIGN KEY (id_event)
    REFERENCES galette_events_events (id_event) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT galette_events_activitiesevents_id_activity_fkey FOREIGN KEY (id_activity)
    REFERENCES galette_events_activities (id_activity) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_events_activitiesevents_new (id_event, id_activity, status)
SELECT id_event, id_activity, status
FROM galette_events_activitiesevents;

CREATE TABLE galette_events_activitiesbookings_new (
  id_activitybooking int(10) NOT NULL auto_increment,
  id_activity int(10) NOT NULL,
  id_booking int(10) NOT NULL,
  checked tinyint(1) NOT NULL default 0,
  PRIMARY KEY (id_activitybooking),
  UNIQUE KEY galette_events_activitiesbookings_id_activity_id_booking_key (id_activity, id_booking),
  CONSTRAINT galette_events_activitiesbookings_id_activity_fkey FOREIGN KEY (id_activity)
    REFERENCES galette_events_activities (id_activity) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT galette_events_activitiesbookings_id_booking_fkey FOREIGN KEY (id_booking)
    REFERENCES galette_events_bookings (id_booking) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_events_activitiesbookings_new (id_activitybooking, id_activity, id_booking, checked)
SELECT id_activitybooking, id_activity, id_booking, COALESCE(checked, 0)
FROM galette_events_activitiesbookings;

DROP TABLE galette_events_activitiesbookings, galette_events_activitiesevents,
  galette_events_bookings, galette_events_events;

RENAME TABLE galette_events_events_new TO galette_events_events,
  galette_events_bookings_new TO galette_events_bookings,
  galette_events_activitiesevents_new TO galette_events_activitiesevents,
  galette_events_activitiesbookings_new TO galette_events_activitiesbookings;

SET FOREIGN_KEY_CHECKS=1;
