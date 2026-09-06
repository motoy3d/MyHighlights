import { expect } from '@playwright/test';
import { gotoApp } from './app.js';

/**
 * 投稿まわりの共通操作。
 *
 * 注意: <v-ons-page id="post"> は EditPost / AddSchedule / EditSchedule /
 * Member / AddMember / ICal / Post の7コンポーネントで重複している。
 * OnsenUIは全ページをDOMに残すため #post は同時に複数存在しうる。
 * そのため画面の特定にはフォームIDなど一意なものを使う。
 */

/** 表示されているダイアログのOKを押す（無ければ何もしない） */
export async function dismissDialog(page) {
  const ok = page.locator('ons-alert-dialog:visible')
    .locator('.alert-dialog-button', { hasText: 'OK' });
  if (await ok.count()) {
    await ok.first().click();
    await page.waitForTimeout(500);
  }
}

/**
 * 新規投稿画面を開く（タイムラインのFABから）。
 *
 * OnsenUIのページ遷移はアニメーションするので、要素が見えた直後は
 * まだ動いている。待たずに操作すると、クリックが前の画面の同名要素
 * (#postBtn は複数コンポーネントで重複している)に当たって
 * 何も起きないことがある。
 */
export async function openNewPost(page) {
  await page.locator('#timeline_page ons-fab').first().click();
  await expect(page.locator('#postForm')).toBeVisible({ timeout: 15_000 });
  await page.waitForTimeout(700);
}

/**
 * 投稿を作成する。files を渡すと添付付きで投稿する。
 * 作成APIのレスポンスを待って返す。
 */
export async function createPost(page, { title, body = '自動テストの本文', files = [] } = {}) {
  await openNewPost(page);
  await page.locator('#postForm input[placeholder="件名"]').fill(title);
  await page.locator('#postForm textarea[placeholder="本文"]').fill(body);

  if (files.length) {
    await page.locator('#postForm input[type="file"]').setInputFiles(files);
  }

  const [res] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/posts') && r.request().method() === 'POST'),
    page.locator('#postBtn').click(),
  ]);
  expect(res.status(), '投稿の作成に失敗した').toBe(200);

  // 投稿後は「投稿しました」のダイアログが出て、OKを押すまで
  // afterPost()(画面を戻して一覧を再取得)が走らない
  await dismissDialog(page);
  return res;
}

/** タイムラインを開き直して、件名で投稿を開く */
export async function openPostByTitle(page, title) {
  await gotoApp(page);
  await page.getByText(title).first().click();
  await expect(page.getByText('この投稿を削除')).toBeVisible({ timeout: 15_000 });
}

/** 開いている投稿を削除する */
export async function deleteOpenPost(page) {
  const [res] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/posts') && r.request().method() === 'DELETE'),
    (async () => {
      await page.getByText('この投稿を削除').click();
      // 設定画面の #logout_dialog などにも非表示のOKがあるので、
      // 必ず「表示されているダイアログ」に限定する
      await page.locator('ons-alert-dialog:visible')
        .locator('.alert-dialog-button', { hasText: 'OK' }).first().click();
    })(),
  ]);
  expect(res.status(), '投稿の削除に失敗した').toBe(200);
}

/** 一意な件名 */
export function stamp(prefix = '自動テスト投稿') {
  return `${prefix} ${Date.now()}`;
}
