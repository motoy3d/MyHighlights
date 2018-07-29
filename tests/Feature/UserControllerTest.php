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
 * UserControllerのテスト
 * @package Tests\Feature
 */
class UserControllerTest extends TestCase {
  public function setUp()
  {
    parent::setUp();
    // Avoid "Session store not set on request." - Exception!
    Session::setDefaultDriver('array');
    $this->manager = app('session');
  }

  // updateName ----------------------------------------------------
  /**
   * updateNameのテスト。
   * @return void
   */
  public function testUpdateName1() {
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->put('http://localhost:8000/api/users/updateName', [
        'name' => '氏名　' . date('Y/m/d H:i:s')
      ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * updateEmailのテスト。
   * @return void
   */
  public function testUpdateEmail1() {
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->put('http://localhost:8000/api/users/updateEmail', [
        'email' => 'm' . date('YmdHis') . '@phpunittest.jp'
      ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * updatePasswordのテスト。
   * @return void
   */
  public function testUpdatePassword1() {
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->put('http://localhost:8000/api/users/updatePassword', [
        'current_password' => 'motoikataoka',
        'new_password' => 'test1234'
      ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }
}
