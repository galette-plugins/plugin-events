--
-- This file is part of Galette Events plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

SET FOREIGN_KEY_CHECKS=0;

--
-- Table structure for table `galette_events_events`
--

DROP TABLE IF EXISTS galette_events_events;
CREATE TABLE galette_events_events (
  id_event int unsigned NOT NULL auto_increment,
  name varchar(150) NOT NULL,
  address varchar(150) NOT NULL default '',
  zip varchar(10) NOT NULL default '',
  town varchar(50) NOT NULL default '',
  country varchar(50) default NULL,
  begin_date date NOT NULL,
  end_date date NOT NULL,
  begin_time time default NULL,
  end_time time default NULL,
  creation_date date NOT NULL,
  is_open tinyint(1) NOT NULL default 1,
  is_public tinyint(1) NOT NULL default 0,
  id_group int unsigned default NULL,
  summary varchar(255) default NULL,
  comment text,
  color varchar(7),
  PRIMARY KEY (id_event),
  CONSTRAINT galette_events_events_id_group_fkey FOREIGN KEY (id_group)
    REFERENCES galette_groups (id_group) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Table structure for table `galette_events_bookings`
--

DROP TABLE IF EXISTS galette_events_bookings;
CREATE TABLE galette_events_bookings (
  id_booking int unsigned NOT NULL auto_increment,
  id_event int unsigned NOT NULL,
  id_adh int unsigned NOT NULL,
  booking_date date NOT NULL,
  is_paid tinyint(1) NOT NULL default 0,
  payment_amount decimal(15,2) default '0',
  payment_method int unsigned NOT NULL,
  bank_name varchar(100) default NULL,
  check_number varchar(50) default NULL,
  number_people smallint default NULL,
  creation_date date NOT NULL,
  comment text,
  PRIMARY KEY (id_booking),
  UNIQUE KEY galette_events_bookings_id_event_id_adh_key (id_event, id_adh),
  CONSTRAINT galette_events_bookings_id_event_fkey FOREIGN KEY (id_event)
    REFERENCES galette_events_events (id_event) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT galette_events_bookings_id_adh_fkey FOREIGN KEY (id_adh)
    REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Table structure for table `galette_events_activities`
--

DROP TABLE IF EXISTS galette_events_activities;
CREATE TABLE galette_events_activities (
  id_activity int unsigned NOT NULL auto_increment,
  name varchar(150) NOT NULL,
  is_active tinyint(1) NOT NULL default 1,
  creation_date date NOT NULL,
  comment text,
  PRIMARY KEY (id_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_events_activitiesevents;
CREATE TABLE galette_events_activitiesevents (
  id_event int unsigned NOT NULL,
  id_activity int unsigned NOT NULL,
  status smallint NOT NULL,
  PRIMARY KEY (id_event, id_activity),
  CONSTRAINT galette_events_activitiesevents_id_event_fkey FOREIGN KEY (id_event)
    REFERENCES galette_events_events (id_event) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT galette_events_activitiesevents_id_activity_fkey FOREIGN KEY (id_activity)
    REFERENCES galette_events_activities (id_activity) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_events_activitiesbookings;
CREATE TABLE galette_events_activitiesbookings (
  id_activitybooking int unsigned NOT NULL auto_increment,
  id_activity int unsigned NOT NULL,
  id_booking int unsigned NOT NULL,
  checked tinyint(1) NOT NULL default 0,
  PRIMARY KEY (id_activitybooking),
  UNIQUE KEY galette_events_activitiesbookings_id_activity_id_booking_key (id_activity, id_booking),
  CONSTRAINT galette_events_activitiesbookings_id_activity_fkey FOREIGN KEY (id_activity)
    REFERENCES galette_events_activities (id_activity) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT galette_events_activitiesbookings_id_booking_fkey FOREIGN KEY (id_booking)
    REFERENCES galette_events_bookings (id_booking) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Table structure for table `galette_events_feeds`
--

DROP TABLE IF EXISTS galette_events_feeds;
CREATE TABLE galette_events_feeds (
  id_adh int unsigned NOT NULL,
  token char(64) NOT NULL,
  creation_date datetime NOT NULL,
  PRIMARY KEY (id_adh),
  UNIQUE KEY galette_events_feeds_token_key (token),
  CONSTRAINT galette_events_feeds_id_adh_fkey FOREIGN KEY (id_adh)
    REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

SET FOREIGN_KEY_CHECKS=1;
