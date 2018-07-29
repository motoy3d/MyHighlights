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
 * TeamControllerのテスト
 * @package Tests\Feature
 */
class TeamControllerTest extends TestCase {
  public function setUp()
  {
    parent::setUp();
    // Avoid "Session store not set on request." - Exception!
    Session::setDefaultDriver('array');
    $this->manager = app('session');
  }

  // show ----------------------------------------------------
  /**
   * showのテスト。
   * @return void
   */
  public function testShow1() {
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->get('http://localhost:8000/api/teams');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * showのテスト。別チーム。
   * @return void
   */
  public function testShow2_other_team() {
    $response = $this->actingAs(User::findOrFail(2), 'api')
      ->get('http://localhost:8000/api/teams');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }
}
