<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitation;
use App\Member;
use App\Support\AccountRelinker;
use App\Team;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

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
        // すでにこのチームの有効メンバーなら登録させない(#79)。
        // 招待メールを送る前・members を作る前に弾くこと。
        $this->assertNotActiveMemberOfTeam($existingUser->id, $team->id);
        // 追加登録招待メール送信
        $fromUser = User::findOrFail(Auth::id());
        Mail::to($request->email)->send(new UserInvitation($fromUser, $existingUser, $team->name, null));
        $user = $existingUser;
      } else {
        $password = Str::random(10);
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
        Log::info('招待メール送信:' . $request->email);
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
      // 管理者の設定は管理者だけができる(#124)
      "admin_flg" => ($request->adminFlg && $this->isCurrentTeamAdmin())? 1 : 0,
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
    $member = Member::findOrFail($id);
    if (!$member || $member->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      return response()->json(null, 404);
    }

    // 名前・誕生日・背番号・アイコンなどは、管理者でなくても編集できる(チームの利便性のため)。
    // ただしアカウントの乗っ取りや権限の奪取につながる次の操作は管理者だけに限る(#124)。
    //   - 紐づくアカウントのメールアドレスの変更(変えた先のアドレスでパスワードを再設定すれば乗っ取れる)
    //   - 招待・別のアカウントへの紐づけ直し
    //   - 管理者の設定
    // 画面(Member.vue)も管理者にしか出していないが、サーバでも必ず確かめる。
    $isAdmin = $this->isCurrentTeamAdmin();
    $relink = $request->invitationFlg == "1";
    if ($relink && !$isAdmin) {
      return response()->json(['message' => 'この操作は管理者だけができます。'], 403);
    }

    $email = trim((string) $request->email);
    // 管理者がメールアドレスを扱うのは、アカウントが紐づいているとき(アドレスの変更)と招待・紐づけ直しのとき
    $handlesEmail = $isAdmin && ($relink || $member->user_id);
    if ($handlesEmail) {
      $this->assertEmailShape($email);
      // そのメールアドレスを既に使っている別の利用者。users.email は一意なので、確認せずに保存すると500になる。
      // members/users の更新や招待メール送信より前に確認する(途中まで保存されて 422 になるのを防ぐ)
      $otherUser = User::where('email', $email)->where('id', '!=', $member->user_id)->first();
      if ($otherUser) {
        if ($relink) {
          // 別の利用者に紐づける場合、すでにこのチームの有効メンバーなら弾く(#79)
          $this->assertNotActiveMemberOfTeam($otherUser->id, $member->team_id, $member->id);
        } else {
          throw ValidationException::withMessages([
            'email' => 'このメールアドレスは他の方が使っています。',
          ]);
        }
      }
    }

    $invitation = null;
    DB::transaction(function () use ($request, $member, $isAdmin, $relink, $handlesEmail, $email, &$invitation) {
      $member->name = $request->name;
      $member->name_kana = $request->nameKana;
      $member->type = $request->memberTypeSegment + 1;
      $member->birthday = $request->birthday;
      $member->backno = $request->backno;
      $member->prof_img_filename = $request->selectedAvatarFilename;
      if ($isAdmin) {
        $member->admin_flg = $request->adminFlg ? 1 : 0;
      }
      $member->updated_id = Auth::id();
      $member->save();

      // 紐づくアカウントのメールアドレスの変更。
      // 招待・紐づけ直しでは今のアカウントには触らない(入力したアドレスのアカウントに付け替える操作なので)
      if ($handlesEmail && !$relink) {
        $user = User::find($member->user_id);
        if ($user && $user->email !== $email) {
          $user->email = $email;
          $user->updated_id = Auth::id();
          $user->save();
        }
      }

      if ($relink) {
        $invitation = $this->relinkMember($member, $email, $request);
      }
    });

    // 招待メールは保存が確定してから送る(途中で失敗したのに招待だけ届くのを防ぐ)
    if ($invitation) {
      $fromUser = User::findOrFail(Auth::id());
      $team = Team::findOrFail($member->team_id);
      Mail::to($email)->send(new UserInvitation($fromUser, $invitation['user'], $team->name, $invitation['password']));
    }
    return Response::json($member->fresh());
  }

  /**
   * 招待・紐づけ直し。入力したアドレスのアカウント(無ければ作る)にメンバーを紐づける。
   * すでに別のアカウントに紐づいていた場合は、そのチームでの過去の書き込みも移し(AccountRelinker)、
   * 元のアカウントが他のどのチームにも在籍していなければ、削除と同じく退会扱いにする(#124)。
   *
   * @return array{user: User, password: ?string} 招待メールに使う
   */
  private function relinkMember(Member $member, string $email, Request $request): array
  {
    $password = null;
    $user = User::where('email', $email)->first();
    if (!$user) {
      $password = Str::random(10);
      $user = User::create([
        "name" => $request->name,
        "name_kana" => $request->nameKana,
        "email" => $email,
        "password" => Hash::make($password),
        "created_id" => Auth::id(),
        "updated_id" => Auth::id()
      ]);
    } elseif ($user->withdrawal_date) {
      // 退会済みのアカウントに紐づける場合は、在籍するメンバーになるので退会を取り消す
      $user->withdrawal_date = null;
      $user->updated_id = Auth::id();
      $user->save();
    }

    $oldUserId = $member->user_id;
    if ($oldUserId && $oldUserId != $user->id) {
      AccountRelinker::moveTeamHistory($member->team_id, (int) $oldUserId, (int) $user->id);
    }
    $member->user_id = $user->id;
    $member->save();

    if ($oldUserId && $oldUserId != $user->id) {
      $stillMember = Member::where('user_id', $oldUserId)->whereNull('withdrawal_date')->exists();
      if (!$stillMember) {
        $oldUser = User::find($oldUserId);
        if ($oldUser && !$oldUser->withdrawal_date) {
          $oldUser->withdrawal_date = Carbon::now();
          $oldUser->save();
        }
      }
    }
    return ['user' => $user, 'password' => $password];
  }

  /** ログインしている人が、今のチームの在籍中の管理者か */
  private function isCurrentTeamAdmin(): bool
  {
    // Member は SoftDeletes なので deleted_at IS NULL は Eloquent が自動で付ける
    return Member::where('user_id', Auth::id())
      ->where('team_id', Cookie::get('current_team_id'))
      ->whereNull('withdrawal_date')
      ->where('admin_flg', 1)
      ->exists();
  }

  /**
   * メールアドレスの形だけを確かめる(空・空白入り・@ が無いものを弾く)。
   * 携帯キャリアの古いアドレス(. が続くものなど)は RFC の検証では弾かれるので、厳密な検証はしない
   */
  private function assertEmailShape(string $email): void
  {
    if ($email === '' || mb_strlen($email) > 255 || !preg_match('/^[^@\s]+@[^@\s]+$/u', $email)) {
      throw ValidationException::withMessages([
        'email' => 'メールアドレスを正しく入れてください。',
      ]);
    }
  }

  /**
   * 削除する
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    // 管理者権限チェック
    $loginMember = Member::where('user_id', Auth::id())
      ->where('team_id', Cookie::get('current_team_id'))  //別チームの管理者権限で操作できないよう絞る
      ->whereNull('withdrawal_date')
      ->whereNull('deleted_at')
      ->where('admin_flg', 1)
      ->first();
    if (!$loginMember) { //このチームの管理者でない場合は404
      return response()->json(null, 404);
    }

    $deletedMember = Member::findOrFail($id);
    if (!$loginMember || !$deletedMember || $deletedMember->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      return response()->json(null, 404);
    }

    // memberの退会日時を更新
    $deletedMember->withdrawal_date = Carbon::now();
    $count = $deletedMember->save();

    // 他チーム所属の有効な(退会していない)memberが残っているか確認
    $otherTeamMember = Member::where('user_id', $deletedMember->user_id)
      ->whereNull('withdrawal_date')
      ->whereNull('deleted_at')
      ->first();
    // 有効なmemberが残っていなければ、userも退会とする
    if (!$otherTeamMember) {
      // 退会日時を更新
      $user = User::findOrFail($deletedMember->user_id);
      $user->withdrawal_date = Carbon::now();
      $user->save();
    }

    $result = ["deleted_count" => $count];
    return Response::json($result);
  }

  /**
   * 指定ユーザーがすでにチームの有効メンバー(退会・削除されていない)なら 422 を投げる。
   *
   * 同じ team_id + user_id の members が2行あると、members を user_id + team_id で
   * JOIN している箇所(記事のコメント一覧など)で同じ行が2回表示されてしまう(#79)。
   * 退会済み(withdrawal_date あり)のメンバーは再登録できるよう対象外にする。
   *
   * @param int $userId
   * @param int $teamId
   * @param int|null $exceptMemberId 更新中のメンバー自身は除外する
   * @throws ValidationException
   */
  private function assertNotActiveMemberOfTeam($userId, $teamId, $exceptMemberId = null)
  {
    // Member は SoftDeletes なので deleted_at IS NULL は Eloquent が自動で付ける
    $query = Member::where('user_id', $userId)
      ->where('team_id', $teamId)
      ->whereNull('withdrawal_date');
    if ($exceptMemberId) {
      $query->where('id', '!=', $exceptMemberId);
    }
    if ($query->exists()) {
      throw ValidationException::withMessages([
        'email' => 'このメールアドレスの方はすでにこのチームのメンバーです。'
          . '重複しているメンバーを削除してから、もう一度お試しください。',
      ]);
    }
  }
}
