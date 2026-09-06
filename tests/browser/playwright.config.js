import { defineConfig, devices } from '@playwright/test';

/**
 * Tsubasa⬆UP のブラウザ自動テスト。
 *
 * 接続先は環境変数で切り替える。既定は SSM ポートフォワード経由の新サーバ。
 *
 *   TSUBASA_URL=http://localhost:8080          フェーズ1・2（ポートフォワード）
 *   TSUBASA_URL=https://tsubasa.smartj.mobi    当夜のスモークテスト（hostsで新IPへ向けて）
 *
 * 認証情報もリポジトリに置かず環境変数で渡す。
 *
 *   TSUBASA_EMAIL / TSUBASA_PASSWORD           通常のテストアカウント
 *   TSUBASA_MULTI_TEAM_EMAIL / _PASSWORD       複数チーム所属のアカウント(任意)
 */
export default defineConfig({
  testDir: './specs',
  // 本番データに対して更新系を流すこともあるので、既定は直列。
  // 参照系だけを回すときは --workers=4 などで上書きしてよい。
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  // SSMポートフォワード経由はトンネルが詰まって単発で落ちることがある。
  // アプリ側の問題ではないので1回だけ再試行する
  // (2回続けて落ちるなら本物の不具合として扱ってよい)
  retries: 1,
  // SSMポートフォワード経由だとトンネルの分だけ遅い。余裕を持たせる
  // レート制限(429)に当たると Retry-After の分だけ待つので長めに取る
  timeout: 150_000,
  expect: { timeout: 20_000 },

  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],

  use: {
    baseURL: process.env.TSUBASA_URL || 'http://localhost:8080',
    locale: 'ja-JP',
    timezoneId: 'Asia/Tokyo',
    // 失敗時にだけ証跡を残す。移行作業では「何が起きたか」が命綱になる
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 20_000,
    navigationTimeout: 30_000,
  },

  projects: [
    // 最初に1回だけログインしてCookieを保存する
    // testDir が ./specs なので、ルートにある setup は testDir を明示する
    { name: 'setup', testDir: '.', testMatch: /auth\.setup\.js/ },

    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], storageState: '.auth/user.json' },
      dependencies: ['setup'],
    },
    {
      // 利用者の多くはスマートフォンから使う
      name: 'mobile',
      use: { ...devices['iPhone 13'], storageState: '.auth/user.json' },
      dependencies: ['setup'],
    },
  ],
});
