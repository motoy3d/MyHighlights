<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Controller;
use App\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

/**
 * Class UserController
 * ユーザー情報APIコントローラ
 * @package App\Http\Controllers\Api
 */
class UserController extends Controller
{
  /**
   * ユーザーセッション情報を返す。
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getMe(Request $request) {
    $teams = Auth::user()->teams()->orderBy('created_at')->get();
    Auth::user()->myTeams = $teams;
    return Response::json(Auth::user());
  }
  /**
   * ユーザー名の更新。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function updateName(Request $request)
  {
    //TODO validate
    $user = User::findOrFail(Auth::user()->id);
    if (!$user) {// ヒットしない場合は404
      return response()->json(null, 404);
    }

    $user->name = $request->name;
    $user->updated_id = Auth::id();
    $user = $user->save();
    return Response::json($user);
  }

  /**
   * ユーザーメールアドレスの更新。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function updateEmail(Request $request)
  {
    //TODO validate
    $user = User::findOrFail(Auth::user()->id);
    if (!$user) {// ヒットしない場合は404
      return response()->json(null, 404);
    }

    $user->email = $request->email;
    $user->updated_id = Auth::id();
    $user = $user->save();
    return Response::json($user);
  }

  /**
   * ユーザーのパスワードの更新。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function updatePassword(Request $request)
  {
    //TODO validate
    $user = Auth::user();
//    Log::debug('new_password = ' . $request->new_password);
    // 現在のパスワードのチェック
//    if (Hash::check($request->current_password, Auth::user()->password)) {

    $user->password = Hash::make($request->new_password);

    $user->setRememberToken(Str::random(60));

    $user->save();

    event(new PasswordReset($user));

    return Response::json($user);
//    } else {
//      // TODO メッセージリソース化
//      return response()->json('現在のパスワードが違います', 400);
//    }
  }

  /**
   * 退会する。
   * @param Request $request
   * @param $user_id 退会するユーザーのID
   * @return \Illuminate\Http\JsonResponse
   */
  public function withdraw(Request $request, $user_id)
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
    return $logoutController->logout($request);
  }

}
