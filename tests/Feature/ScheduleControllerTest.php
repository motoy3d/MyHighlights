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
 * ScheduleControllerのテスト
 * @package Tests\Feature
 */
class ScheduleControllerTest extends TestCase {
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
      ->get('http://localhost:8000/api/schedules?month=201809');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  // store ----------------------------------------------------
  /**
   * storeのテスト。終日。
   * @return void
   */
  public function testStore1_allday(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->post('http://localhost:8000/api/schedules',
        [
          'schedule_date' => '2018-09-15',
          'title' => '予定タイトル ' . date('Y-m-d H:i:s'),
          'allday_flg' => true,
          'category_id' => '1',
          'contents' => "予定本文です\nよろしくお願いします。\n " . date('Y-m-d H:i:s'),
          'notification_flg' => 1
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * storeのテスト。時間指定。
   * @return void
   */
  public function testStore2_time(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->post('http://localhost:8000/api/schedules',
        [
          'schedule_date' => '2018-09-16',
          'title' => '試合 ' . date('Y-m-d H:i:s'),
          'allday_flg' => false,
          'time_from' => '10:00',
          'time_to' => '17:00',
          'category_id' => '2',
          'contents' => "予定本文です3\nよろしくお願いします。\n " . date('Y-m-d H:i:s'),
          'notification_flg' => false
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  // create ----------------------------------------------------
  /**
   * createのテスト。
   * @return void
   */
  public function testCreate1(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->get('http://localhost:8000/api/schedules/create');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  // update ----------------------------------------------------
  /**
   * updateのテスト。
   * @return void
   */
  public function testUpdate1(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->put('http://localhost:8000/api/schedules/2',
        [
          'title' => '予定タイトル更新 ' . date('Y-m-d H:i:s'),
          'schedule_date' => '2018-08-05',
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
      ->put('http://localhost:8000/api/schedules/2',
        [
          'title' => '予定タイトル更新 ' . date('Y-m-d H:i:s'),
          'schedule_date' => '2018-08-05',
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
      ->delete('http://localhost:8000/api/schedules/3');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }
  /**
   * destroyのテスト。別チームの予定削除は404
   * @return void
   */
  public function testDestroy2_404(){
    $response = $this->actingAs(User::findOrFail(2), 'api')
      ->delete('http://localhost:8000/api/schedules/2');
    $response->assertStatus(404);
    echo $this->json_enc($response->json());
  }

}
