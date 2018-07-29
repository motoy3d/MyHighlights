<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * 全体の設定に関するコントローラ
 * @package App\Http\Controllers\Api
 */
class ConfigController extends Controller
{
  /**
   * 設定リストを返す。
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function show()
  {
    $configs = DB::table('configs')->get();
    Response::json($configs);
  }
}
