<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\LoginController;
use App\Member;
use App\Post;
use App\Questionnaire;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;

/**
 * アンケートの全回答結果をCSVでダウンロードするためのController
 * @package App\Http\Controllers
 */
class QuestionnaireCsvController extends Controller
{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct()
  {
    $this->middleware('auth');
  }

  /**
   * チームからの退会処理実行。複数チームに所属している場合は最後のチームを退会した時にログイン不可となる。
   * @param Request $request
   * @param questionnaire_id
   * @return \Illuminate\Http\Response
   */
  public function __invoke(Request $request, $questionnaire_id)
  {
    $quetionnare = Questionnaire::findOrFail($questionnaire_id);
    if (!$quetionnare) {
      return response()->json(null, 404);
    }
    $post = Post::where('questionnaire_id', $quetionnare->id)->first();
    if (!$post || $post->team_id != Cookie::get('current_team_id')) {
      return response()->json(null, 404);
    }

    $items = json_decode($quetionnare->items);

    $answersBuilder = DB::table('users')
      ->join('members', function(JoinClause $join) use ($quetionnare, $post) {
        $join->on('users.id', '=', 'members.user_id')
        ->where('members.team_id', '=', $post->team_id);
      });

    $quetionnare_id = $quetionnare->id;
    for ($i=0; $i<count($items); $i++) {
      $answersBuilder->leftJoin('questionnaire_answers as qa' . $i, function(JoinClause $join) use($quetionnare_id, $i) {
        $join->on('qa' . $i . '.user_id', '=', 'users.id')
        ->where('qa' . $i . '.questionnaire_id', '=', $quetionnare_id)
        ->where('qa' . $i . '.question_no', '=', $i);
      });
    }
    $selectCols = ['members.name'];
    // 設問数分
    for ($i=0; $i<count($items); $i++) {
      array_push($selectCols, '\',\'');
      array_push($selectCols, 'ifnull(qa' . $i . '.answer, \'\')');
    }
    $selectConcat = 'concat(' . join(',', $selectCols) . ') as answer_row';
    $answers = $answersBuilder->select(DB::raw($selectConcat))
      ->orderBy('members.id')
      ->get();

    $itemTitles = [];
    for ($i=0; $i<count($items); $i++) {
      array_push($itemTitles, $items[$i]->text);
    }
    $csv = '氏名,' . join(',', $itemTitles) . "\n";
//    Log::info('answers-----------------------------');
    foreach ($answers as $answer) {
      $csv .= ($answer->answer_row . "\n");
//      Log::info($csv);
    }
    $filename = 'result.csv';
    $headers = [
      'Content-Type' => 'text/csv',
      'Content-Disposition' => 'attachment; filename="' . $filename . '"'
    ];
    return Response::make($csv, 200, $headers);
  }
}
