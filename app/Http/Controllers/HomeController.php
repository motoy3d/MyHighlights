<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class HomeController extends Controller
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
   * Show the application dashboard.
   *
   * @return \Illuminate\Http\Response
   */
  public function index(Request $request)
  {
    Log::info("HomeController#index ------------------------ " . Auth::user()->name);
    $teams = Auth::user()->teams()->orderBy('created_at')->get();
    $needMakingCookie = true;
    // Cookieにcurrent_team_idがある場合、所属チームに該当するかチェック
    if (Cookie::get('current_team_id')) {
      foreach ($teams as $team) {
        if ($team->id == Cookie::get('current_team_id')) {
          $needMakingCookie = false;
          break;  //ちゃんと所属チームと同じであればOK
        }
      }
    }
    // Cookieにcurrent_team_idがない場合、または↑でCookieセットが必要と判断した場合、1番目のteam_idをセット
    if ($needMakingCookie) {
      $team = $teams[0];
      $currentTeamId = $team->id;
      $currentTeamName = $team->name;
      $minutes = env('SESSION_LIFETIME', 129600);
      $path = "/";
      $domain = "";
      $secure = env('APP_ENV') == 'production';
      $httpOnly = false; //jsで扱うために必要
      Cookie::queue(Cookie::make('current_team_id', $currentTeamId,
        $minutes, $path, $domain, $secure, $httpOnly));
      Cookie::queue(Cookie::make('current_team_name', $currentTeamName,
        $minutes, $path, $domain, $secure, $httpOnly));
    }
    return view('home');
  }
}
