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
 * PostResponseControllerのテスト
 * @package Tests\Feature
 */
class PostResponseControllerTest extends TestCase {
  public function setUp()
  {
    parent::setUp();
    // Avoid "Session store not set on request." - Exception!
    Session::setDefaultDriver('array');
    $this->manager = app('session');
  }
  // store ----------------------------------------------------
  /**
   * read_flg1オンのテスト。
   * @return void
   */
  public function testStoreReadFlg1(){
    $user = User::findOrFail(1);
    $response = $this->actingAs($user, 'api')
      ->post('http://localhost:8000/api/post_responses',
        [
          'post_id' => 6,
          'read_flg' => 1
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * like_flg1オンのテスト。
   * @return void
   */
  public function testStoreLikeFlgOn(){
    $user = User::findOrFail(1);
    $response = $this->actingAs($user, 'api')
      ->post('http://localhost:8000/api/post_responses',
        [
          'post_id' => 6,
          'like_flg' => 1
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * like_flg1オフのテスト。
   * @return void
   */
  public function testStoreLikeFlgOff(){
    $user = User::findOrFail(1);
    $response = $this->actingAs($user, 'api')
      ->post('http://localhost:8000/api/post_responses',
        [
          'post_id' => 6,
          'like_flg' => 0
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * star_flgオンのテスト。
   * @return void
   */
  public function testStoreStarFlgOn(){
    $user = User::findOrFail(1);
    $response = $this->actingAs($user, 'api')
      ->post('http://localhost:8000/api/post_responses',
        [
          'post_id' => 6,
          'star_flg' => 1
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * star_flgオフのテスト。
   * @return void
   */
  public function testStoreStarFlgOff(){
    $user = User::findOrFail(1);
    $response = $this->actingAs($user, 'api')
      ->post('http://localhost:8000/api/post_responses',
        [
          'post_id' => 6,
          'star_flg' => 0
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }


  /**
   * storeのテスト。別チームのユーザー
   * @return void
   */
  public function testStore2_other_team(){
    $user = User::findOrFail(2);
    $response = $this->actingAs($user, 'api')
      ->post('http://localhost:8000/api/post_responses',
        [
          'post_id' => 6
        ]);
    $response->assertStatus(404);
    echo $this->json_enc($response->json());
  }

}
