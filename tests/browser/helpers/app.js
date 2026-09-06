import { expect } from '@playwright/test';

/**
 * 画面操作の共通処理。
 *
 * セレクタの方針:
 *   Vue 2 → Vue 3 の移行後もそのまま通ることを狙って、
 *   「利用者に見える文字列」と「テンプレートに書かれた id」だけを使う。
 *   OnsenUI が内部で生成するクラス名には依存しない。
 *   (依存すると、UIライブラリを入れ替えた瞬間に全部落ちて、
 *    本当の不具合と区別が付かなくなる)
 */

export const creds = {
  email: process.env.TSUBASA_EMAIL || 'test@example.com',
  password: process.env.TSUBASA_PASSWORD || 'password',
  multiTeamEmail: process.env.TSUBASA_MULTI_TEAM_EMAIL || '',
  multiTeamPassword: process.env.TSUBASA_MULTI_TEAM_PASSWORD || '',
};

/** ログインし、タイムラインが描画されるまで待つ */
export async function login(page, email = creds.email, password = creds.password) {
  await page.goto('/login');
  await expect(page.locator('#login_form')).toBeVisible();

  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#login_btn').click();

  // SPAの初期化を待つ。URLだけ見ると描画前に進んでしまう。
  // ons-page はDOMに残り続けるので、一覧の中身が来るまで待つ
  await expect(page.locator('#timeline_page')).toBeVisible({ timeout: 20_000 });
  await expect(page.locator('#timeline_list ons-list-item').first())
    .toBeVisible({ timeout: 30_000 });
}

/**
 * 保存済みの認証状態でアプリを開く。
 * 各テストが毎回ログインするとポートフォワード経由では遅すぎるため、
 * 通常のテストはこちらを使う。
 */
/**
 * 保存済みの認証状態でアプリを開き、タイムラインの中身が出るまで待つ。
 *
 * レート制限(429)を踏んだ場合は Retry-After の分だけ待って開き直す。
 * bootstrap/app.php の throttleApi('60,1') は 60リクエスト/分で、
 * スイートを通しで回すと1つのIPからの合算で普通に超える。
 * 利用者1人では当たらない水準なのでアプリの不具合ではないが、
 * アプリ側に429のハンドリングが無いため画面が黙って空になる。
 * (本番設定をテストの都合で緩めるのは筋が違うので、こちらが待つ)
 */
/**
 * 429(レート制限)を踏んだら Retry-After の分だけ待って処理をやり直す。
 *
 * bootstrap/app.php の throttleApi('60,1') は 60リクエスト/分。
 * スイートを通しで回すと1つのIPからの合算で超える。
 * アプリ側に429のハンドリングが無いため、踏むと画面が
 * 「ごめんなさい。エラーになりました」になって何も表示されない。
 */
/**
 * スイート全体の流量を throttleApi('60,1') = 60リクエスト/分 に収める。
 *
 * gotoApp は1回で約5本のAPIを叩き、ほぼ全テストの起点になる。
 * ここで最低間隔を空けることで、スイート全体の流量が自然に下がる。
 * 429を踏んでからリトライで待つより、最初から踏まない方が速い
 * (Retry-Afterは30〜40秒返ってくるため)。
 *
 * PACE_MS を 0 にすれば無効化できる。単体のテストを流すときなど。
 */
const PACE_MS = Number(process.env.TSUBASA_PACE_MS ?? 5000);
let lastBurstAt = 0;

async function pace(page) {
  if (!PACE_MS) return;
  const wait = PACE_MS - (Date.now() - lastBurstAt);
  if (wait > 0) await page.waitForTimeout(wait);
  lastBurstAt = Date.now();
}

export async function withRateLimitRetry(page, fn, { attempts = 3 } = {}) {
  let last;
  for (let i = 0; i < attempts; i++) {
    let retryAfter = 0;
    const onResponse = (res) => {
      if (res.url().includes('/api/') && res.status() === 429) {
        retryAfter = Math.max(retryAfter, Number(res.headers()['retry-after'] || 10));
      }
    };
    page.on('response', onResponse);
    try {
      const r = await fn();
      page.off('response', onResponse);
      return r;
    } catch (e) {
      page.off('response', onResponse);
      last = e;
      if (!retryAfter) throw e;
      // eslint-disable-next-line no-console
      console.log(`  レート制限(429)。${retryAfter + 1}秒待って再試行`);
      await page.waitForTimeout((retryAfter + 1) * 1000);
    }
  }
  throw last;
}

export async function gotoApp(page, { attempts = 3 } = {}) {
  await pace(page);
  let lastError;

  for (let i = 0; i < attempts; i++) {
    let retryAfter = 0;
    const onResponse = (res) => {
      if (res.url().includes('/api/') && res.status() === 429) {
        retryAfter = Math.max(retryAfter, Number(res.headers()['retry-after'] || 10));
      }
    };
    page.on('response', onResponse);

    try {
      await page.goto('/home');
      // OnsenUIは全ページをDOMに残すので #timeline_page の可視性では
      // 「描画が終わった」ことにならない。一覧の中身が入るまで待つ
      await expect(page.locator('#timeline_page')).toBeVisible({ timeout: 30_000 });
      await expect(page.locator('#timeline_list ons-list-item').first())
        .toBeVisible({ timeout: 15_000 });
      page.off('response', onResponse);
      return;
    } catch (e) {
      page.off('response', onResponse);
      lastError = e;
      if (!retryAfter) throw e;   // 429以外は素直に失敗させる
      // eslint-disable-next-line no-console
      console.log(`  レート制限(429)。${retryAfter + 1}秒待って開き直す`);
      await page.waitForTimeout((retryAfter + 1) * 1000);
    }
  }
  throw lastError;
}

/** 設定画面からログアウトする */
export async function logout(page) {
  await openTab(page, '設定');
  await page.getByText('ログアウト', { exact: true }).click();
  await expect(page.locator('#logout_dialog')).toBeVisible();
  await page.locator('#logout_dialog').getByText('OK', { exact: true }).click();
  await expect(page.locator('#login_form')).toBeVisible({ timeout: 20_000 });
}

/**
 * 下部タブを切り替える。label は タイムライン/カレンダー/メンバー/設定。
 *
 * <ons-tab label="設定"> の中の button を押す。label属性はVueのデータから
 * 渡っているので、Vue 3 化してもそのまま残る。
 * OnsenUIが生成する tabbar__item のようなクラス名には依存しない。
 */
export async function openTab(page, label) {
  await page.locator(`ons-tab[label="${label}"] button`).first().click();
  // OnsenUIは全ページをDOMに残したままにするので、ons-page の可視性では
  // 切り替わったか判定できない。active クラスが付いたかで見る
  await expect(page.locator(`ons-tab[label="${label}"]`)).toHaveClass(/active/, { timeout: 15_000 });
  await page.waitForTimeout(500); // 切替アニメーション
}

/** いま表示されているタブのラベル */
export async function activeTabLabel(page) {
  return page.locator('ons-tab.active').first().getAttribute('label');
}

/**
 * /api/* に500が出ていないことを監視する。
 * 移行時に踏んだ「ログインはできるがデータが出ない」を検出するための網。
 * storage/framework/cache/data の所有者が root になっていると再発する。
 */
export function watchApiFailures(page) {
  const failures = [];
  page.on('response', (res) => {
    const url = res.url();
    if (url.includes('/api/') && res.status() >= 500) {
      failures.push(`${res.status()} ${url}`);
    }
  });
  return failures;
}

/**
 * ページ内のJSエラーを集める。
 *
 * 429(レート制限)由来のものは除外する。
 * bootstrap/app.php で throttleApi('60,1') = 60リクエスト/分 が効いており、
 * テストを連続で回すと1つのIPからの合算で普通に当たる。
 * 利用者1人あたりでは当たらない水準なので、アプリの不具合ではない。
 *
 * ただし「429が未処理のPromise拒否になる」こと自体は
 * アプリ側の弱点である。axiosのinterceptorが無く、429も500も
 * ネットワーク断も、すべて画面が黙って更新されないだけになる。
 * (移行中に踏んだ /api/* が全て500になる不具合が読みにくかったのも同じ理由)
 * 429を区別して数えたい場合は rateLimited を見る。
 */
export function watchPageErrors(page) {
  const errors = [];
  const rateLimited = [];
  page.on('pageerror', (e) => {
    const msg = String(e);
    if (/status code 429/.test(msg)) rateLimited.push(msg);
    else errors.push(msg);
  });
  errors.rateLimited = rateLimited;
  return errors;
}
