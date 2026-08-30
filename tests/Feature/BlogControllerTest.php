<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PostController;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * BlogControllerのテスト
 * @package Tests\Feature
 */
class BlogControllerTest extends TestCase {
  protected function setUp(): void
  {
    parent::setUp();
    // Avoid "Session store not set on request." - Exception!
    Session::setDefaultDriver('array');
  }

  // index ----------------------------------------------------
  /**
   * indexのテスト。
   * @return void
   */
  public function testIndex1() {
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->get('http://localhost:8000/api/blog');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
    $response->assertJsonCount(10);
  }
}
