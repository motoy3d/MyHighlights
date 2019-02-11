<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\LoginController;
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
   * 退会処理実行
   *
   * @return \Illuminate\Http\Response
   */
  public function __invoke(Request $request, $user_id)
  {
    //TODO 他ユーザーを退会させる場合は管理者チェック

    $user = User::findOrFail($user_id);
    if (!$user || $user->id != $user_id) { //ユーザーが自分のみ退会可能。管理者からの退会はTODO
      return response()->json(null, 404);
    }
    // 退会日時を更新
    $user->withdrawal_date = Carbon::now();
    $user->save();

    // ログアウト
    $logoutController = app()->make(LoginController::class);
    $logoutController->logout($request);
    return view('withdrawal');
  }
}
