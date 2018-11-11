<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PostController;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Debug\Dumper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * PostControllerのテスト
 * @package Tests\Feature
 */
class PostControllerTest extends TestCase {
//  use RefreshDatabase;  //データ全部消える
  public function setUp()
  {
    parent::setUp();
    // Avoid "Session store not set on request." - Exception!
    Session::setDefaultDriver('array');
    $this->manager = app('session');
  }

  // store ----------------------------------------------------
  /**
   * storeのテスト。
   * @return void
   */
  public function testStore1(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->post('http://localhost:8000/api/posts',
        [
          'title' => 'タイトル ' . date('Y-m-d H:i:s'),
          'contents' => "本文です\nよろしくお願いします。\n " . date('Y-m-d H:i:s'),
          'category_id' => '1',
          'notification_flg' => 1
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * storeのテスト。別チームのユーザー
   * @return void
   */
  public function testStore2_other_team(){
    $response = $this->actingAs(User::findOrFail(2), 'api')
      ->post('http://localhost:8000/api/posts',
        [
          'title' => 'チーム2. タイトル ' . date('Y-m-d H:i:s'),
          'contents' => "チーム2. 本文です\nよろしくお願いします。\n " . date('Y-m-d H:i:s'),
          'category_id' => '2',
          'notification_flg' => null
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * storeのテスト。添付ファイルあり。
   * @return void
   */
  public function testStore3_attachment(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->post('http://localhost:8000/api/posts',
        [
          'title' => '添付ありタイトル ' . date('Y-m-d H:i:s'),
          'contents' => "本文です\nよろしくお願いします。\n " . date('Y-m-d H:i:s'),
          'category_id' => '1',
          'notification_flg' => 1,
          'file1' => UploadedFile::fake()->image('test1.jpg')
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * storeのテスト。添付ファイル複数あり。
   * @return void
   */
  public function testStore4_attachment(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->post('http://localhost:8000/api/posts',
        [
          'title' => '添付複数ありタイトル ' . date('Y-m-d H:i:s'),
          'contents' => "本文です\nよろしくお願いします。\n " . date('Y-m-d H:i:s'),
          'category_id' => '1',
          'notification_flg' => 1,
          'file1' => UploadedFile::fake()->image('test1.pdf'),
          'file2' => UploadedFile::fake()->image('test1.docx')
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  // index ----------------------------------------------------
  /**
   * indexのテスト。
   * @return void
   */
  public function testIndexUser1(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->get('http://localhost:8000/api/posts');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * indexのテスト。2ページ目。
   * @return void
   */
  public function testIndexUser1_page2(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->get('http://localhost:8000/api/posts?page=2');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * indexのテスト。別のチームのユーザーで結果が変わる。
   * @return void
   */
  public function testIndexUser2(){
    $response = $this->actingAs(User::findOrFail(2), 'api')
      ->get('http://localhost:8000/api/posts');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }
  /**
   * indexのテスト。キーワード検索
   * @return void
   */
  public function testIndexUser1_search(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->get('http://localhost:8000/api/posts?keyword=' . urlencode('キーワード'));
    echo $this->json_enc($response->json());
    $response->assertStatus(200);
  }

  // show ----------------------------------------------------
  /**
   * showのテスト。
   * @return void
   */
  public function testShow1(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->get('http://localhost:8000/api/posts/53');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }

  /**
   * showのテスト。他のチームのデータは見れない。404になる。
   * @return void
   */
  public function testShow2_他のチームのデータは見れない(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->get('http://localhost:8000/api/posts/4');
    $response->assertStatus(404);
    echo $this->json_enc($response->json());
  }

  // update ----------------------------------------------------
  /**
   * updateのテスト。
   * @return void
   */
  public function testUpdate1(){
    $response = $this->actingAs(User::findOrFail(1), 'api')
      ->put('http://localhost:8000/api/posts/3',
        [
          'title' => 'タイトル更新 ' . date('Y-m-d H:i:s'),
          'contents' => "本文更新............です\nよろしくお願いします。\n " . date('Y-m-d H:i:s'),
          'category_id' => '1',
          'notification_flg' => 1
        ]);
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }
  /**
   * updateのテスト。別チームの投稿は404
   * @return void
   */
  public function testUpdate2_404(){
    $response = $this->actingAs(User::findOrFail(2), 'api')
      ->put('http://localhost:8000/api/posts/3',
        [
          'title' => 'タイトル更新 ' . date('Y-m-d H:i:s'),
          'contents' => "本文更新............です\nよろしくお願いします。\n " . date('Y-m-d H:i:s'),
          'category_id' => '1',
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
      ->delete('http://localhost:8000/api/posts/5');
    $response->assertStatus(200);
    echo $this->json_enc($response->json());
  }
  /**
   * destroyのテスト。別チームの投稿は404
   * @return void
   */
  public function testDestroy2_404(){
    $response = $this->actingAs(User::findOrFail(2), 'api')
      ->delete('http://localhost:8000/api/posts/3');
    $response->assertStatus(404);
    echo $this->json_enc($response->json());
  }

}
