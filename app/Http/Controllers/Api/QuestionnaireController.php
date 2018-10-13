<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Post;
use App\PostResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class QuestionnaireController extends Controller
{
  /**
   * アンケートの回答をDB保存する。
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    $post = Post::findOrFail($request->post_id);
    if (!$post || $post->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $questionnaireResult = null;
    $questionnaire = DB::table('quesltionnaires')
      ->where('user_id', '=', Auth::id())
      ->where('post_id', '=', $request->post_id)
      ->first();
    Log::info("----store ");
    if ($postResponse) {
      Log::info("like_flg: " . $request->like_flg);
      Log::info("star_flg: " . $request->star_flg);
      $postResponseModel = PostResponse::findOrFail($postResponse->id);
      if (isset($request->like_flg)) { //いいね
        $postResponseModel->like_flg = $request->like_flg;
        $postResponseModel->save();
        //TODO いいね合計数更新
        Log::info("like_flg saved.");
      } else if (isset($request->star_flg)) { //スター
        $postResponseModel->star_flg = $request->star_flg;
        $postResponseModel->save();
      } else{
        // ありえない
      }
      $postResponseResult = $postResponseModel;
    } else {
      // 既読
      if (isset($request->read_flg)) { //既読
        $postResponseResult = PostResponse::create([
          "user_id" => Auth::id(),
          "post_id" => $request->post_id,
          "read_flg" => true,
          "created_id" => Auth::id(),
          "updated_id" => Auth::id()
        ]);
      }
    }
    return Response::json($postResponseResult);
  }
}
