<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\NoticeLog;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * アプリ内のお知らせ一覧(🔔)の API(#125 設計書 §5.3)。
 *
 * どれも本人の分だけを扱う(他の人の通知は見えず、開いたにもできない)。
 */
class NoticeController extends Controller
{
    /**
     * GET /api/notices
     * → { unopened, items: [{ id, nid, type, team_id, title, body, url, opened, created_at }] }
     * 最近 30 日の通知を新しい順に最大 100 件
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'unopened' => NoticeLog::unopenedCount($user),
            'items' => NoticeLog::list($user),
        ]);
    }

    /**
     * GET /api/notices/unopened → { unopened }
     * 🔔とホーム画面のアイコンの数(まだ開いていない通知の数)
     */
    public function unopened(Request $request): JsonResponse
    {
        return response()->json(['unopened' => NoticeLog::unopenedCount($request->user())]);
    }

    /**
     * POST /api/notices/open  { nid } または { all: true } → { unopened }
     * その通知(またはすべて)を開いたにする
     */
    public function open(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($request->boolean('all')) {
            NoticeLog::openAll($user);
        } else {
            $request->validate(['nid' => ['required', 'string', 'max:32']]);
            NoticeLog::openByNid($user, (string) $request->input('nid'));
        }

        return response()->json(['unopened' => NoticeLog::unopenedCount($user)]);
    }
}
