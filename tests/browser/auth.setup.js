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

  // テストは投稿・コメント・予定を作るので、そのチームのメンバーに通知が届き、お知らせ一覧にも残る。
  // 本物の利用者がいるチームで流すと迷惑になるので、テスト用のアカウントだけが所属するチームに切り替える
  // (TSUBASA_TEAM_ID。確認用サーバでは smoke:account で作った【検証】チーム)
  if (creds.teamId) {
    const { hostname } = new URL(page.url());
    await page.context().addCookies([{ name: 'current_team_id', value: String(creds.teamId), domain: hostname, path: '/' }]);
    await page.goto('/home?launcher=true');
    await expect(page.locator('#timeline_page')).toBeVisible();
    // 所属していないチームを指定すると、サーバが所属チームのクッキーに戻す(EnsureCurrentTeamIsOwn)
    await page.waitForLoadState('networkidle');
    const cookie = (await page.context().cookies()).find((c) => c.name === 'current_team_id');
    expect(cookie && cookie.value, 'TSUBASA_TEAM_ID のチームに切り替わっていない(このアカウントが所属していない?)')
      .toBe(String(creds.teamId));
  }
  await page.context().storageState({ path: authFile });
});
