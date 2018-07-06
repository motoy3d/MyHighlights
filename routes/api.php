<?php

  use Illuminate\Http\Request;

  /*
  |--------------------------------------------------------------------------
  | API Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register API routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | is assigned the "api" middleware group. Enjoy building your API!
  |
  */

  //TODO テスト時は認証を外す
  Route::middleware('auth:api')->group(function () {
    Route::resource('posts', 'Api\PostController');
  });

//  Route::resource('posts', 'Api\PostController');
