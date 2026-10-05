import { test, expect } from '@playwright/test';
import { login, logout, creds, watchApiFailures, watchPageErrors } from '../helpers/app.js';

// 認証そのものを試すので、保存済みのログイン状態は使わない
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('認証', () => {
  test('ログイン画面が表示崩れなく描画される', async ({ page }) => {
    await page.goto('/login');

    // OnsenUIがCDNではなくバンドルから効いていること。
    // 効いていないと枠線の無い素のテキストとリンクになる
    await expect(page.locator('#login_form')).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('#login_btn')).toBeVisible();
    await expect(page).toHaveTitle(/Tsubasa/);
  });

  test('OnsenUIをCDNから読んでいない', async ({ page }) => {
    // 移行でバンドルに移した。CDN依存が復活すると外部障害で落ちるようになる
    const external = [];
    page.on('request', (req) => {
      const u = req.url();
      if (u.includes('cdnjs.cloudflare.com') || u.includes('unpkg.com')) external.push(u);
    });
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    expect(external, `CDNへのリクエストが復活している: ${external.join(', ')}`).toHaveLength(0);
  });

  test('誤ったパスワードではログインできない', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill(creds.email);
    await page.locator('#password').fill('definitely-not-the-password');
    await page.locator('#login_btn').click();

    await expect(page.locator('#login_form')).toBeVisible();
    await expect(page.locator('#timeline_page')).toHaveCount(0);
  });

  test('ログインしてタイムラインに到達できる', async ({ page }) => {
    const apiFailures = watchApiFailures(page);
    const jsErrors = watchPageErrors(page);

    await login(page);

    await expect(page.locator('#timeline_page')).toBeVisible();
    expect(apiFailures, `/api/* が5xxを返した: ${apiFailures.join(', ')}`).toHaveLength(0);
    expect(jsErrors, `JSエラー: ${jsErrors.join(', ')}`).toHaveLength(0);
  });

  test('ログアウトできる', async ({ page }) => {
    await login(page);
    await logout(page);
    await expect(page.locator('#login_form')).toBeVisible();
  });

  test('未認証で /home を開くとログインに戻される', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/home');
    await expect(page.locator('#login_form')).toBeVisible();
  });
});
