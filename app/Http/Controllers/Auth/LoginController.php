<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
   * ログイン条件にusers.withdrawal_date is nullを追加
   * @param Request $request
   * @return array
   */
  public function credentials(Request $request)
  {
    $authConditionsOrigin = $request->only($this->username(), 'password');
    $authConditionsCustom = array_merge(
      $authConditionsOrigin,
      ['withdrawal_date' => null]
    );
    return $authConditionsCustom;
  }

  /**
   * ログイン成功直後の処理
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  mixed  $user
   * @return mixed
   */
  protected function authenticated(Request $request, $user)
  {
    //
  }

}
