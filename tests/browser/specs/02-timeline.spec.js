import { test, expect } from '@playwright/test';
import { gotoApp, openTab, watchApiFailures, watchPageErrors } from '../helpers/app.js';

test.describe('タイムラインと投稿', () => {
  test.beforeEach(async ({ page }) => { await gotoApp(page); });

  test('投稿の一覧が表示される', async ({ page }) => {
    const list = page.locator('#timeline_list');
    await expect(list).toBeVisible();

    const items = list.locator('ons-list-item');
    await expect(items.first()).toBeVisible();
    expect(await items.count(), '投稿が1件も表示されていない').toBeGreaterThan(0);
  });

  test('投稿を開くと本文が表示される', async ({ page }) => {
    const first = page.locator('#timeline_list ons-list-item').first();
    const title = (await first.locator('.entry_title').first().innerText()).trim();

    await first.click();
    // 記事ページに遷移し、一覧で見えていた件名がどこかに出ること
    await expect(page.getByText(title, { exact: false }).first()).toBeVisible({ timeout: 15_000 });
  });

  test('投稿を作成して削除できる', async ({ page }) => {
    const apiFailures = watchApiFailures(page);
    const stamp = `自動テスト投稿 ${Date.now()}`;

    // タイムラインのFABから新規投稿へ
    await page.locator('#timeline_page ons-fab').first().click();
    await expect(page.locator('#post')).toBeVisible({ timeout: 15_000 });

    // <ons-input> はラッパーで、中に実体の <input> がある。
    // getByPlaceholder だと両方に一致してしまうので実要素を指す
    await page.locator('#post input[placeholder="件名"]').fill(stamp);
    await page.locator('#post textarea[placeholder="本文"]').fill(
      'ブラウザ自動テストが作成しました。検証後に削除されます。');

    const [res] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/posts') && r.request().method() === 'POST'),
      page.locator('#postBtn').click(),
    ]);
    expect(res.status(), '投稿の作成APIが成功していない').toBe(200);

    // 投稿直後の画面はタイムラインを再取得しないので、開き直して確認する
    await gotoApp(page);
    await expect(page.getByText(stamp).first()).toBeVisible({ timeout: 20_000 });
    expect(apiFailures, `/api/* が5xx: ${apiFailures.join(', ')}`).toHaveLength(0);

    // 後始末。残すと実行のたびに積み上がる
    await page.getByText(stamp).first().click();
    await expect(page.getByText('この投稿を削除')).toBeVisible({ timeout: 15_000 });

    const [delRes] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/posts') && r.request().method() === 'DELETE'),
      (async () => {
        await page.getByText('この投稿を削除').click();
        // $ons.notification.confirm の buttonLabels は ['キャンセル', 'OK']。
        // 設定画面の #logout_dialog / #withdrawal_dialog にも非表示のOKボタンが
        // DOM上に存在するため、必ず「表示されているダイアログ」に限定する
        await page.locator('ons-alert-dialog:visible')
          .locator('.alert-dialog-button', { hasText: 'OK' })
          .first().click();
      })(),
    ]);
    expect(delRes.status(), '削除APIが成功していない').toBe(200);

    // 開き直して、一覧から消えていること
    await gotoApp(page);
    await expect(page.getByText(stamp)).toHaveCount(0, { timeout: 20_000 });
  });

  test('タイムライン閲覧中にJSエラーも5xxも出ない', async ({ page }) => {
    const apiFailures = watchApiFailures(page);
    const jsErrors = watchPageErrors(page);

    await openTab(page, 'メンバー');
    await openTab(page, '設定');
    await openTab(page, 'タイムライン');

    expect(apiFailures, `/api/* が5xx: ${apiFailures.join(', ')}`).toHaveLength(0);
    expect(jsErrors, `JSエラー: ${jsErrors.join(', ')}`).toHaveLength(0);
  });
});
