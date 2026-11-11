/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { test, expect } from '@e2e/fixtures/auth.fixture';

/**
 * What a member who is neither staff nor group manager may do
 */
test.describe('Member permissions', () => {

  test('A member sees the events', async ({ loggedInAs }) => {
    const page = await loggedInAs('member');
    await page.goto('/plugins/events/events');

    await expect(page).toHaveURL(/\/plugins\/events\/events$/);
    await expect(page.locator('table.listing')).toBeVisible();
    //but cannot change them
    await expect(page.locator('a[href*="/event/remove/"]')).toHaveCount(0);
  });

  for (const path of ['/plugins/events/activities', '/plugins/events/event/add']) {
    test(`A member cannot open ${path}`, async ({ loggedInAs }) => {
      const page = await loggedInAs('member');
      await page.goto(path);

      await expect(page).not.toHaveURL(new RegExp(`${path}$`));
    });
  }
});
