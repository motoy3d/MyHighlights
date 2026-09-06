import { test, expect } from '@playwright/test';
import { gotoApp, openTab, watchApiFailures } from '../helpers/app.js';

/**
 * 予定の登録・編集・削除。Calendar.vue(656行) と
 * AddSchedule.vue / EditSchedule.vue が担当する範囲。
 *
 * 注意: <v-ons-page id="post"> が7コンポーネントで重複しているため、
 * 画面の特定には #addScheduleForm のような一意なものを使う。
 */

/** 当月の15日。カレンダーに必ず表示され、月末月初の境界も踏まない */
function targetDate() {
  const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  return `${y}-${m}-15`;
}

/**
 * OnsenUIのスイッチをONにする。
 *
 * <ons-switch> の実体は視覚的に隠れた input[type=checkbox] で、
 * 見た目は .switch__toggle が担っている。
 * カスタム要素そのものへの合成クリックは WebKit(iOS Safari相当) では
 * 伝わらないことがあるため、見た目の要素を押し、
 * それでも変わらなければ input を直接操作する。
 */
async function toggleSwitchOn(page, selector) {
  const input = page.locator(`${selector} input[type="checkbox"]`);
  if (await input.isChecked()) return;

  // 1) 見た目の要素を押す（PC）
  await page.locator(`${selector} .switch__toggle`).click().catch(() => {});
  if (await input.isChecked()) return;

  // 2) タップする（モバイルはタッチ前提で、合成クリックが効かないことがある）
  const hasTouch = await page.evaluate(() => 'ontouchstart' in window);
  if (hasTouch) {
    await page.locator(`${selector} .switch__toggle`).tap().catch(() => {});
    if (await input.isChecked()) return;
  }

  // 3) ons-switch のプロパティを直接立てて change を発火する。
  //    checkbox は OnsenUI が制御しており click では状態が変わらないため。
  //    実機のタップでは動作するので、これはテスト自動化側の都合。
  await page.locator(selector).evaluate((el) => {
    el.checked = true;
    el.dispatchEvent(new Event('change', { bubbles: true }));
  });
  await expect(input, 'スイッチをONにできなかった').toBeChecked();
}

/** 表示されているダイアログのOKを押す（無ければ何もしない） */
async function dismissDialog(page) {
  const ok = page.locator('ons-alert-dialog:visible')
    .locator('.alert-dialog-button', { hasText: 'OK' });
  if (await ok.count()) {
    await ok.first().click();
    await page.waitForTimeout(400);
  }
}

/**
 * 予定の追加/編集画面が閉じて、カレンダーが操作できる状態になるまで待つ。
 *
 * 保存すると「登録しました」等のダイアログが出て、OKを押すまで
 * 画面が戻らない。押さずに待つとカレンダーが隠れたままになる。
 */
async function waitForCalendarBack(page) {
  await dismissDialog(page);
  await expect(page.locator('#addScheduleForm')).toBeHidden({ timeout: 20_000 }).catch(() => {});
  await page.waitForTimeout(700);   // 戻りアニメーション
}

test.describe('予定', () => {
  test.beforeEach(async ({ page }) => {
    await gotoApp(page);
    await openTab(page, 'カレンダー');
    await expect(page.locator('.current_year_month').first()).toBeVisible({ timeout: 20_000 });
  });

  test('予定を登録し、編集して、削除できる', async ({ page }) => {
    const apiFailures = watchApiFailures(page);
    const date = targetDate();
    const title = `自動テスト予定 ${Date.now()}`;
    const edited = `${title} 編集済`;

    // --- 登録 ---
    // FABはタブごとにDOM上に存在し、非表示のものも残っている。
    // ons-page は入れ子になるため :has() では外側にも一致してしまうので、
    // 「実際に表示されているFAB」で特定する
    await page.locator('ons-fab:visible').first().click();
    await expect(page.locator('#addScheduleForm')).toBeVisible({ timeout: 15_000 });
    // OnsenUIのページ遷移アニメーションが終わるまで要素が動き続ける
    await page.waitForTimeout(700);

    await page.locator('#dateForAdd input').fill(date);
    await page.locator('#addScheduleForm input[placeholder="件名"]').fill(title);
    await page.locator('#addScheduleForm textarea[placeholder="詳細"]')
      .fill('ブラウザ自動テストが作成しました。');

    const [addRes] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/schedules') && r.request().method() === 'POST'),
      page.locator('#addScheduleForm #postBtn').click(),
    ]);
    expect(addRes.status(), '予定の登録に失敗した').toBe(200);
    await waitForCalendarBack(page);

    // --- 一覧に出ること ---
    await page.locator(`td[data-date="${date}"]:visible`).first().click();
    await expect(page.getByText(title).first(), '登録した予定が一覧に出ない')
      .toBeVisible({ timeout: 20_000 });

    // --- 編集 ---
    // #scheduleListItem0 は「その日の1件目」であって自分の予定とは限らない。
    // 残骸があると別の予定を編集してしまうので、件名で特定する
    const item = page.locator('ons-list-item').filter({ hasText: title });
    await expect(item.first()).toBeVisible({ timeout: 15_000 });
    await item.first().click();                                 // expandable を開く
    await item.first().locator('ons-button:has-text("編集")').click();
    await expect(page.locator('input[placeholder="件名"]').last()).toBeVisible({ timeout: 15_000 });
    await page.waitForTimeout(700);

    const titleInput = page.locator('input[placeholder="件名"]').last();
    await expect(titleInput, '編集画面に既存の件名が入っていない').toHaveValue(title);
    await titleInput.fill(edited);

    const [editRes] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/schedules')
        && ['POST', 'PUT'].includes(r.request().method())),
      page.locator('#postBtn').last().click(),
    ]);
    expect(editRes.status(), '予定の更新に失敗した').toBe(200);
    await waitForCalendarBack(page);

    await page.locator(`td[data-date="${date}"]:visible`).first().click();
    await expect(page.getByText(edited).first(), '編集内容が反映されていない')
      .toBeVisible({ timeout: 20_000 });

    // --- 削除 ---
    const editedItem = page.locator('ons-list-item').filter({ hasText: edited });
    await expect(editedItem.first()).toBeVisible({ timeout: 15_000 });
    await editedItem.first().click();
    const [delRes] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/schedules') && r.request().method() === 'DELETE'),
      (async () => {
        await editedItem.first().locator('ons-icon[icon="fa-trash"]').first().click();
        await page.locator('ons-alert-dialog:visible')
          .locator('.alert-dialog-button', { hasText: 'OK' }).first().click();
      })(),
    ]);
    expect(delRes.status(), '予定の削除に失敗した').toBe(200);
    await dismissDialog(page);
    await page.waitForTimeout(700);

    await page.locator(`td[data-date="${date}"]:visible`).first().click();
    await expect(page.getByText(edited)).toHaveCount(0, { timeout: 20_000 });

    expect(apiFailures, `/api/* が5xx: ${apiFailures.join(', ')}`).toHaveLength(0);
  });

  test('終日の予定を登録できる', async ({ page }) => {
    const date = targetDate();
    const title = `自動テスト終日予定 ${Date.now()}`;

    // FABはタブごとにDOM上に存在し、非表示のものも残っている。
    // ons-page は入れ子になるため :has() では外側にも一致してしまうので、
    // 「実際に表示されているFAB」で特定する
    await page.locator('ons-fab:visible').first().click();
    await expect(page.locator('#addScheduleForm')).toBeVisible({ timeout: 15_000 });
    // OnsenUIのページ遷移アニメーションが終わるまで要素が動き続ける
    await page.waitForTimeout(700);

    await page.locator('#dateForAdd input').fill(date);
    await toggleSwitchOn(page, '#allday_switch');
    await page.locator('#addScheduleForm input[placeholder="件名"]').fill(title);

    const [res] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/schedules') && r.request().method() === 'POST'),
      page.locator('#addScheduleForm #postBtn').click(),
    ]);
    expect(res.status()).toBe(200);
    await waitForCalendarBack(page);

    await page.locator(`td[data-date="${date}"]:visible`).first().click();
    await expect(page.getByText(title).first()).toBeVisible({ timeout: 20_000 });

    // 後始末
    const mine = page.locator('ons-list-item').filter({ hasText: title });
    await mine.first().click();
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/schedules') && r.request().method() === 'DELETE'),
      (async () => {
        await mine.first().locator('ons-icon[icon="fa-trash"]').first().click();
        await page.locator('ons-alert-dialog:visible')
          .locator('.alert-dialog-button', { hasText: 'OK' }).first().click();
      })(),
    ]);
  });
});
