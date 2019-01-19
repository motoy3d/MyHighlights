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
    Log::info("HomeController#index ------------------------");
    Log::info(Auth::user()->name . " team_id=" . Auth::user()->teams()->first()->team_id);
    $currentTeamId = Auth::user()->teams()->orderBy('created_at')->first()->team_id;
    Cookie::queue(Cookie::make('current_team_id', $currentTeamId));
    return view('home');
  }
}
