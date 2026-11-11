/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { test, expect } from '@e2e/fixtures/auth.fixture';

/**
 * Calendar, drawn by the FullCalendar bundle the plugin builds
 */
test.describe('Calendar', () => {

  test('Display the events in the calendar', async ({ loggedInAs }) => {
    const page = await loggedInAs('member');

    const feed = page.waitForResponse(response => response.url().includes('/plugins/events/ajax/events/calendar'));
    await page.goto('/plugins/events/events/calendar');
    const response = await feed;
    expect(response.status()).toBe(200);
    const events: { title: string }[] = await response.json();
    expect(events.length).toBeGreaterThan(0);

    //no bundle, no calendar: the plugin JavaScript has to be built
    const calendar = page.locator('#calendar');
    await expect(calendar.getByRole('heading')).toBeVisible();
    await expect(calendar.getByText(events[0].title).first()).toBeVisible();
  });
});
