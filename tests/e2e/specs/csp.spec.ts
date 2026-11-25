/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { type Page } from '@playwright/test';
import { test } from '@e2e/fixtures/auth.fixture';
import { expectNoViolation, listenViolations } from '@e2e/fixtures/csp.fixture';

/**
 * Address of the first link of a list matching a path
 */
async function firstLink(page: Page, list: string, path: string): Promise<string> {
  await page.goto(list);
  const href = await page.locator(`a[href*="${path}"]`).first().getAttribute('href');
  if (href === null) {
    throw new Error(`No ${path} link on ${list}`);
  }
  return href;
}

/**
 * Plugin pages run no script Galette Content Security Policy does not allow
 */
test.describe('Content Security Policy', () => {

  test('administrator pages', async ({ loggedInAs }) => {
    test.setTimeout(90000);
    const page = await loggedInAs('admin');
    await listenViolations(page);

    const event = await firstLink(page, '/plugins/events/events', '/plugins/events/event/edit/');
    const booking = await firstLink(page, '/plugins/events/bookings/all', '/plugins/events/booking/edit/');
    const activity = await firstLink(page, '/plugins/events/activities', '/plugins/events/activity/edit/');

    await expectNoViolation(page, [
      '/plugins/events/events',
      '/plugins/events/event/add',
      event,
      '/plugins/events/bookings/all',
      '/plugins/events/booking/add',
      booking,
      '/plugins/events/activities',
      '/plugins/events/activity/add',
      activity,
      '/plugins/events/events/calendar',
    ]);
  });

  test('member pages', async ({ loggedInAs }) => {
    const page = await loggedInAs('member');
    await listenViolations(page);

    await expectNoViolation(page, [
      '/plugins/events/events',
      '/plugins/events/bookings/all',
      '/plugins/events/events/calendar',
    ]);
  });
});
