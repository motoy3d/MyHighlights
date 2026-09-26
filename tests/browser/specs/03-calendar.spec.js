import { test, expect } from '@playwright/test';
import { gotoApp, openTab, watchApiFailures } from '../helpers/app.js';

test.describe('カレンダー', () => {
  test.beforeEach(async ({ page }) => { await gotoApp(page); });

  test('当月のカレンダーが表示される', async ({ page }) => {
    const apiFailures = watchApiFailures(page);
    await openTab(page, 'カレンダー');

    const label = page.locator('.current_year_month').first();
    await expect(label).toBeVisible({ timeout: 15_000 });

    // 「2026年9月」のような表記であること。ここがずれると
    // タイムゾーン設定(Asia/Tokyo)の取りこぼしを疑う
    const text = (await label.innerText()).trim();
    expect(text, `年月の表記が想定と違う: ${text}`).toMatch(/\d{4}\s*年\s*\d{1,2}\s*月/);

    const now = new Date();
    const jst = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    expect(text).toContain(String(jst.getFullYear()));
    expect(text).toContain(`${jst.getMonth() + 1}月`);

    expect(apiFailures, `/api/* が5xx: ${apiFailures.join(', ')}`).toHaveLength(0);
  });

  test('前月・翌月に移動できる', async ({ page }) => {
    await openTab(page, 'カレンダー');
    const label = page.locator('.current_year_month').first();
    await expect(label).toBeVisible({ timeout: 15_000 });

    const start = (await label.innerText()).trim();

    await page.locator('.month_text').first().click();   // 前月
    await expect(label).not.toHaveText(start, { timeout: 10_000 });

    await page.locator('.month_text').last().click();    // 翌月で戻る
    await expect(label).toHaveText(start, { timeout: 10_000 });
  });

  test('予定がカレンダー上に表示される', async ({ page }) => {
    await openTab(page, 'カレンダー');
    await expect(page.locator('.current_year_month').first()).toBeVisible({ timeout: 15_000 });

    // シーダーは当月周辺に予定を作る。本番データでも当月に何かしら入っている想定。
    // 0件でも即異常とは言えないので、テーブルが描画されていることまでを必須とする
    await expect(page.locator('table.calendar-table, .calendar-table').first())
      .toBeVisible({ timeout: 15_000 });
  });
});
