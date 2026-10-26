--
-- This file is part of Galette Events plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Events times; none for all day events
ALTER TABLE galette_events_events
  ADD begin_time time default NULL AFTER end_date,
  ADD end_time time default NULL AFTER begin_time;
