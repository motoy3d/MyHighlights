import { test, expect } from '@playwright/test';
import { gotoApp, openTab, fetchInPage, watchPageErrors, withRateLimitRetry } from '../helpers/app.js';

/**
 * #110 Web プッシュ通知の画面側。
 *
 * 設計: docs/design/110-web-push.md §6 / §9
 * - 実際に通知が届くかはここでは確かめない
 *   (Playwright 同梱の Chromium は Google のプッシュサービスに接続できない。§9 の手順で手作業で確認する)
 * - 設定画面のスイッチは /api/push/config の enabled で出し分けるので、そこだけ page.route で差し替える
 * - 起動時のリンク処理(deep-link.js)は本物の API で確かめる
 */

const PREFERENCES = {
  new_post: true,
  comment_on_mine: true,
  comment_on_others: false,
  schedule_change: true,
  schedule_comment: true,
};
// テスト用の VAPID 公開鍵の形をした値(購読は偽物に差し替えるので実際には使われない)
const DUMMY_VAPID_KEY = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U';

/** /api/push/config を差し替える */
async function mockPushConfig(page, enabled) {
  await page.route('**/api/push/config', (route) => route.fulfill({
    contentType: 'application/json',
    body: JSON.stringify({
      enabled,
      vapid_public_key: enabled ? DUMMY_VAPID_KEY : null,
      preferences: PREFERENCES,
    }),
  }));
}

/** 日本時間の今日(YYYY-MM-DD) */
function todayJst() {
  const jst = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
  const pad = (n) => String(n).padStart(2, '0');
  return `${jst.getFullYear()}-${pad(jst.getMonth() + 1)}-${pad(jst.getDate())}`;
}

test.describe('プッシュ通知の設定', () => {
  test('enabled が false なら、この端末で通知を受け取るの項目を出さない', async ({ page }) => {
    await mockPushConfig(page, false);
    const configLoaded = page.waitForResponse('**/api/push/config');
    await gotoApp(page);
    await configLoaded;
    await openTab(page, '設定');

    await expect(page.getByText('メールで通知を受け取る')).toBeVisible();
    await expect(page.locator('#push_device_item')).toHaveCount(0);
    await expect(page.locator('#push_unavailable_item')).toHaveCount(0);
  });

  test('enabled が true なら、端末の状態に応じてスイッチか理由を出す', async ({ page }, testInfo) => {
    await mockPushConfig(page, true);
    const configLoaded = page.waitForResponse('**/api/push/config');
    await gotoApp(page);
    await configLoaded;
    await openTab(page, '設定');

    await expect(page.getByText('この端末で通知を受け取る')).toBeVisible();

    if (testInfo.project.name === 'mobile') {
      // iPhone の Safari(ホーム画面から開いていない)では、スイッチの代わりに案内を出す
      await expect(page.locator('#push_unavailable_message'))
        .toHaveText('iPhoneでは、ホーム画面に追加したアイコンから開くと通知を受け取れます。');
      await expect(page.getByText('ホーム画面に追加する方法')).toBeVisible();
      return;
    }

    // デスクトップの Chromium は Push API を持つ。
    // ただしヘッドレスでは通知の許可が「拒否」になるので、その場合は拒否の案内が出ることを確かめる
    const permission = await page.evaluate(() => Notification.permission);
    if (permission === 'denied') {
      await expect(page.locator('#push_unavailable_message'))
        .toHaveText('通知が拒否されています。端末の設定からこのアプリの通知を許可してください。');
    } else {
      await expect(page.locator('#push_device_switch')).toBeVisible();
    }
  });

  test('スイッチをオンにすると購読をサーバへ送り、種類ごとの設定とテスト通知が使える', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile', 'iPhone の Safari ではスイッチを出さない');

    // 通知の許可と PushManager を偽物にする(本物の購読はプッシュサービスに接続できず失敗するため)
    await page.addInitScript(() => {
      Object.defineProperty(Notification, 'permission', { get: () => 'granted', configurable: true });
      Notification.requestPermission = () => Promise.resolve('granted');
      let subscription = null;
      const fake = () => ({
        endpoint: 'https://push.example.invalid/playwright',
        options: {},
        toJSON() { return { endpoint: this.endpoint, keys: { p256dh: 'p256dh', auth: 'auth' } }; },
        unsubscribe() { subscription = null; return Promise.resolve(true); },
      });
      PushManager.prototype.subscribe = function () { subscription = fake(); return Promise.resolve(subscription); };
      PushManager.prototype.getSubscription = function () { return Promise.resolve(subscription); };
    });
    await mockPushConfig(page, true);

    // サーバの購読・設定・テスト送信は差し替える(テスト用アカウントに購読を残さないため)
    const requests = [];
    await page.route('**/api/push/subscriptions', (route) => {
      requests.push(`${route.request().method()} subscriptions ${route.request().postData() || ''}`);
      return route.fulfill({ status: 204, body: '' });
    });
    await page.route('**/api/push/preferences', (route) => {
      requests.push(`PUT preferences ${route.request().postData()}`);
      return route.fulfill({ contentType: 'application/json', body: route.request().postData() });
    });
    await page.route('**/api/push/test', (route) => {
      requests.push('POST test');
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ sent: 2 }) });
    });

    const jsErrors = watchPageErrors(page);
    await gotoApp(page);
    await openTab(page, '設定');

    // オンにする → 購読がサーバへ送られ、種類ごとのスイッチが出る
    await page.locator('#push_device_switch').click();
    await expect(page.locator('.push_pref_item')).toHaveCount(5);
    expect(requests.find((r) => r.startsWith('POST subscriptions'))).toContain('"content_encoding"');

    // 種類ごとのスイッチを切り替えると、全体を PUT する
    await page.locator('.push_pref_item ons-switch').nth(2).click();
    await expect.poll(() => requests.find((r) => r.startsWith('PUT preferences')))
      .toContain('"comment_on_others":true');

    // テスト通知
    await page.locator('#push_test_btn').click();
    await expect(page.locator('ons-toast').getByText('テスト通知を送りました（2台）')).toBeVisible();

    // オフにする → サーバから購読を消す
    await page.locator('#push_device_switch').click();
    await expect(page.locator('.push_pref_item')).toHaveCount(0);
    await expect.poll(() => requests.find((r) => r.startsWith('DELETE subscriptions')))
      .toContain('push.example.invalid');

    expect(jsErrors, `JSエラー: ${jsErrors.join(', ')}`).toHaveLength(0);
  });

  test('Service Worker が登録される', async ({ page }) => {
    // 本番ホスト名をポートフォワードで叩くときは証明書が一致せず、Service Worker を登録できない
    test.skip(!!process.env.TSUBASA_RESOLVE, '証明書が一致しない接続では登録できない');
    await gotoApp(page);
    const supported = await page.evaluate(() => 'serviceWorker' in navigator);
    test.skip(!supported, 'このブラウザは Service Worker を持たない');

    await expect.poll(() => page.evaluate(async () => {
      const registration = await navigator.serviceWorker.getRegistration('/');
      return registration ? new URL(registration.active?.scriptURL || registration.installing?.scriptURL
        || registration.waiting?.scriptURL || '', location.origin).pathname : null;
    }), { timeout: 15_000 }).toBe('/sw.js');
  });
});

test.describe('通知のリンクで該当画面を開く', () => {
  test('?post= で投稿の詳細が開き、URL からパラメータが消える', async ({ page }) => {
    const jsErrors = watchPageErrors(page);
    await gotoApp(page);

    // 本物の投稿を 1 件使う(page.request はホストの差し替えが効かないので、画面の中から取る)
    const res = await fetchInPage(page, '/api/posts');
    expect(res.status).toBe(200);
    const post = JSON.parse(res.text).posts.data[0];
    expect(post, '投稿が 1 件も無い').toBeTruthy();

    await withRateLimitRetry(page, async () => {
      await page.goto(`/home?launcher=true&post=${post.id}`);
      // ナビゲーターに積まれた 2 枚目のページ(投稿の詳細)に、その投稿のタイトルが出る
      const article = page.locator('ons-navigator > ons-page').nth(1);
      await expect(article.locator('.entry_title')).toHaveText(post.title.trim(), { timeout: 30_000 });
    });

    // 再読み込みで開き直さないよう、パラメータは消える(launcher=true は残す)
    await expect(page).not.toHaveURL(/post=/);
    expect(new URL(page.url()).search).toBe('?launcher=true');
    expect(jsErrors, `JSエラー: ${jsErrors.join(', ')}`).toHaveLength(0);
  });

  test('?date= でカレンダーが開き、その日が選ばれる', async ({ page }) => {
    const jsErrors = watchPageErrors(page);
    const date = todayJst();

    await withRateLimitRetry(page, async () => {
      await page.goto(`/home?launcher=true&date=${date}`);
      await expect(page.locator('ons-tab[label="カレンダー"]')).toHaveClass(/active/, { timeout: 30_000 });
      await expect(page.locator(`td.selectedDate[data-date="${date}"]`)).toBeVisible({ timeout: 15_000 });
    });

    await expect(page).not.toHaveURL(/date=/);
    expect(jsErrors, `JSエラー: ${jsErrors.join(', ')}`).toHaveLength(0);
  });
});

/**
 * 通知をタップしたとき(#110)：sw.js は開きたい画面を Cache Storage に書き置きし、
 * アプリは前面に戻ったときにそれを読んで開く(iPhone では navigate による遷移が効かなかったため)。
 * ここでは sw.js の代わりに書き置きを置き、前面に戻ったことにして確かめる。
 */
test.describe('通知のタップの書き置き', () => {
  async function leave(page, url, at) {
    await page.evaluate(async ({ url, at }) => {
      const cache = await caches.open('tsubasa-deeplink');
      await cache.put('/__deeplink__', new Response(JSON.stringify({ url, at: at || Date.now() }),
        { headers: { 'Content-Type': 'application/json' } }));
    }, { url, at });
  }
  async function firstPost(page) {
    const res = await fetchInPage(page, '/api/posts');
    expect(res.status).toBe(200);
    return JSON.parse(res.text).posts.data[0];
  }

  test('前面に戻ると、書き置きの投稿が読み込み直さずに開く', async ({ page }) => {
    await gotoApp(page);
    const post = await firstPost(page);
    expect(post, '投稿が 1 件も無い').toBeTruthy();
    await page.waitForTimeout(3500); // 起動直後は書き置きを読まない期間がある
    let reloaded = false;
    page.on('framenavigated', (f) => { if (f === page.mainFrame()) reloaded = true; });
    await leave(page, '/home?launcher=true&post=' + post.id);
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    // ナビゲーターに積まれた 2 枚目のページ(投稿の詳細)に、その投稿のタイトルが出る
    const article = page.locator('ons-navigator > ons-page').nth(1);
    await expect(article.locator('.entry_title')).toHaveText(post.title.trim(), { timeout: 15000 });
    expect(reloaded, '読み込み直さずに開くはず').toBe(false);
    // 書き置きは取り出したら消える
    const left = await page.evaluate(async () => !!(await (await caches.open('tsubasa-deeplink')).match('/__deeplink__')));
    expect(left).toBe(false);
  });

  test('sw.js からの「この画面を開いて」で投稿が開き、同じタップは書き置きがあっても二度開かない', async ({ page }) => {
    await gotoApp(page);
    const post = await firstPost(page);
    expect(post, '投稿が 1 件も無い').toBeTruthy();
    await page.waitForTimeout(3500);
    const url = '/home?launcher=true&post=' + post.id;
    // sw.js は同じタップを、書き置き・知らせ(2回)の3通りで届ける
    await leave(page, url);
    await page.evaluate(async (url) => {
      const c = await caches.open('tsubasa-deeplink');
      await c.put('/__deeplink__', new Response(JSON.stringify({ url, tapId: 'tap-dup', at: Date.now() }),
        { headers: { 'Content-Type': 'application/json' } }));
      const send = () => navigator.serviceWorker.dispatchEvent(new MessageEvent('message',
        { data: { type: 'open-url', url, tapId: 'tap-dup' } }));
      send(); send();
      window.dispatchEvent(new Event('focus'));
    }, url);
    const article = page.locator('ons-navigator > ons-page').nth(1);
    await expect(article.locator('.entry_title')).toHaveText(post.title.trim(), { timeout: 15000 });
    await page.waitForTimeout(2500);
    // 投稿の画面は1枚だけ積まれる(タイムライン + 投稿 = 2枚)
    expect(await page.locator('ons-navigator > ons-page').count()).toBe(2);
  });

  test('古い書き置き(5分より前)は開かない', async ({ page }) => {
    await gotoApp(page);
    const post = await firstPost(page);
    expect(post, '投稿が 1 件も無い').toBeTruthy();
    await page.waitForTimeout(3500);
    const before = await page.locator('ons-navigator > ons-page').count();
    await leave(page, '/home?launcher=true&post=' + post.id, Date.now() - 10 * 60 * 1000);
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await page.waitForTimeout(2500);
    expect(await page.locator('ons-navigator > ons-page').count()).toBe(before);
  });
});

/**
 * 通知をタップしてもタップが sw.js に届かないとき(iPhone でアプリがバックグラウンドのとき。WebKit bug 268797)：
 * 前面に戻ったときに、バックグラウンドの間にサーバが送った通知(GET /api/push/recent)のうち、
 * 通知センターから消えたものをタップされた通知とみなす。
 * ここではテスト用のブラウザに通知が無いので、送った通知はすべて「消えた」扱いになる。
 * サーバの応答は差し替え、バックグラウンドに回って戻ったことにして確かめる。
 */
const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6.1 Mobile/15E148 Safari/604.1';
const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36';

test.describe('通知センターから消えた通知', () => {
  // iOS の不具合への対策なので、iPhone のときだけ動く
  test.use({ userAgent: IPHONE_UA });

  // Service Worker に制御されたページからの通信は、WebKit では page.route で差し替えられない。
  // sw.js の登録を止めて、サーバの応答を差し替えられるようにする（通知は元々表示されないので、
  // 「消えた通知」の判定にも影響しない）
  test.beforeEach(async ({ page }) => {
    await page.route('**/sw.js', (route) => route.abort());
  });

  // 消えた通知を調べるのは、ホーム画面のアプリで通知が許可され、この端末が購読しているときだけ。
  // テスト用のブラウザはどれも満たさないので、そう見えるように差し替える。
  // 通知センターに残っている通知は displayed(既定は無し)。
  async function actAsSubscribedHomeScreenApp(page, displayed = []) {
    await page.evaluate((displayed) => {
      Object.defineProperty(window.navigator, 'standalone', { configurable: true, get: () => true });
      if (typeof window.Notification === 'undefined') {
        window.Notification = {};
      }
      Object.defineProperty(window.Notification, 'permission', { configurable: true, get: () => 'granted' });
      const registration = {
        pushManager: { getSubscription: async () => ({ endpoint: 'https://push.example.test/this-device' }) },
        getNotifications: async () => displayed.map((nid) => ({ tag: 'x', data: { nid } })),
      };
      navigator.serviceWorker.getRegistration = async () => registration;
    }, displayed);
  }

  // バックグラウンドに回る → notices がその間に送られた → 前面に戻る。
  // notices の nid は 'nid0', 'nid1'...(指定が無ければ)
  async function backgroundAndReturn(page, notices, { subscribed = true, displayed = [] } = {}) {
    if (subscribed) {
      await actAsSubscribedHomeScreenApp(page, displayed);
    }
    await page.evaluate(() => {
      window.__vis = 'visible';
      Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => window.__vis });
      window.__vis = 'hidden';
      document.dispatchEvent(new Event('visibilitychange'));
    });
    await page.waitForTimeout(100);
    await page.route('**/api/push/recent', (route) => {
      const now = Date.now();
      route.fulfill({ json: { now, notices: notices.map((x, i) => ({ nid: 'nid' + i, ...x, at: x.old ? now - 60 * 60 * 1000 : now - 50 })) } });
    });
    await page.evaluate(() => {
      window.__vis = 'visible';
      document.dispatchEvent(new Event('visibilitychange'));
    });
  }
  async function firstPost(page) {
    const res = await fetchInPage(page, '/api/posts');
    expect(res.status).toBe(200);
    return JSON.parse(res.text).posts.data[0];
  }
  const pages = (page) => page.locator('ons-navigator > ons-page').count();

  test('バックグラウンドの間に届いた通知が1件だけ消えていれば、その投稿を読み込み直さずに開く', async ({ page }) => {
    await gotoApp(page);
    const post = await firstPost(page);
    expect(post, '投稿が 1 件も無い').toBeTruthy();
    await page.waitForTimeout(3500);
    let reloaded = false;
    page.on('framenavigated', (f) => { if (f === page.mainFrame()) reloaded = true; });
    await backgroundAndReturn(page, [{ tag: 'post-' + post.id, url: '/home?launcher=true&post=' + post.id }]);
    const article = page.locator('ons-navigator > ons-page').nth(1);
    await expect(article.locator('.entry_title')).toHaveText(post.title.trim(), { timeout: 15000 });
    expect(reloaded, '読み込み直さずに開くはず').toBe(false);
  });

  test('同じ投稿の通知が2件あっても、タップして消えた方を開く', async ({ page }) => {
    // iPhone は同じ tag の通知を置き換えずに並べる。古い方をタップしても開けること
    await gotoApp(page);
    const post = await firstPost(page);
    expect(post, '投稿が 1 件も無い').toBeTruthy();
    await page.waitForTimeout(3500);
    const url = '/home?launcher=true&post=' + post.id;
    await backgroundAndReturn(page, [
      { nid: 'older', tag: 'post-' + post.id, url },
      { nid: 'newer', tag: 'post-' + post.id, url },
    ], { displayed: ['newer'] });
    const article = page.locator('ons-navigator > ons-page').nth(1);
    await expect(article.locator('.entry_title')).toHaveText(post.title.trim(), { timeout: 15000 });
  });

  test('ホーム画面のアプリで購読していない端末では調べない', async ({ page }) => {
    // Safari のタブで開いている・通知を切っている端末には通知が表示されないので、
    // 送った通知がすべて「消えた」ように見えてしまう
    await gotoApp(page);
    await page.waitForTimeout(3500);
    const before = await pages(page);
    let asked = false;
    page.on('request', (r) => { if (r.url().includes('/api/push/recent')) asked = true; });
    await backgroundAndReturn(page, [{ tag: 'post-1', url: '/home?launcher=true&post=1' }], { subscribed: false });
    await page.waitForTimeout(3000);
    expect(asked, '問い合わせないはず').toBe(false);
    expect(await pages(page)).toBe(before);
  });

  test('2件以上消えていたら(すべて消去など)開かない', async ({ page }) => {
    await gotoApp(page);
    await page.waitForTimeout(3500);
    const before = await pages(page);
    await backgroundAndReturn(page, [
      { tag: 'post-1', url: '/home?launcher=true&post=1' },
      { tag: 'post-2', url: '/home?launcher=true&post=2' },
    ]);
    await page.waitForTimeout(3000);
    expect(await pages(page)).toBe(before);
  });

  test('バックグラウンドに回る前に送られた通知は、消えていても開かない', async ({ page }) => {
    await gotoApp(page);
    await page.waitForTimeout(3500);
    const before = await pages(page);
    await backgroundAndReturn(page, [{ tag: 'post-1', url: '/home?launcher=true&post=1', old: true }]);
    await page.waitForTimeout(3000);
    expect(await pages(page)).toBe(before);
  });

  test('sw.js からの知らせで開いた直後に、同じ通知が消えていても二度は開かない', async ({ page }) => {
    await gotoApp(page);
    const post = await firstPost(page);
    expect(post, '投稿が 1 件も無い').toBeTruthy();
    await page.waitForTimeout(3500);
    const url = '/home?launcher=true&post=' + post.id;
    // sw.js はタップの目印にサーバの nid を使う(消えた通知の判定と同じ目印)
    await page.evaluate((url) => navigator.serviceWorker.dispatchEvent(new MessageEvent('message',
      { data: { type: 'open-url', url, tapId: 'nid0' } })), url);
    const article = page.locator('ons-navigator > ons-page').nth(1);
    await expect(article.locator('.entry_title')).toHaveText(post.title.trim(), { timeout: 15000 });
    await backgroundAndReturn(page, [{ tag: 'post-' + post.id, url }]);
    await page.waitForTimeout(3000);
    expect(await pages(page)).toBe(2);
  });
});

test.describe('通知センターから消えた通知(Android)', () => {
  test.use({ userAgent: ANDROID_UA });

  test.beforeEach(async ({ page }) => {
    await page.route('**/sw.js', (route) => route.abort());
  });

  test('Android ではタップが sw.js に届くので、消えた通知からは開かない', async ({ page }) => {
    await gotoApp(page);
    await page.waitForTimeout(3500);
    const before = await page.locator('ons-navigator > ons-page').count();
    let asked = false;
    page.on('request', (r) => { if (r.url().includes('/api/push/recent')) asked = true; });
    await page.evaluate(() => {
      window.__vis = 'hidden';
      Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => window.__vis });
      document.dispatchEvent(new Event('visibilitychange'));
      window.__vis = 'visible';
      document.dispatchEvent(new Event('visibilitychange'));
    });
    await page.waitForTimeout(2500);
    expect(asked, 'Android では問い合わせない').toBe(false);
    expect(await page.locator('ons-navigator > ons-page').count()).toBe(before);
  });
});

/**
 * アプリ内のお知らせ一覧(🔔。#125)と、ホーム画面のアイコンの数(#123)。
 * サーバの応答は差し替える(WebKit では Service Worker があると差し替えが効かないので sw.js の登録を止める)。
 */
test.describe('お知らせ(🔔)', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/sw.js', (route) => route.abort());
  });

  // 通知を開放している人として、🔔の数と一覧を差し替える
  async function stubNotices(page, { enabled = true, unseen = 0, items = [] } = {}) {
    const calls = { seen: 0, opened: [] };
    await page.route('**/api/push/config', (route) => route.fulfill({
      json: { enabled, vapid_public_key: null, preferences: {} } }));
    await page.route('**/api/notices/unseen', (route) => route.fulfill({ json: { unseen: calls.seen ? 0 : unseen } }));
    await page.route('**/api/notices/seen', (route) => { calls.seen += 1; route.fulfill({ json: { unseen: 0 } }); });
    await page.route('**/api/notices/open', (route) => {
      calls.opened.push(JSON.parse(route.request().postData() || '{}'));
      route.fulfill({ json: { unseen: 0 } });
    });
    await page.route(/\/api\/notices$/, (route) => route.fulfill({ json: { unseen, items } }));
    return calls;
  }
  const bell = (page) => page.locator('#timeline_page .notice-bell');
  const notice = (over) => Object.assign({
    id: 1, nid: 'nid-a', type: 'new_post', team_id: null, title: 'テストチーム', body: '山田さんが投稿しました：練習',
    url: '/home?launcher=true', opened: false, created_at: new Date().toISOString(),
  }, over);

  test('🔔に数が出て、一覧を開くと0になり、タップで投稿が開く', async ({ page }) => {
    await page.addInitScript(() => {
      window.__badges = [];
      navigator.setAppBadge = (n) => { window.__badges.push(n); return Promise.resolve(); };
      navigator.clearAppBadge = () => { window.__badges.push(0); return Promise.resolve(); };
    });
    await gotoApp(page);
    const res = await fetchInPage(page, '/api/posts');
    const post = JSON.parse(res.text).posts.data[0];
    expect(post, '投稿が 1 件も無い').toBeTruthy();

    const calls = await stubNotices(page, {
      unseen: 3,
      items: [
        notice({ id: 2, nid: 'nid-new', body: 'まだ開いていない通知', url: '/home?launcher=true&post=' + post.id }),
        notice({ id: 1, nid: 'nid-old', body: '開いた通知', opened: true }),
      ],
    });
    await gotoApp(page);

    await expect(bell(page)).toBeVisible({ timeout: 15000 });
    await expect(bell(page).locator('.notice-bell-count')).toHaveText('3');
    // ホーム画面のアイコンの数も同じ
    await expect.poll(() => page.evaluate(() => window.__badges)).toContain(3);

    await bell(page).click();
    const list = page.locator('#notices_page');
    await expect(list.locator('.notice-item')).toHaveCount(2, { timeout: 15000 });
    await expect(list.locator('.notice-unopened')).toHaveCount(1);
    await expect(list.locator('.notice-unopened')).toContainText('まだ開いていない通知');
    // 一覧を開いたので数は 0
    expect(calls.seen).toBe(1);
    await expect(bell(page).locator('.notice-bell-count')).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => window.__badges.slice(-1)[0])).toBe(0);

    await list.locator('.notice-unopened').click();
    const article = page.locator('ons-navigator > ons-page').nth(1);
    await expect(article.locator('.entry_title')).toHaveText(post.title.trim(), { timeout: 15000 });
    expect(calls.opened).toContainEqual({ nid: 'nid-new' });
  });

  test('お知らせが無いときは「お知らせはありません」', async ({ page }) => {
    await stubNotices(page, { unseen: 0, items: [] });
    await gotoApp(page);
    await expect(bell(page)).toBeVisible({ timeout: 15000 });
    await expect(bell(page).locator('.notice-bell-count')).toHaveCount(0);
    await bell(page).click();
    await expect(page.locator('#notices_page .notices-empty')).toBeVisible({ timeout: 15000 });
  });

  test('すべて既読にすると、まだ開いていない通知が無くなる', async ({ page }) => {
    const calls = await stubNotices(page, { unseen: 2, items: [notice({ id: 2, nid: 'a' }), notice({ id: 1, nid: 'b' })] });
    await gotoApp(page);
    await bell(page).click();
    const list = page.locator('#notices_page');
    await expect(list.locator('.notice-unopened')).toHaveCount(2, { timeout: 15000 });
    await list.locator('.notices-open-all').click();
    await expect(list.locator('.notice-unopened')).toHaveCount(0);
    expect(calls.opened).toContainEqual({ all: true });
  });

  test('チーム名は複数のチームに所属している人にだけ出す', async ({ page }) => {
    await stubNotices(page, { unseen: 1, items: [notice({ title: '横浜SCつばさ' })] });
    // 1 チームだけに所属している人として見せる(/api/me の所属チームを 1 つに絞る)
    let singleTeam = true;
    await page.route('**/api/me', async (route) => {
      const response = await route.fetch();
      const json = await response.json();
      if (singleTeam && Array.isArray(json.myTeams)) {
        json.myTeams = json.myTeams.slice(0, 1);
      }
      route.fulfill({ response, json });
    });
    await gotoApp(page);
    await bell(page).click();
    const meta = page.locator('#notices_page .notice-meta').first();
    await expect(meta).toBeVisible({ timeout: 15000 });
    await expect(meta).not.toContainText('横浜SCつばさ');

    // 複数のチームに所属している人には出す(テスト用のアカウントは複数のチームに所属している)
    singleTeam = false;
    await gotoApp(page);
    await bell(page).click();
    await expect(page.locator('#notices_page .notice-meta').first()).toContainText('横浜SCつばさ', { timeout: 15000 });
  });

  test('通知を開放していない人には🔔を出さない', async ({ page }) => {
    await stubNotices(page, { enabled: false, unseen: 5 });
    await gotoApp(page);
    await page.waitForTimeout(3000);
    await expect(bell(page)).toHaveCount(0);
  });
});
