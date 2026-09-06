import { test as setup, expect } from '@playwright/test';
import { login, creds } from './helpers/app.js';

/**
 * ログインを1回だけ行い、Cookieを .auth/user.json に保存する。
 *
 * 各テストが毎回ログインすると、SSMポートフォワード経由では
 * トンネルを叩きすぎてタイムアウトする(Apacheに408が出る)。
 * 認証状態を使い回すことで、実行時間もトンネルの負荷も大きく下がる。
 */
const authFile = '.auth/user.json';

setup('ログインして認証状態を保存する', async ({ page }) => {
  await login(page, creds.email, creds.password);
  await expect(page.locator('#timeline_page')).toBeVisible();
  await page.context().storageState({ path: authFile });
});
