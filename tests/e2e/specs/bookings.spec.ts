/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { test, expect } from '@e2e/fixtures/auth.fixture';
import { confirmRemoval, listRow, rowId, selectValue, uniqueName } from '../helpers';
import { FlashMessageHelper } from '@e2e/helpers';

/**
 * Bookings management, on an event of their own so that counts of the
 * fixture events stay as they are
 */
test.describe('Bookings', () => {
  test.describe.configure({ mode: 'serial' });

  const event = uniqueName('Réservations e2e');
  let eventId: number;
  let memberId: number;
  let bookingId: number;

  test('List the bookings of a fixture event', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await page.goto('/plugins/events/events');
    const id = await rowId(listRow(page, 'Assemblée générale'), '/event/edit/');

    await page.goto(`/plugins/events/bookings/${id}`);
    await expect(page.locator('#event_filter')).toHaveValue(String(id));
    //15 bookings, the page may hold less of them
    await expect(page.locator('table.listing tbody tr').first()).toContainText('Assemblée générale');

    //a member booked on the fixture event, to book on ours
    memberId = await rowId(page.locator('table.listing tbody tr').first(), '/member/');
  });

  test('Book a member on an event', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await page.goto('/plugins/events/event/add');
    await page.locator('#name').fill(event);
    await page.locator('#town').fill('Gap');
    await page.locator('#open').check({ force: true });
    await page.locator('button[name="save"]').click();
    await page.waitForURL(/\/plugins\/events\/events$/);
    eventId = await rowId(listRow(page, event), '/event/edit/');

    await page.goto(`/plugins/events/booking/add/${memberId}`);
    await expect(page.locator('input[name="member"]')).toHaveValue(String(memberId));
    await selectValue(page, '#event', String(eventId));
    await page.locator('#number_people').fill('2');
    await page.locator('#amount').fill('25');
    await page.locator('#paid').check({ force: true });
    await page.locator('button[name="save"]').click();

    await FlashMessageHelper.expectSuccess(page);
    await page.goto(`/plugins/events/bookings/${eventId}`);
    const rows = page.locator('table.listing tbody tr');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText(event);
    bookingId = await rowId(rows.first(), '/booking/edit/');
  });

  test('Edit the booking', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await page.goto(`/plugins/events/booking/edit/${bookingId}`);

    await expect(page.locator('#number_people')).toHaveValue('2');
    await page.locator('#number_people').fill('3');
    await page.locator('button[name="save"]').click();
    await FlashMessageHelper.expectSuccess(page);

    await page.goto(`/plugins/events/booking/edit/${bookingId}`);
    await expect(page.locator('#number_people')).toHaveValue('3');
  });

  test('Only paid bookings once filtered', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await page.goto(`/plugins/events/bookings/${eventId}`);

    await page.locator('#filter_not_paid').check({ force: true });
    await page.locator('button[name="filter"]').click();
    await expect(page.locator('table.listing tbody tr a[href*="/booking/edit/"]')).toHaveCount(0);

    await page.locator('#filter_paid').check({ force: true });
    await page.locator('button[name="filter"]').click();
    await expect(page.locator('table.listing tbody tr a[href*="/booking/edit/"]').first()).toBeVisible();

    await page.locator('button[name="clear_filter"]').click();
  });

  test('Remove the booking, then the event', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await confirmRemoval(page, `/plugins/events/booking/remove/${bookingId}`);
    await FlashMessageHelper.expectSuccess(page);

    await page.goto(`/plugins/events/bookings/${eventId}`);
    await expect(page.locator('table.listing tbody tr a[href*="/booking/edit/"]')).toHaveCount(0);

    await confirmRemoval(page, `/plugins/events/event/remove/${eventId}`);
    await FlashMessageHelper.expectSuccess(page);
  });
});
