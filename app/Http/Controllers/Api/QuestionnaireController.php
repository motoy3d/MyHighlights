<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Post;
use App\PostResponse;
use App\Questionnaire;
use App\QuestionnaireAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class QuestionnaireController extends Controller
{
  /**
   * アンケートの回答をDB保存する。
   * パラメータはpost_id,questionnaire_id,question_no,answer
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    if ($request->question_no === 3) {return;}  //キャンセルの場合(UIで制御するので来ないはず)
    $post = Post::findOrFail($request->post_id);
    //チームIDが別の場合やアンケートIDがマッチしない場合は404
    if (!$post || $post->team_id != Auth::user()->team_id
      || $post->questionnaire_id != $request->questionnaire_id) {
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $questionnaire = Questionnaire::findOrFail($request->questionnaire_id);
    if (!$questionnaire) { // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    Log::info("----store ");
    $answer = QuestionnaireAnswer::updateOrCreate(
      [
        'questionnaire_id' => $request->questionnaire_id,
        'user_id' => Auth::id(),
        'question_no' => $request->question_no
      ],
      [
        'answer' => $request->answer,
        'created_id' => Auth::id(), //更新時に更新するのは本来NGだがアンケート回答は本人がする(create)はずなので許容
        'updated_id' => Auth::id()
      ]);
//    $answer->answer = $request->answer;
//    $answer->created_id = Auth::id();
//    $answer->updated_id = Auth::id();
//    $answer->save();
    return Response::json($answer);
  }
}
