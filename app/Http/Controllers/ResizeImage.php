<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;

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
    $image = Image::make(file_get_contents($uploadedFilePath));
    // 幅か高さどちらかが1000を超えていたらリサイズ
    if ($image->width() < 1000 && $image->height() < 1000) {
      return;
    }
    $resizeWidth = null;
    $resizeHeight = null;
    // 縦横大きい方を1000pxに設定し縦横比維持してリサイズ
    if ($image->width() < $image->height()) {
      $resizeHeight = 1000;
    } else {
      $resizeWidth = 1000;
    }
    $image->resize($resizeWidth, $resizeHeight, function ($constraint) {
      $constraint->aspectRatio();
    })->save($uploadedFilePath);
  }
}