import { test, expect } from '@playwright/test';
import { gotoApp, watchApiFailures } from '../helpers/app.js';
import { openNewPost, openPostByTitle, deleteOpenPost, stamp } from '../helpers/post.js';

/**
 * アンケートの作成・回答・CSV出力。
 *
 * 作成は Post.vue（新規投稿画面）、回答と集計は Article.vue。
 * EditPost.vue は既存アンケートへの選択肢追加のみで、新規作成はできない。
 */
test.describe('アンケート', () => {
  test.beforeEach(async ({ page }) => { await gotoApp(page); });

  test('アンケート付き投稿を作成し、回答して、CSVを取得できる', async ({ page }) => {
    const apiFailures = watchApiFailures(page);
    const title = stamp('アンケートテスト');
    const qTitle = '次回の練習に参加できますか';
    const item1 = '9月20日(土)';
    const item2 = '9月21日(日)';

    // --- アンケート付きで投稿を作成 ---
    await openNewPost(page);
    await page.waitForTimeout(700);   // ページ遷移アニメーション

    await page.locator('ons-button:has-text("アンケート作成")').click();
    await expect(page.locator('#createQuestionnaireForm')).toBeVisible({ timeout: 15_000 });

    await page.locator('input[placeholder="タイトル・質問"]').fill(qTitle);
    await page.locator('input[placeholder="選択肢1"]').fill(item1);
    await page.locator('ons-button:has-text("選択肢追加")').click();
    await page.locator('input[placeholder="選択肢2"]').fill(item2);
    await page.locator('ons-button:has-text("作成する")').click();

    // モーダルが閉じ、投稿画面にアンケートが載ること
    await expect(page.getByText(qTitle).first()).toBeVisible({ timeout: 15_000 });

    await page.locator('#postForm input[placeholder="件名"]').fill(title);
    await page.locator('#postForm textarea[placeholder="本文"]').fill('アンケートの自動テストです。');

    const [postRes] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/posts') && r.request().method() === 'POST'),
      page.locator('#postBtn').click(),
    ]);
    expect(postRes.status(), 'アンケート付き投稿の作成に失敗した').toBe(200);

    // --- 投稿を開いてアンケートが表示されること ---
    await openPostByTitle(page, title);
    await expect(page.getByText(qTitle).first(), 'アンケートが表示されない').toBeVisible();
    await expect(page.getByText(item1).first()).toBeVisible();
    await expect(page.getByText(item2).first()).toBeVisible();

    // --- 回答する ---
    const [ansRes] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/questionnaire')),
      (async () => {
        await page.locator('ons-button:has-text("回答")').first().click();
        // アクションシートの選択肢は ['◯','△','✕','回答削除','閉じる']
        await page.locator('ons-action-sheet:visible')
          .locator('ons-action-sheet-button', { hasText: '◯' }).first().click();
      })(),
    ]);
    expect(ansRes.status(), 'アンケートの回答に失敗した').toBe(200);

    // 集計が0から1になること
    await expect(page.locator('td.answer').first()).toContainText('1', { timeout: 15_000 });

    // --- CSVを取得できること ---
    const csvLink = page.getByText('全回答結果を見る');
    await expect(csvLink).toBeVisible();
    const href = await csvLink.getAttribute('href');
    expect(href, 'CSVのリンクが想定と違う').toContain('questionnaire_download/');

    const csv = await page.request.get(href.startsWith('/') ? href : `/${href}`);
    expect(csv.status(), 'CSVがダウンロードできない').toBe(200);
    const body = await csv.text();
    expect(body, 'CSVに設問が含まれていない').toContain(item1);

    expect(apiFailures, `/api/* が5xx: ${apiFailures.join(', ')}`).toHaveLength(0);

    await deleteOpenPost(page);
  });
});
