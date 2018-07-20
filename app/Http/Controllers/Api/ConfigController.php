<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

/**
 * チーム単位や全体の設定に関するコントローラ
 * @package App\Http\Controllers\Api
 */
class ConfigController extends Controller
{
  /**
   * ユーザーのteam_idの設定リストと、全チーム共通設定リストを返す。
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function show()
  {
    $teamConfigs = DB::table('configs')
      ->where('team_id', Auth::user()->team_id)
      ->get();
    if (!$teamConfigs) {
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    // 全チーム共有設定
    $allTeamsConfigs = DB::table('configs')
      ->where('team_id', 'all')
      ->get();
    $configs = [
      'team' => $teamConfigs,
      'all' => $allTeamsConfigs
    ];
    Response::json($configs);
  }
}
