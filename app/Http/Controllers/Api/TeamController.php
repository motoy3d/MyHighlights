<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

/**
 * Class TeamController
 * チーム情報APIコントローラ
 * @package App\Http\Controllers\Api
 */
class TeamController extends Controller
{
  /**
   * 自分のチーム情報を返す。
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function show()
  {
    $team = Team::findOrFail(Auth::user()->team_id);
    return Response::json($team);
  }
}
