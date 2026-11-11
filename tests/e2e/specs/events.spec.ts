/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { test, expect } from '@e2e/fixtures/auth.fixture';
import { confirmRemoval, listRow, rowId, uniqueName } from '../helpers';
import { FlashMessageHelper } from '@e2e/helpers';

/**
 * Events management, with the events galette:seed-fixtures created
 */
test.describe('Events', () => {

  test('List the fixture events', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await page.goto('/plugins/events/events');

    await expect(page.locator('table.listing')).toBeVisible();
    await expect(listRow(page, 'Assemblée générale')).toHaveCount(1);
    //a closed event says so
    await expect(listRow(page, 'Voyage à Rome').locator('i.lock.red')).toHaveCount(1);
    await expect(listRow(page, 'Assemblée générale').locator('i.lock.open.green')).toHaveCount(1);
  });

  test.describe('CRUD', () => {
    test.describe.configure({ mode: 'serial' });

    const name = uniqueName('Événement e2e');
    let id: number;

    test('Add an event', async ({ loggedInAs }) => {
      const page = await loggedInAs('admin');
      await page.goto('/plugins/events/event/add');

      await page.locator('#name').fill(name);
      await page.locator('#town').fill('Valence');
      await page.locator('#zip').fill('26000');
      //new events last all day: their times show once switched off
      await expect(page.locator('#begin_time')).toBeHidden();
      await page.locator('label[for="all_day"]').click();
      await page.locator('#begin_time').fill('10:00');
      await page.locator('#end_time').fill('12:00');
      await page.locator('#comment').fill('Créé par un test e2e');
      await page.locator('button[name="save"]').click();

      await page.waitForURL(/\/plugins\/events\/events$/);
      await FlashMessageHelper.expectSuccess(page);

      const row = listRow(page, name);
      await expect(row).toHaveCount(1);
      await expect(row).toContainText('Valence');
      id = await rowId(row, '/event/edit/');
    });

    test('A missing town is refused', async ({ loggedInAs }) => {
      const page = await loggedInAs('admin');
      await page.goto('/plugins/events/event/add');

      await page.locator('#name').fill(uniqueName('Sans ville'));
      //the field is required in the browser: go past it to check the server
      await page.locator('#town').evaluate(input => input.removeAttribute('required'));
      await page.locator('button[name="save"]').click();

      await page.waitForURL(/\/plugins\/events\/event\/add$/);
      await FlashMessageHelper.expectError(page);
    });

    test('Edit the event', async ({ loggedInAs }) => {
      const page = await loggedInAs('admin');
      await page.goto(`/plugins/events/event/edit/${id}`);

      await expect(page.locator('#name')).toHaveValue(name);
      await expect(page.locator('#all_day')).not.toBeChecked();
      await expect(page.locator('#begin_time')).toHaveValue('10:00');
      await page.locator('#town').fill('Die');
      await page.locator('button[name="save"]').click();

      await page.waitForURL(/\/plugins\/events\/events$/);
      await FlashMessageHelper.expectSuccess(page);
      await expect(listRow(page, name)).toContainText('Die');
    });

    test('Attach an activity to the event', async ({ loggedInAs }) => {
      const page = await loggedInAs('admin');
      await page.goto(`/plugins/events/event/edit/${id}`);

      const activity = await page.locator('#attach_activity option', { hasText: 'Covoiturage' }).getAttribute('value');
      await page.evaluate(v => (window as any).$('#attach_activity').dropdown('set selected', v), activity!);
      await page.locator('#add_activity').click();

      await page.waitForURL(new RegExp(`/plugins/events/event/edit/${id}$`));
      await FlashMessageHelper.expectWarning(page);
      await page.locator('button[name="save"]').click();
      await page.waitForURL(/\/plugins\/events\/events$/);

      await page.goto(`/plugins/events/event/edit/${id}`);
      await expect(page.locator(`input[name="activities_ids[]"][value="${activity}"]`)).toHaveCount(1);
    });

    test('Remove the event', async ({ loggedInAs }) => {
      const page = await loggedInAs('admin');
      await confirmRemoval(page, `/plugins/events/event/remove/${id}`);

      await expect(page).toHaveURL(/\/plugins\/events\/events$/);
      await FlashMessageHelper.expectSuccess(page);
      await expect(listRow(page, name)).toHaveCount(0);
    });
  });

  test('Download an event for calendar applications', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await page.goto('/plugins/events/events');
    const id = await rowId(listRow(page, 'Assemblée générale'), '/event/edit/');

    const response = await page.request.get(`/plugins/events/event/${id}/ics`);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/calendar');
    const body = await response.text();
    expect(body).toContain('BEGIN:VCALENDAR');
    expect(body).toContain('SUMMARY:Assemblée générale');
  });

  test('Export the bookings of an event', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await page.goto('/plugins/events/events');
    const row = listRow(page, 'Assemblée générale');

    const download = page.waitForEvent('download');
    await row.locator('a[href*="/export/bookings"]').click();
    expect((await download).suggestedFilename()).toMatch(/\.csv$/);
  });
});
