import { test, expect } from '@playwright/test';
import { gotoApp, openTab, watchApiFailures, watchPageErrors } from '../helpers/app.js';
import { createPost, openPostByTitle, deleteOpenPost, stamp } from '../helpers/post.js';

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
    const title = stamp();

    await createPost(page, { title });

    // 投稿直後の画面はタイムラインを再取得しないので、開き直して確認する
    await gotoApp(page);
    await expect(page.getByText(title).first()).toBeVisible({ timeout: 20_000 });
    expect(apiFailures, `/api/* が5xx: ${apiFailures.join(', ')}`).toHaveLength(0);

    // 後始末
    await openPostByTitle(page, title);
    await deleteOpenPost(page);

    await gotoApp(page);
    await expect(page.getByText(title)).toHaveCount(0, { timeout: 20_000 });
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
