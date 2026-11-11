/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { expect, Locator, Page } from '@playwright/test';

/**
 * A name no other run, nor another spec running alongside, will use
 */
export function uniqueName(prefix: string): string {
  return `${prefix} ${Date.now()}-${Math.floor(Math.random() * 1000)}`;
}

/**
 * Row of a listing holding a text
 */
export function listRow(page: Page, text: string): Locator {
  return page.locator('table.listing tbody tr', { hasText: text });
}

/**
 * Identifier found in the first link of a row matching a path, such as /event/edit/<id>
 */
export async function rowId(row: Locator, path: string): Promise<number> {
  const href = await row.locator(`a[href*="${path}"]`).first().getAttribute('href');
  const match = href?.match(new RegExp(`${path}(\\d+)`));
  expect(match, `no ${path} link in row`).not.toBeNull();
  return Number(match![1]);
}

/**
 * Select a value in a select Fomantic turned into a dropdown
 *
 * The native select is hidden, selectOption() would wait for it forever.
 */
export async function selectValue(page: Page, selector: string, value: string): Promise<void> {
  await page.evaluate(
    ([s, v]) => (window as any).$(s).dropdown('set selected', v),
    [selector, value]
  );
}

/**
 * Remove an entry from its confirmation page
 */
export async function confirmRemoval(page: Page, path: string): Promise<void> {
  await page.goto(path);
  await page.locator('#delete').click();
  await page.waitForLoadState();
}
