<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

trait ResizeImage
{
  /**
   * リサイズ対象の画像かどうかを、保存後のファイル名の拡張子で判定する。
   *
   * 保存時の拡張子はアップロードされた内容から判定されたものなので、
   * クライアントが送ってきたファイル名より信用できる。
   */
  public function isResizableImage(string $filePath): bool
  {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true);
  }

  /**
   * ファイルが画像だった場合で、縦横どちらか1000px以上だった場合に、
   * 大きい辺を1000pxにしてリサイズして上書き保存する
   * @param $filePath
   */
  public function resizeImage($filePath): void
  {
    $uploadedFilePath = storage_path() . '/app/' . $filePath;
    Log::info("resizeImage=$uploadedFilePath");

    try {
      // intervention/image 3系はファサードのImage::make()を廃止したためImageManagerを直接使う
      $image = (new ImageManager(new Driver()))->read($uploadedFilePath);
    } catch (Throwable $e) {
      // 拡張子が画像でも中身が壊れている場合がある。
      // 添付自体は成功させたいのでリサイズだけ諦める。
      Log::warning("画像として読めなかったためリサイズをスキップ: $uploadedFilePath ({$e->getMessage()})");

      return;
    }

    // 幅か高さどちらかが1000を超えていたらリサイズ
    if ($image->width() < 1000 && $image->height() < 1000) {
      return;
    }

    // 縦横比を保ったまま1000px四方に収める(拡大はしない)
    $image->scaleDown(width: 1000, height: 1000)->save($uploadedFilePath);
  }
}
