import { test, expect } from '@playwright/test';
import { watchPageErrors } from '../helpers/app.js';

/**
 * API通信エラー時に利用者へ知らせること(#111)。
 *
 * 以前は 429 / 5xx / 通信断のどれでも画面が黙って更新されないだけで、
 * 投稿が送れていないことにも気付けなかった。
 * resources/assets/js/http-errors.js の axios interceptor がトーストを出す。
 *
 * サーバを壊すわけにはいかないので、タイムラインの一覧API(/api/posts)だけを
 * page.route で差し替えて失敗させる。他のAPIは本物に流す。
 *
 * gotoApp は一覧の中身が出るまで待つので、失敗させるテストでは使わない。
 */

const MSG_NETWORK = '通信できませんでした。電波の良いところでもう一度お試しください。';
const MSG_RATE_LIMIT = 'アクセスが集中しています。少し待ってからもう一度お試しください。';
const MSG_SERVER = 'サーバーでエラーが発生しました。時間をおいてもう一度お試しください。';

/** タイムラインの一覧API。クエリ付き(検索・次ページ)は対象にしない */
const isPostsApi = (url) => url.pathname === '/api/posts' && !url.search;

/** OnsenUI のトースト(ons.notification.toast)に出た文言 */
const toast = (page, message) => page.locator('ons-toast').getByText(message);

test.describe('API通信エラーの通知', () => {
  test('500 のとき「サーバーでエラー」のトーストが出る', async ({ page }) => {
    const jsErrors = watchPageErrors(page);
    await page.route(isPostsApi, (route) => route.fulfill({ status: 500, body: '' }));

    await page.goto('/home');
    await expect(page.locator('#timeline_page')).toBeVisible({ timeout: 30_000 });
    await expect(toast(page, MSG_SERVER)).toBeVisible({ timeout: 15_000 });

    // 既存の catch が error.response.status を読んで落ちないこと
    expect(jsErrors, `JSエラー: ${jsErrors.join(', ')}`).toHaveLength(0);
  });

  test('通信断のとき「通信できませんでした」のトーストが出て、JSエラーにならない', async ({ page }) => {
    const jsErrors = watchPageErrors(page);
    await page.route(isPostsApi, (route) => route.abort('internetdisconnected'));

    await page.goto('/home');
    await expect(page.locator('#timeline_page')).toBeVisible({ timeout: 30_000 });
    await expect(toast(page, MSG_NETWORK)).toBeVisible({ timeout: 15_000 });

    // response が無いと以前は catch 内で TypeError になっていた
    expect(jsErrors, `JSエラー: ${jsErrors.join(', ')}`).toHaveLength(0);
  });

  test('429 の後に成功すれば自動で再試行され、エラーのトーストは出ない', async ({ page }) => {
    let calls = 0;
    await page.route(isPostsApi, (route) => {
      calls += 1;
      if (calls === 1) {
        return route.fulfill({ status: 429, headers: { 'Retry-After': '1' }, body: '' });
      }
      return route.continue();
    });

    await page.goto('/home');
    await expect(page.locator('#timeline_page')).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('#timeline_list ons-list-item').first())
      .toBeVisible({ timeout: 20_000 });

    expect(calls, '429 の後に再試行されていない').toBeGreaterThanOrEqual(2);
    await expect(toast(page, MSG_RATE_LIMIT)).toHaveCount(0);
    await expect(toast(page, MSG_SERVER)).toHaveCount(0);
    await expect(toast(page, MSG_NETWORK)).toHaveCount(0);
  });
});
