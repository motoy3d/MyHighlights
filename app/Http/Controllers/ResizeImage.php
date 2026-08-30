<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

trait ResizeImage
{
  /**
   * ファイルが画像だった場合で、縦横どちらか1000px以上だった場合に、
   * 大きい辺を1000pxにしてリサイズして上書き保存する
   * @param $filePath
   */
  public function resizeImage($filePath): void
  {
    $uploadedFilePath = storage_path() . '/app/' . $filePath;
    Log::info("resizeImage=$uploadedFilePath");

    // intervention/image 3系はファサードのImage::make()を廃止したためImageManagerを直接使う
    $image = (new ImageManager(new Driver()))->read($uploadedFilePath);

    // 幅か高さどちらかが1000を超えていたらリサイズ
    if ($image->width() < 1000 && $image->height() < 1000) {
      return;
    }

    // 縦横比を保ったまま1000px四方に収める(拡大はしない)
    $image->scaleDown(width: 1000, height: 1000)->save($uploadedFilePath);
  }
}
