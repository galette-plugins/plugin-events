--
-- This file is part of Galette Events plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Events times; none for all day events
ALTER TABLE galette_events_events
  ADD begin_time time default NULL AFTER end_date,
  ADD end_time time default NULL AFTER begin_time;

-- Personal calendar subscriptions
CREATE TABLE galette_events_feeds (
  id_adh int(10) unsigned NOT NULL,
  token char(64) NOT NULL,
  creation_date date NOT NULL default '1901-01-01',
  PRIMARY KEY (id_adh),
  UNIQUE KEY galette_events_feeds_token_key (token),
  CONSTRAINT galette_events_feeds_id_adh_fkey FOREIGN KEY (id_adh)
    REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
