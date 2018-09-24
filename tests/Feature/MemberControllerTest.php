<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PostController;
use App\User;
use Illuminate\Support\Debug\Dumper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * MemberControllerのテスト
 * @package Tests\Feature
 */
class MemberControllerTest extends TestCase {
  public function setUp()
  {
    parent::setUp();
    // Avoid "Session store not set on request." - Exception!
    Session::setDefaultDriver('array');
    $this->manager = app('session');
  }
  // index ----------------------------------------------------
  /**
   * indexのテスト。
   * @return void
   */
  public function testIndex1(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->get('http://' . env('TEST_IP') . ':8000/api/members');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  // store ----------------------------------------------------
  /**
   * storeのテスト。招待なし。
   * @return void
   */
  public function testStore1(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->post('http://' . env('TEST_IP') . ':8000/api/members',
        [
          'name' => '小林侑李',
          'type' => 1,  //1=選手
          'birthday' => '2008-04-19',
          'backno' => '1',
          'has_profile_img_flg' => 0
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * storeのテスト。招待あり。
   * @return void
   */
  public function testStore2(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->post('http://' . env('TEST_IP') . ':8000/api/members',
        [
          'name' => '遠藤航',
          'type' => 1,  //1=選手
          'birthday' => '2008-05-20',
          'backno' => '6',
          'has_profile_img_flg' => 0,
          'email' => 'motoy3d+endo@gmail.com',
          'invite_flg' => 1
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  //TODO update ----------------------------------------------------
  /**
   * updateのテスト。
   * @return void
   */
  public function testUpdate1(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->put('http://' . env('TEST_IP') . ':8000/api/members/2',
        [
          'title' => '予定タイトル更新 ' . date('Y-m-d H:i:s'),
          'member_date' => '2018-08-05',
          'allday_flg' => false,
          'time_from' => '13:10',
          'time_to' => '16:40',
          'category_id' => '1',
          'contents' => "予定本文更新............です\nよろしくお願いします。\n " . date('Y-m-d H:i:s'),
          'notification_flg' => 1
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }
  /**
   * updateのテスト。別チームの予定更新は404
   * @return void
   */
  public function testUpdate2_404(){
    $response = $this->actingAs(User::findOrFail(2), 'api')
      ->put('http://' . env('TEST_IP') . ':8000/api/members/2',
        [
          'title' => '予定タイトル更新 ' . date('Y-m-d H:i:s'),
          'member_date' => '2018-08-05',
          'allday_flg' => false,
          'time_from' => '13:10',
          'time_to' => '16:40',
          'category_id' => '1',
          'contents' => "予定本文更新............です\nよろしくお願いします。\n " . date('Y-m-d H:i:s'),
          'notification_flg' => 1
        ]);
    $response->assertStatus(404);
    echo $this->json_enc($response->json());
  }

  // destroy ----------------------------------------------------
  /**
   * destroyのテスト。
   * @return void
   */
  public function testDestroy1(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->delete('http://' . env('TEST_IP') . ':8000/api/members/3');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }
  /**
   * destroyのテスト。別チームの予定削除は404
   * @return void
   */
  public function testDestroy2_404(){
    $response = $this->actingAs(User::findOrFail(2), 'api')
      ->delete('http://' . env('TEST_IP') . ':8000/api/members/2');
    $response->assertStatus(404);
    echo $this->json_enc($response->json());
  }

}
