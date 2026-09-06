import { test, expect } from '@playwright/test';

/**
 * Apache設定に依存するため自動テスト(PHPUnit)では検証できない項目。
 * チェックリストの「切り替え前の動作確認」に対応する。
 */
test.describe('配信まわりの安全確認', () => {
  test('.env がWebから読めない', async ({ request }) => {
    const res = await request.get('/.env');
    expect([403, 404], `.env が ${res.status()} で返っている`).toContain(res.status());
  });

  test('添付ファイルに Content-Disposition と nosniff が付く', async ({ request }) => {
    // これが無いと、HTMLやSVGを添付された際に同一オリジンでスクリプトが動く(Stored XSS)。
    // リポジトリに含まれるプリセット画像で検証する
    const res = await request.get('/storage/prof/preset_boy.png');
    expect(res.status(), 'プリセット画像が配信できていない').toBe(200);

    const h = res.headers();
    expect(h['content-disposition'],
      `Content-Disposition が無い。mod_headers を確認: httpd -M | grep headers`)
      .toContain('attachment');
    expect(h['x-content-type-options']).toBe('nosniff');
  });

  test('ドットで始まるファイルが拒否される', async ({ request }) => {
    const res = await request.get('/.gitignore');
    expect([403, 404]).toContain(res.status());
  });

  test('ログイン画面にCSRFトークンが埋まっている', async ({ page }) => {
    await page.goto('/login');
    const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    expect(token, 'csrf-token メタタグが無い').toBeTruthy();
    expect(token.length).toBeGreaterThan(10);
  });
});
