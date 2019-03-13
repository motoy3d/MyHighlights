<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitation;
use App\Member;
use App\Team;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;

class MemberController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function index()
  {
    $members = DB::table('members')
      ->where('members.team_id', Cookie::get('current_team_id'))
      ->whereNull('withdrawal_date')
      ->orderBy('members.type')
      ->orderBy('members.backno')
      ->orderBy('members.id')
      ->get();
    return Response::json($members);
  }

  /**
   * 新規登録画面を表示する時に必要なデータを返す
   *
   * @return \Illuminate\Http\Response
   */
//  public function create()
//  {
//  }

  /**
   * 新規メンバー登録
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    Log::info('MemberController#store 1');
//    $profImgFilename = null;
//    if ($request->profImg) {
//      $this->validate($request, [
//        'profImg' => [
//          'file', // アップロードされたファイルであること
//          'image', // 画像ファイルであること
//          'mimes:jpeg,png', // MIMEタイプを指定
//          'dimensions:min_width=120,min_height=120,max_width=400,max_height=400', // 最小縦横120px 最大縦横400px
//        ]
//      ]);
//      Log::info('MemberController#store 2');
//      if ($request->file('profImg')->isValid()) {
//        $profImgFilename = $request->profImg->storeAs('public/prof');
//      }
//    }
//    Log::info('MemberController#store 3');

    $user = null;
    $team = Team::findOrFail(Cookie::get('current_team_id'));
    // ユーザー作成
    if ($request->invitationFlg == "1") {
      $existingUser = User::where('email', $request->email)->first();
      if ($existingUser) {
        // 追加登録招待メール送信
        $fromUser = User::findOrFail(Auth::id());
        Mail::to($request->email)->send(new UserInvitation($fromUser, $existingUser, $team->name, null));
        $user = $existingUser;
      } else {
        $password = str_random(10);
        $user = User::create([
          "name" => $request->name,
          "name_kana" => $request->nameKana,
          "email" => $request->email,
          "password" => Hash::make($password),
          "mail_notification_flg" => true,
//        "status" => 'invited',
          "created_id" => Auth::id(),
          "updated_id" => Auth::id()
        ]);
//      Log::info('ユーザー作成：' . $user->mail_notification_flg);

        // 新規登録招待メール送信
        $fromUser = User::findOrFail(Auth::id());
        Mail::to($request->email)->send(
          new UserInvitation($fromUser, $user, $team->name, $password));
      }
    }

    //TODO validate
    // メンバー作成
    $member = Member::create([
      "user_id" => $user? $user->id : null,
      "team_id" => Cookie::get('current_team_id'),
      "name" => $request->name,
      "name_kana" => $request->nameKana,
      "type" => $request->memberTypeSegment + 1,
      "admin_flg" => null,
      "birthday" => $request->birthday,
      "backno" => $request->backno,
      "prof_img_filename" => $request->selectedAvatarFilename,
      "created_id" => Auth::id(),
      "updated_id" => Auth::id()
    ]);
    return Response::json($member);
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($id)
  {
    $member = Member::findOrFail($id);
    if ($member->user_id) {
      $user = User::find($member->user_id);
      if ($user) {
        $member->user_id = $user->id;
        $member->email = $user->email;
      }
    }
    return Response::json($member);
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
//  public function edit($id)
//  {
//  }

  /**
   * メンバーの更新
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function update(Request $request, $id)
  {
    //TODO validate
    $member = Member::findOrFail($id);
    //TODO 管理者権限チェック
    if (!$member || $member->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      return response()->json(null, 404);
    }

    $userId = $member->user_id;
    Log::info('＞＞＞＞' . $userId);
    $member->name = $request->name;
    $member->name_kana = $request->nameKana;
    $member->type = $request->memberTypeSegment + 1;  //DB反映されない。updateに入らない。要調査。
    $member->birthday = $request->birthday;
    $member->backno = $request->backno;
    $member->prof_img_filename = $request->selectedAvatarFilename;
    $member->admin_flg = $request->adminFlg;
    $member->updated_id = Auth::id();
    $member = $member->save();
    //usersテーブルで保持するデータもある
    if ($userId) {
      Log::info("★" . $userId);
      $user = User::find($userId);
      if ($user) {
        $user->name = $request->name;
        $user->name_kana = $request->nameKana;
        $user->email = $request->email;
        Log::info("★★" . $user->email);
        $user->save();
      }
    }
    // 招待
    if ($request->invitationFlg == "1") {
      $password = str_random(10);
      $user = User::create([
        "name" => $request->name,
        "name_kana" => $request->nameKana,
        "email" => $request->email,
        "password" => Hash::make($password),
//        "status" => 'invited',
        "created_id" => Auth::id(),
        "updated_id" => Auth::id()
      ]);

      // 招待メール送信
      Log::info('Auth::user ' . Auth::user());
      $fromUser = User::findOrFail(Auth::id());
      Mail::to($request->email)->send(
        new UserInvitation($fromUser, $user, $password));
      // members更新
      $member = Member::find($id);
      $member->user_id = $user->id;
      $member = $member->save();
    }
    return Response::json($member);
  }

  /**
   * 削除する
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    $member = Member::findOrFail($id);
    //TODO 管理者権限チェック
    if (!$member || $member->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      return response()->json(null, 404);
    }
    $count = $member->delete();
    $result = ["deleted_count" => $count];
    return Response::json($result);
  }
}
