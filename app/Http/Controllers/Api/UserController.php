<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Controller;
use App\Member;
use App\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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
    $currentTeamId = Cookie::get('current_team_id');
    if (!$currentTeamId) { //ここでCookieにセットされていると思っていたが、されていないエラーが出たので再度セット。
      $team = $teams[0];
      $currentTeamId = $team->id;
      $currentTeamName = $team->name;
      $minutes = env('SESSION_LIFETIME', 129600);
      $path = "/";
      $domain = "";
      $secure = env('APP_ENV', 'production') == 'production';
      $httpOnly = false; //jsで扱うために必要
      Cookie::queue(Cookie::make('current_team_id', $currentTeamId,
        $minutes, $path, $domain, $secure, $httpOnly));
      Cookie::queue(Cookie::make('current_team_name', $currentTeamName,
        $minutes, $path, $domain, $secure, $httpOnly));
    }
    $member = Member::where('user_id', Auth::id())
      ->where('team_id', $currentTeamId)
      ->first();
    Auth::user()->currentTeamAdminFlg = $member->admin_flg;
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
   * ユーザー名かなの更新。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function updateNameKana(Request $request)
  {
    //TODO validate
    $user = User::findOrFail(Auth::user()->id);
    if (!$user) {// ヒットしない場合は404
      return response()->json(null, 404);
    }

    $user->name_kana = $request->name_kana;
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
   * ユーザーのメール通知フラグの更新。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function updateMailNotificationFlg(Request $request)
  {
    //TODO validate
    Log::info("メール通知フラグ " . $request->mail_notification_flg);
    $user = Auth::user();
    $user->mail_notification_flg = $request->mail_notification_flg;
    $user->save();

    return Response::json($user);
  }
}
