<?php

  /*
  |--------------------------------------------------------------------------
  | Web Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register web routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | contains the "web" middleware group. Now create something great!
  |
  */

  Route::middleware(['log'])->group(function () {
    Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
    Route::post('login', 'Auth\LoginController@login');
    Route::post('logout', 'Auth\LoginController@logout')->name('logout');
    Route::post('withdrawal', 'WithdrawalController')->name('withdrawal');

    // Password Reset Routes...
    Route::get('password/reset', 'Auth\ForgotPasswordController@showLinkRequestForm')->name('password.request');
    Route::post('password/email', 'Auth\ForgotPasswordController@sendResetLinkEmail')->name('password.email');
    Route::get('password/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->name('password.reset');
    Route::post('password/reset', 'Auth\ResetPasswordController@reset');

    Route::get('/', 'HomeController@index')->name('home');
    Route::get('/home', 'HomeController@index')->name('home');

    Route::get('/ical/{ical_id}', 'ICalendarController@make')->name('ical');

    Route::get('questionnaire_download/{questionnaire_id}', 'QuestionnaireCsvController')->name('questionnaire_download');

    Route::get('goto_line_auth', 'LineNotifyController@redirectToProvider');
    Route::post('line_auth', 'LineNotifyController@handleProviderCallback');
    Route::get('line_auth', 'LineNotifyController@authError');
  });