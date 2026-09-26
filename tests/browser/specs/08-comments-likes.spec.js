import { test, expect } from '@playwright/test';
import { gotoApp, watchApiFailures } from '../helpers/app.js';
import { createPost, openPostByTitle, deleteOpenPost, stamp } from '../helpers/post.js';

/**
 * コメントといいね。Article.vue(765行)が担当する範囲で、
 * Vue 3 化の影響を最も受けるコンポーネントのひとつ。
 */
test.describe('コメントといいね', () => {
  let title;

  test.beforeEach(async ({ page }) => {
    await gotoApp(page);
    title = stamp('コメントテスト');
    await createPost(page, { title });
    await openPostByTitle(page, title);
  });

  test.afterEach(async ({ page }) => {
    // 投稿を消せばコメントも一緒に消える
    try { await deleteOpenPost(page); } catch { /* 既に消えている場合は無視 */ }
  });

  test('コメントを投稿できる', async ({ page }) => {
    const apiFailures = watchApiFailures(page);
    const text = `自動テストのコメント ${Date.now()}`;

    await page.locator('textarea.comment_textarea').fill(text);
    const [res] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/post_comments') && r.request().method() === 'POST'),
      page.locator('ons-button:has(ons-icon[icon="fa-paper-plane"])').first().click(),
    ]);
    expect(res.status(), 'コメント投稿に失敗した').toBe(200);

    await expect(page.getByText(text).first(), 'コメントが表示されない')
      .toBeVisible({ timeout: 15_000 });
    expect(apiFailures, `/api/* が5xx: ${apiFailures.join(', ')}`).toHaveLength(0);
  });

  test('投稿にいいねができ、解除もできる', async ({ page }) => {
    const heart = page.locator('ons-icon.heart').first();
    await expect(heart).toBeVisible();

    // いいねする
    const [onRes] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/post_responses')),
      heart.click(),
    ]);
    expect(onRes.status()).toBe(200);
    await expect(page.locator('.heart-count').first(), 'いいね数が表示されない')
      .toBeVisible({ timeout: 10_000 });

    // 解除する
    const [offRes] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/post_responses')),
      heart.click(),
    ]);
    expect(offRes.status()).toBe(200);
  });

  test('コメントを削除できる', async ({ page }) => {
    const text = `削除するコメント ${Date.now()}`;

    await page.locator('textarea.comment_textarea').fill(text);
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/post_comments') && r.request().method() === 'POST'),
      page.locator('ons-button:has(ons-icon[icon="fa-paper-plane"])').first().click(),
    ]);
    await expect(page.getByText(text).first()).toBeVisible({ timeout: 15_000 });

    const [res] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/post_comments') && r.request().method() === 'DELETE'),
      (async () => {
        await page.locator('ons-icon.delete_comment_icon').first().click();
        await page.locator('ons-alert-dialog:visible')
          .locator('.alert-dialog-button', { hasText: 'OK' }).first().click();
      })(),
    ]);
    expect(res.status(), 'コメント削除に失敗した').toBe(200);
    await expect(page.getByText(text)).toHaveCount(0, { timeout: 15_000 });
  });

  test('添付付きのコメントを投稿できる', async ({ page }) => {
    const text = `添付付きコメント ${Date.now()}`;

    // コメント欄の添付は .upload-btn-wrapper の中の file input
    await page.locator('.upload-btn-wrapper input[type="file"]').first()
      .setInputFiles('fixtures/small-image.png');
    await page.locator('textarea.comment_textarea').fill(text);

    const [res] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/post_comments') && r.request().method() === 'POST'),
      page.locator('ons-button:has(ons-icon[icon="fa-paper-plane"])').first().click(),
    ]);
    expect(res.status()).toBe(200);
    await expect(page.getByText(text).first()).toBeVisible({ timeout: 15_000 });
  });
});
