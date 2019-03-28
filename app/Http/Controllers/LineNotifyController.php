<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Team;
use App\User;
use Awjudd\FeedReader\Facades\FeedReader;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

/**
 * LINE Notifyに関するコントローラ
 * https://notify-bot.line.me/doc/ja/
 * @package App\Http\Controllers\Api
 */
class LineNotifyController extends Controller
{
  const LINE_NOTIFY_AUTH_URL = 'https://notify-bot.line.me/oauth/authorize';
  const LINE_NOTIFY_TOKEN_URL = 'https://notify-bot.line.me/oauth/token';

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
   * LINE Notifyの認証画面へリダイレクトする。ユーザーはそこでLINEログインし、Tsubasa⬆UPの連携を許可する設定をする。
   * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
   */
  public function redirectToProvider()
  {
    $uri = self::LINE_NOTIFY_AUTH_URL . '?' .
      'response_type=code&' .
      'client_id=' . config('app.line_notify_client_id') . '&' .
      'redirect_uri=' . config('app.line_notify_callback_uri') . '&' .
      'scope=notify&' .
      'state=' . csrf_token() . '&' .
      'response_mode=form_post';
    Log::info('authorize url=' . $uri);
    return redirect($uri);
  }

  /**
   * LINEからのコールバックで認証コード(POSTリクエスト)を受け取り、
   * それを使用してアクセストークン取得リクエストをLINEに送る。
   * そこで返ってきたアクセストークンをusersに保存し、当システムからの通知時に使用する。
   * @return \Illuminate\Http\JsonResponse
   */
  public function handleProviderCallback(Request $request)
  {
    Log::info('LINE Notify 認証コード=' . $request->code);
    $client = new Client();
    $response = $client->post(self::LINE_NOTIFY_TOKEN_URL, [
      'headers'     => [
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'form_params' => [
        'grant_type'    => 'authorization_code',
        'code'          => $request->code,
        'redirect_uri'  => config('app.line_notify_callback_uri'),
        'client_id'     => config('app.line_notify_client_id'),
        'client_secret' => config('app.line_notify_client_secret')
      ]
    ]);
    Log::info('tokenレスポンス------------');
    Log::info($response->getBody());
    $message = '';
    if ($response->getStatusCode() == 200) {
      // LINE通知に必要なユーザーごとのアクセストークン。DBに保存する。
      $access_token = json_decode($response->getBody())->access_token;
//      Log::info('ログインユーザー:' . Auth::id());
      $user = User::findOrFail(Auth::id());
      $user->line_notification_flg = true;
      $user->line_access_token = $access_token;
      $user->save();
      $message = 'LINEで通知する設定が完了しました。';
    } else {
      $message = 'LINEの通知設定に失敗しました。';
    }
    Log::info('結果メッセージ：' . $message);
    return redirect('/home');
  }

  /**
   * https://notify-bot.line.me/oauth/authorize へのリクエスト時にキャンセルまたはエラーが発生した場合のリダイレクト先。
   * @param Request $request
   */
  public function authError(Request $request)
  {
    Log::error("LINE Notify authorize error or canceled. errorcode=" . $request->error);
    return redirect('/home');
  }

}
