<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitation;
use App\Member;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
      ->where('members.team_id', Auth::user()->team_id)
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

    //TODO validate
    $member = Member::create([
      "team_id" => Auth::user()->team_id,
      "name" => $request->name,
      "type" => $request->type + 1,
      "birthday" => $request->birthday,
      "backno" => $request->backno,
      "prof_img_filename" => 'boy.png',
      "created_id" => Auth::id(),
      "updated_id" => Auth::id()
    ]);
    if ($request->invitationFlg == "1") {
      //TODO user登録
      $password = str_random(10);
      $user = User::create([
        "name" => $request->name,
        "email" => $request->email,
        "team_id" => Auth::user()->team_id,
        "member_id" => $member->id,
        "password" => $password,
//        "status" => 'invited',
        "created_id" => Auth::id(),
        "updated_id" => Auth::id()
      ]);
      //TODO 招待メール
      Log::info('Auth::user ' . Auth::user());
      $fromUser = User::findOrFail(Auth::id());
      Mail::to($request->email)->send(
        new UserInvitation($fromUser, $user, $password));
    }
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
    if (!$member || $member->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
      return response()->json(null, 404);
    }

    $member->name = $request->name;
    $member->type = $request->type;
    $member->birthday = $request->birthday;
    $member->backno = $request->backno;
    $member->has_profile_img_flg = $request->has_profile_img_flg;
    $member->updated_id = Auth::id();
    $member = $member->save();

    //usersテーブルで保持するデータもある
    $user = DB::table('members')
      ->where('member_id', $id)
      ->first();
    if ($user) {
      $user->name = $request->name;
      $user->email = $request->email;
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
    if (!$member || $member->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
      return response()->json(null, 404);
    }
    $count = $member->delete();
    $result = ["deleted_count" => $count];
    return Response::json($result);
  }
}
