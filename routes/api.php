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

  Route::middleware('auth:api')->group(function () {
    Route::get('posts/search_init', 'Api\PostController@searchInit');
    Route::resource('posts', 'Api\PostController');
    Route::post('post_responses/{post_id}', 'Api\PostResponseController@store');
    Route::post('post_comments/{post_id}', 'Api\PostCommentController@store');
    Route::delete('post_comments/{post_id}/{comment_id}', 'Api\PostCommentController@destroy');
    Route::resource('schedules', 'Api\ScheduleController');
    Route::get('schedule_comments/{schedule_id}', 'Api\ScheduleCommentController@show');
    Route::post('schedule_comments/{schedule_id}', 'Api\ScheduleCommentController@store');
    Route::delete('schedule_comments/{schedule_id}/{comment_id}', 'Api\ScheduleCommentController@destroy');
    Route::get('teams', 'Api\TeamController@show');
    Route::get('me', 'Api\UserController@getMe');
    Route::put('users/updateName', 'Api\UserController@updateName');
    Route::put('users/updateEmail', 'Api\UserController@updateEmail');
    Route::post('users/updatePassword', 'Api\UserController@updatePassword');
    Route::resource('members', 'Api\MemberController');
    Route::post('questionnaires/answer', 'Api\QuestionnaireController@store');
    Route::get('blog', 'Api\BlogController@index');

  });

