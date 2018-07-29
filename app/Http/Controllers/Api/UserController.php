<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;

/**
 * Class UserController
 * ユーザー情報APIコントローラ
 * @package App\Http\Controllers\Api
 */
class UserController extends Controller
{
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
    // 現在のパスワードのチェック
    if (Hash::check($request->current_password, Auth::user()->password)) {
      $user->fill([
        'password' => Hash::make($request->newPassword),
        'updated_id' => Auth::id()
      ]);
      $user = $user->save();
      return Response::json($user);
    } else {
      // TODO メッセージリソース化
      return response()->json('現在のパスワードが違います', 400);
    }
  }

}
