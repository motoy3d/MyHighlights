import { test, expect } from '@playwright/test';
import { gotoApp, login, openTab, creds, watchApiFailures } from '../helpers/app.js';

test.describe('メンバーとチーム', () => {
  test('メンバー一覧が表示される', async ({ page }) => {
    const apiFailures = watchApiFailures(page);
    await gotoApp(page);
    await openTab(page, 'メンバー');

    // 空の ons-list は高さ0で toBeVisible が偽になる。
    // リスト自体ではなく、中身が出るのを待つ
    const items = page.locator('#member_list ons-list-item');
    await expect(items.first(), 'メンバーが1件も表示されていない').toBeVisible({ timeout: 25_000 });
    expect(await items.count()).toBeGreaterThan(0);

    // 「選手」セグメントが既定。数値コードの type で絞られているので、
    // ここが0件ならファクトリ/本番データの type の形を疑う
    await expect(page.locator('#members').getByText('選手').first()).toBeVisible();
    expect(apiFailures, `/api/* が5xx: ${apiFailures.join(', ')}`).toHaveLength(0);
  });

  test('設定画面が表示される', async ({ page }) => {
    await gotoApp(page);
    await openTab(page, '設定');

    await expect(page.locator('#settings')).toBeVisible();
    await expect(page.getByText('ログアウト', { exact: true })).toBeVisible();
  });

  /**
   * チーム切り替えは移行で認可の穴を塞いだ箇所なので、
   * 複数チーム所属のアカウントがあるときは必ず確認する。
   *
   *   TSUBASA_MULTI_TEAM_EMAIL / TSUBASA_MULTI_TEAM_PASSWORD
   *
   * 未設定なら skip する（シーダーのデータでは再現できないため）。
   */
  test('チームを切り替えると表示内容が入れ替わる', async ({ browser }) => {
    test.skip(!creds.multiTeamEmail,
      'TSUBASA_MULTI_TEAM_EMAIL が未設定のため skip（複数チーム所属アカウントが必要）');

    // 別アカウントでログインするため、保存済みの認証状態は使わない
    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();

    const apiFailures = watchApiFailures(page);
    await login(page, creds.multiTeamEmail, creds.multiTeamPassword);

    // <ons-select> はラッパーで、中に実体の <select> がある。
    // ラッパーに対して selectOption は使えない
    // (Error: Element is not a <select> element)
    const wrapper = page.locator('#teamSelection');
    await expect(wrapper, '複数チーム所属ならチーム選択が出るはず').toBeVisible({ timeout: 15_000 });
    const select = wrapper.locator('select');

    const options = select.locator('option');
    const count = await options.count();
    expect(count, 'チームが2つ以上ない').toBeGreaterThan(1);

    const before = await page.locator('#timeline_list').innerText();

    const secondValue = await options.nth(1).getAttribute('value');
    await select.selectOption(secondValue);
    // 切り替えでタイムラインを取り直すので、その完了を待つ
    await page.waitForResponse(
      (r) => r.url().includes('/api/posts') && r.request().method() === 'GET',
      { timeout: 20_000 }).catch(() => {});
    await page.waitForTimeout(1500);

    const after = await page.locator('#timeline_list').innerText();
    expect(after, 'チームを切り替えてもタイムラインが変わらない（認可の穴の兆候）')
      .not.toBe(before);
    expect(apiFailures, `/api/* が5xx: ${apiFailures.join(', ')}`).toHaveLength(0);
    await context.close();
  });
});
