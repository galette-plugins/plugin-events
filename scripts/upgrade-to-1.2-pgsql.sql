--
-- This file is part of Galette Events plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Events times; none for all day events
ALTER TABLE galette_events_events
  ADD COLUMN begin_time time default NULL,
  ADD COLUMN end_time time default NULL;

-- Events published on public pages
ALTER TABLE galette_events_events
  ADD COLUMN is_public boolean default FALSE NOT NULL;

-- Personal calendar subscriptions
CREATE TABLE galette_events_feeds (
  id_adh integer NOT NULL REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
  token character(64) NOT NULL,
  creation_date date default '19010101' NOT NULL,
  PRIMARY KEY (id_adh),
  UNIQUE (token)
);
