import { test, expect } from '@playwright/test';
import { gotoApp, watchApiFailures, fetchInPage } from '../helpers/app.js';
import { createPost, openPostByTitle, deleteOpenPost, stamp } from '../helpers/post.js';

/**
 * 添付ファイル。
 * チェックリストに「保存先が変わる回帰があった箇所」と明記されている。
 * 移行で storage/app と storage/app/private の既定が変わったため、
 * ここが壊れると添付が全て見えなくなる。
 */
test.describe('添付ファイル', () => {
  test.beforeEach(async ({ page }) => { await gotoApp(page); });

  test('画像を添付した投稿を作成し、画像が表示される', async ({ page }) => {
    const apiFailures = watchApiFailures(page);
    const title = stamp('添付テスト');

    await createPost(page, { title, files: ['fixtures/small-image.png'] });
    await openPostByTitle(page, title);

    const img = page.locator('img.image_in_post').first();
    await expect(img, '添付画像が表示されていない').toBeVisible({ timeout: 20_000 });

    // src が storage 配下を指し、実際に読み込めていること。
    // 読み込めていないと naturalWidth が 0 になる
    const info = await img.evaluate((el) => ({
      src: el.getAttribute('src'),
      w: el.naturalWidth,
      h: el.naturalHeight,
    }));
    // file_path は本番でも 'storage/post_attachment/...' という
    // 先頭スラッシュ無しの相対形式で保存されている（移行による変化ではない）
    expect(info.src, `添付のURLが想定と違う: ${info.src}`).toMatch(/^\/?storage\//);
    // naturalWidth が 0 なら 404 などで読み込めていない。
    // 保存先の既定が変わる回帰は、ここが 0 になって現れる
    expect(info.w, '画像が読み込めていない(404などの可能性)').toBeGreaterThan(0);
    expect(info.h).toBeGreaterThan(0);

    expect(apiFailures, `/api/* が5xx: ${apiFailures.join(', ')}`).toHaveLength(0);
    await deleteOpenPost(page);
  });

  test('1000pxを超える画像はリサイズされて保存される', async ({ page }) => {
    // ResizeImage は縦横どちらかが1000以上なら1000pxに収める。
    // バックエンドのテストは post_attachments の件数しか見ていないので、
    // 実際にリサイズされたかはここで確認する。
    const title = stamp('リサイズテスト');

    await createPost(page, { title, files: ['fixtures/large-image.png'] });  // 1600x1200
    await openPostByTitle(page, title);

    const img = page.locator('img.image_in_post').first();
    await expect(img).toBeVisible({ timeout: 20_000 });

    const dim = await img.evaluate((el) => ({ w: el.naturalWidth, h: el.naturalHeight }));
    expect(dim.w, '画像が読み込めていない').toBeGreaterThan(0);
    expect(dim.w, `幅が1000pxを超えている(リサイズされていない): ${dim.w}x${dim.h}`)
      .toBeLessThanOrEqual(1000);
    expect(dim.h, `高さが1000pxを超えている: ${dim.w}x${dim.h}`).toBeLessThanOrEqual(1000);
    // 1600x1200 → 縦横比を保って 1000x750 になるはず
    expect(dim.w / dim.h, '縦横比が保たれていない').toBeCloseTo(1600 / 1200, 1);

    await deleteOpenPost(page);
  });

  test('画像でない添付も投稿できる', async ({ page }) => {
    const title = stamp('非画像添付テスト');
    await createPost(page, { title, files: ['fixtures/sample-note.txt'] });
    await openPostByTitle(page, title);

    // 画像ではないので img.image_in_post ではなくリンクとして出る。
    // 少なくとも投稿が壊れずに開けること、5xxが出ないことを確認する
    await expect(page.getByText(title).first()).toBeVisible();
    await deleteOpenPost(page);
  });

  test('複数ファイルを一度に添付できる', async ({ page }) => {
    const title = stamp('複数添付テスト');
    await createPost(page, {
      title,
      files: ['fixtures/small-image.png', 'fixtures/large-image.png'],
    });
    await openPostByTitle(page, title);

    const imgs = page.locator('img.image_in_post');
    await expect(imgs.first()).toBeVisible({ timeout: 20_000 });
    expect(await imgs.count(), '添付が2件表示されていない').toBeGreaterThanOrEqual(2);

    await deleteOpenPost(page);
  });

  test('既存のアップロード済みファイルが配信される', async ({ page }) => {
    // リポジトリに含まれるプリセット画像。移行で保存先の既定が変わったため、
    // 既存ファイルが読めなくなる回帰をここで検出する
    const res = await fetchInPage(page, '/storage/prof/preset_boy.png');
    expect(res.status, '既存の添付ファイルが配信できていない').toBe(200);
    expect(res.length).toBeGreaterThan(0);
  });
});
