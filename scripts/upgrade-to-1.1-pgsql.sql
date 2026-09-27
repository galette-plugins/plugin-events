--
-- This file is part of Galette Events plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Color came with 2.1.0, but its update script never ran: installations
-- created before do not have the column.
ALTER TABLE galette_events_events ADD COLUMN IF NOT EXISTS color character varying(7);

-- Amounts were stored as floating point numbers
ALTER TABLE galette_events_bookings
  ALTER COLUMN payment_amount TYPE numeric(15,2) USING ROUND(CAST(payment_amount AS numeric), 2),
  ALTER COLUMN payment_amount SET DEFAULT '0';

-- A booking always has an event and a member, a booked activity an activity and a booking
DELETE FROM galette_events_bookings WHERE id_event IS NULL OR id_adh IS NULL;
DELETE FROM galette_events_activitiesbookings WHERE id_activity IS NULL OR id_booking IS NULL;
ALTER TABLE galette_events_bookings
  ALTER COLUMN id_event SET NOT NULL,
  ALTER COLUMN id_adh SET NOT NULL;
ALTER TABLE galette_events_activitiesbookings
  ALTER COLUMN id_activity SET NOT NULL,
  ALTER COLUMN id_booking SET NOT NULL;

-- Flags are never unknown, as on MySQL
UPDATE galette_events_events SET is_open = TRUE WHERE is_open IS NULL;
ALTER TABLE galette_events_events ALTER COLUMN is_open SET NOT NULL;
UPDATE galette_events_bookings SET is_paid = FALSE WHERE is_paid IS NULL;
ALTER TABLE galette_events_bookings ALTER COLUMN is_paid SET NOT NULL;
UPDATE galette_events_activities SET is_active = TRUE WHERE is_active IS NULL;
ALTER TABLE galette_events_activities ALTER COLUMN is_active SET NOT NULL;
UPDATE galette_events_activitiesbookings SET checked = FALSE WHERE checked IS NULL;
ALTER TABLE galette_events_activitiesbookings ALTER COLUMN checked SET NOT NULL;
