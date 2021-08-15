<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Team;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Response;

/**
 * ブログ(RSS)に関するコントローラ
 * @package App\Http\Controllers\Api
 */
class BlogController extends Controller
{
  /**
   * エントリリストを返す。
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function index()
  {
    $team = Team::find(Cookie::get('current_team_id'));
    $entries = [];
    $blogRssUrl = $team->blog_rss;
    if ($blogRssUrl) {
      $feed = FeedReader::read($blogRssUrl);
      foreach($feed->get_items() as $item) {
        $title = trim($item->get_title());
        $link = trim($item->get_permalink());
        $date = $item->get_date('Y.n.j');
        array_push($entries, [
          'title' => $title,
          'link' => $link,
          'date' => $date
        ]);
      }
    }
    return Response::json($entries);
  }
}
