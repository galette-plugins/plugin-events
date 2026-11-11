/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { test, expect } from '@e2e/fixtures/auth.fixture';
import { confirmRemoval, listRow, rowId, uniqueName } from '../helpers';
import { FlashMessageHelper } from '@e2e/helpers';

/**
 * Activities management
 */
test.describe('Activities', () => {

  test('List the fixture activities', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await page.goto('/plugins/events/activities');

    for (const name of ['Repas', 'Hébergement', 'Visite guidée', 'Covoiturage']) {
      await expect(listRow(page, name)).toHaveCount(1);
    }
  });

  test.describe('CRUD', () => {
    test.describe.configure({ mode: 'serial' });

    const name = uniqueName('Activité e2e');
    let id: number;

    test('Add an activity', async ({ loggedInAs }) => {
      const page = await loggedInAs('admin');
      await page.goto('/plugins/events/activity/add');

      await page.locator('#name').fill(name);
      await page.locator('#comment').fill('Créée par un test e2e');
      await page.locator('#active').check({ force: true });
      await page.locator('button[name="valid"]').click();

      await page.waitForURL(/\/plugins\/events\/activities$/);
      await FlashMessageHelper.expectSuccess(page);
      const row = listRow(page, name);
      await expect(row).toHaveCount(1);
      id = await rowId(row, '/activity/edit/');
    });

    test('Edit the activity', async ({ loggedInAs }) => {
      const page = await loggedInAs('admin');
      await page.goto(`/plugins/events/activity/edit/${id}`);

      await expect(page.locator('#name')).toHaveValue(name);
      await page.locator('#comment').fill('Modifiée par un test e2e');
      await page.locator('button[name="valid"]').click();

      await page.waitForURL(/\/plugins\/events\/activities$/);
      await FlashMessageHelper.expectSuccess(page);

      await page.goto(`/plugins/events/activity/edit/${id}`);
      await expect(page.locator('#comment')).toHaveValue('Modifiée par un test e2e');
    });

    test('Remove the activity', async ({ loggedInAs }) => {
      const page = await loggedInAs('admin');
      await confirmRemoval(page, `/plugins/events/activity/remove/${id}`);

      await expect(page).toHaveURL(/\/plugins\/events\/activities$/);
      await FlashMessageHelper.expectSuccess(page);
      await expect(listRow(page, name)).toHaveCount(0);
    });
  });
});
