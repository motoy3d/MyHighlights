<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\LoginController;
use App\Member;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class WithdrawalController extends Controller
{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct()
  {
    $this->middleware('auth');
  }

  /**
   * チームからの退会処理実行。複数チームに所属している場合は最後のチームを退会した時にログイン不可となる。
   * @param Request $request
   * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View
   */
  public function __invoke(Request $request)
  {
    //TODO 他ユーザーを退会させる場合は管理者チェック
    $user_id = $request->user_id;
    $team_id = $request->team_id;

    $user = User::findOrFail($user_id);
    if (!$user || $user->id != $user_id) { //ユーザーが自分のみ退会可能。管理者からの退会はTODO
      return response()->json(null, 404);
    }
    // 有効な(退会していない)member取得
    $member = Member::where('user_id', $user_id)
      ->where('team_id', $team_id)
      ->whereNull('withdrawal_date')
      ->whereNull('deleted_at')
      ->first();
    if (!$member) {
      return response()->json(null, 404); //なければエラー
    }

    // memberの退会日時を更新
    $member->withdrawal_date = Carbon::now();
    $member->save();

    // 有効な(退会していない)memberが残っているか確認
    $member = Member::where('user_id', $user_id)
      ->whereNull('withdrawal_date')
      ->whereNull('deleted_at')
      ->first();
    // 有効なmemberが残っていなければ、userも退会とする
    if (!$member) {
      // 退会日時を更新
      $user->withdrawal_date = Carbon::now();
      $user->save();
    }

    // ログアウト
    $logoutController = app()->make(LoginController::class);
    $logoutController->logout($request);
    return view('withdrawal');
  }
}
