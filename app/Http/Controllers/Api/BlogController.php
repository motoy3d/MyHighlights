<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Awjudd\FeedReader\Facades\FeedReader;
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
    $blogRssUrl = 'http://rssblog.ameba.jp/tsubasa36th/rss20.xml';
    $feed = FeedReader::read($blogRssUrl);
    $entries = [];
    foreach($feed->get_items() as $item) {
      $title = trim($item->get_title());
      $link = trim($item->get_permalink());
      $date = $item->get_date('Y/n/j');
      array_push($entries, [
        'title' => $title,
        'link' => $link,
        'date' => $date
      ]);
    }
    return Response::json($entries);
  }
}
