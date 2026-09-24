<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\PushNotice;
use App\Support\PushRollout;
use App\Support\PushSentLog;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Webプッシュ通知の購読と設定のAPI(#110 設計書 §7.3)。
 */
class PushController extends Controller
{
    /**
     * GET /api/push/config
     * { enabled, vapid_public_key, preferences }
     */
    public function showConfig(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'enabled' => PushRollout::isEnabledFor($user),
            'vapid_public_key' => config('webpush.vapid.public_key') ?: null,
            'preferences' => $user->pushPreferences(),
        ]);
    }

    /**
     * PUT /api/push/preferences
     * { preferences: { new_post: true, ... } } → { preferences }
     * 送られてきたキーだけを更新する(送られなかったキーは今の値のまま)。
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $keys = array_keys(User::PUSH_PREF_DEFAULTS);
        $validated = $request->validate([
            // array:キー一覧 で、未知のキーが含まれていたら422にする
            'preferences' => ['required', 'array:' . implode(',', $keys)],
            'preferences.*' => ['boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $prefs = $user->pushPreferences();
        foreach ($validated['preferences'] as $key => $value) {
            $prefs[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        $user->push_prefs = $prefs;
        $user->save();

        return response()->json(['preferences' => $user->pushPreferences()]);
    }

    /**
     * POST /api/push/subscriptions
     * PushSubscription.toJSON() の形 { endpoint, keys: { p256dh, auth } } と content_encoding → 204
     */
    public function subscribe(Request $request): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! PushRollout::isEnabledFor($user)) {
            return response()->json(['message' => 'プッシュ通知はまだご利用いただけません。'], 403);
        }

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'starts_with:https://',
                'max:' . PushSubscription::ENDPOINT_MAX_LENGTH],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $user->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? 'aes128gcm'
        );

        return response()->noContent();
    }

    /**
     * DELETE /api/push/subscriptions
     * { endpoint } → 204
     * 公開の対象外になった後でも解除はできるようにしておく。
     */
    public function unsubscribe(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:' . PushSubscription::ENDPOINT_MAX_LENGTH],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->deletePushSubscription($validated['endpoint']);

        return response()->noContent();
    }

    /**
     * POST /api/push/recent  { endpoint: この端末の購読の endpoint }
     * 最近この利用者に送った通知 → { now: サーバの今(ミリ秒), notices: [{ nid, tag, url, at }] }
     * アプリが前面に戻ったとき、通知センターから消えた通知（＝タップされた通知）を探すのに使う（PushSentLog）。
     * now は、端末とサーバの時計のずれを直すために返す。
     *
     * endpoint がこの利用者の購読として登録されていない端末には、何も返さない。
     * その端末には通知が届かない(期限切れで消えた購読など)ので、送った通知がすべて「消えた」ように見えてしまうため。
     * endpoint は URL に載せない(アクセスログに残さない)ために POST で受け取る。
     */
    public function recent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $endpoint = (string) $request->input('endpoint', '');
        $subscribed = $endpoint !== '' && $user->pushSubscriptions()->where('endpoint', $endpoint)->exists();

        return response()->json([
            'now' => (int) floor(microtime(true) * 1000),
            'notices' => $subscribed ? PushSentLog::recent($user) : [],
        ]);
    }

    /**
     * POST /api/push/test
     * この利用者の全端末にテスト通知を、キューを通さずその場で送る → { sent: 端末数 }
     */
    public function test(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! PushRollout::isEnabledFor($user)) {
            return response()->json(['message' => 'プッシュ通知はまだご利用いただけません。'], 403);
        }

        $count = $user->pushSubscriptions()->count();
        if ($count > 0) {
            try {
                $notice = new PushNotice(
                    'Tsubasa⬆︎UP',
                    'テスト通知です。この端末で通知を受け取れます。',
                    'test',
                    '/home?launcher=true'
                );
                Notification::sendNow($user, $notice);
                PushSentLog::record($user, $notice);
            } catch (\Throwable $e) {
                Log::error('テスト通知の送信エラー user_id=' . $user->id . ': ' . $e->getMessage());
                return response()->json(['message' => 'テスト通知を送れませんでした。'], 500);
            }
        }

        return response()->json(['sent' => $count]);
    }
}
