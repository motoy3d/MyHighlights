<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\UserTeam;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
  /*
  |--------------------------------------------------------------------------
  | Login Controller
  |--------------------------------------------------------------------------
  |
  | This controller handles authenticating users for the application and
  | redirecting them to your home screen. The controller uses a trait
  | to conveniently provide its functionality to your applications.
  |
  */

  use AuthenticatesUsers;

  /**
   * Where to redirect users after login.
   *
   * @var string
   */
  protected $redirectTo = '/home';

  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct()
  {
      $this->middleware('guest')->except('logout');
  }

  /**
   * 認証後にいろいろやるところ
   *
   * @from vendor/laravel/framework/src/Illuminate/Foundation/Auth/AuthenticatesUsers.php
   * @param  \Illuminate\Http\Request  $request
   * @param  mixed  $user
   * @return mixed
   */
//  protected function authenticated(Request $request, $user)
//  {
//    Log::info('authenticated:' . $user);
//    $teams = DB::table('user_teams')
//      ->where('user_id', $user->id)
//      ->orderBy('created_at')
//      ->get();
//    if (!$teams) {
//      return false;
//    }
//    $user->teams = $teams;
//    // セッションのカレントチームIDをセット
//    Session::put('current_team_id', $teams[0]->team_id);
//    Log::info('======session(LoginController) ' . Session::get('current_team_id'));
//  }
}
