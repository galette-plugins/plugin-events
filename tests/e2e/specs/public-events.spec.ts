/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { Browser, Page } from '@playwright/test';
import { test, expect, ROLE_CREDENTIALS } from '@e2e/fixtures/auth.fixture';
import { FlashMessageHelper } from '@e2e/helpers';
import { PUBLIC_PAGES_VISIBILITY } from '@e2e/helpers/preferences';
import { selectValue } from '../helpers';

const VISIBILITY_PREF = 'pref_events_publicpages_visibility_events';

/**
 * Set the visibility of the public events page from the settings
 *
 * The /test/preferences API of Galette core does not load plugins, so it
 * cannot set a preference a plugin declares.
 */
async function setVisibility(browser: Browser, visibility: number): Promise<void> {
  const context = await browser.newContext();
  const page: Page = await context.newPage();
  const { login, password } = ROLE_CREDENTIALS.superadmin;

  await page.goto('/login');
  await page.locator('input#login').fill(login);
  await page.locator('input#password').fill(password);
  await page.locator('input[type="submit"]').click();
  await page.waitForURL('/dashboard');

  await page.goto('/preferences');
  await selectValue(page, `#${VISIBILITY_PREF}`, String(visibility));
  await Promise.all([
    page.waitForURL(/\/preferences/),
    page.locator('form [type="submit"]').first().click(),
  ]);
  await FlashMessageHelper.expectSuccess(page, undefined, 10000);
  await context.close();
}

/**
 * Public events page; it changes an instance wide preference, its tests run
 * one after the other and put it back as it was
 */
test.describe('Public events', () => {
  test.describe.configure({ mode: 'serial' });

  test.afterAll(async ({ browser }) => {
    await setVisibility(browser, PUBLIC_PAGES_VISIBILITY.INHERIT);
  });

  test('Anonymous visitors see public upcoming events', async ({ browser, page }) => {
    await setVisibility(browser, PUBLIC_PAGES_VISIBILITY.PUBLIC);

    await page.goto('/plugins/events/public/events');
    await expect(page).toHaveURL(/\/plugins\/events\/public\/events$/);
    await expect(page.getByRole('heading', { name: 'Assemblée générale' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Forum des associations' })).toBeVisible();
    //not public, or over
    await expect(page.getByRole('heading', { name: 'Week-end découverte à Strasbourg' })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Pique-nique de rentrée' })).toHaveCount(0);

    await expect(page.locator('#feed_url')).toHaveValue(/\/plugins\/events\/public\/events\.ics$/);
  });

  test('Anonymous visitors can subscribe to public events', async ({ browser, request }) => {
    await setVisibility(browser, PUBLIC_PAGES_VISIBILITY.PUBLIC);

    const response = await request.get('/plugins/events/public/events.ics');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/calendar');
    const body = await response.text();
    expect(body).toContain('SUMMARY:Assemblée générale');
    expect(body).not.toContain('SUMMARY:Week-end découverte à Strasbourg');
  });

  test('A hidden page is not shown', async ({ browser, page, request }) => {
    await setVisibility(browser, PUBLIC_PAGES_VISIBILITY.HIDDEN);

    await page.goto('/plugins/events/public/events');
    await expect(page).not.toHaveURL(/\/plugins\/events\/public\/events$/);

    const response = await request.get('/plugins/events/public/events.ics', { maxRedirects: 0 });
    expect(response.status()).not.toBe(200);
  });
});
