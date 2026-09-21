<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Webプッシュ通知の1件(#110)。
 *
 * Service Worker(public/sw.js)に届くJSONは次の形になる(設計書 §7.3.1)。
 *   { "title": "チーム名", "body": "…", "tag": "post-123",
 *     "data": { "url": "/home?launcher=true&team=41&post=123" }, ... }
 *
 * ShouldQueue は付けない。キューに積むのは PushNotificationJob の側で、
 * テスト送信(/api/push/test)はその場で送るため。
 */
class PushNotice extends Notification
{
    /** ロック画面に出るので、本文は冒頭だけにする(全角でおよそ60文字) */
    public const BODY_WIDTH = 120;

    public const ICON = '/appicon.png';

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $tag,
        public readonly string $url,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable, mixed $notification = null): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body(self::truncate($this->body))
            ->icon(self::ICON)
            ->badge(self::ICON)
            ->tag($this->tag)
            // 同じtagで置き換えたときも音・バイブで知らせる
            ->renotify(true)
            ->data(['url' => $this->url])
            // 端末がオフラインでも1日は再送を試みる。iOSは urgency が低いと届くのが遅れる
            ->options(['TTL' => 86400, 'urgency' => 'high']);
    }

    /**
     * 改行・連続する空白を1つの空白にまとめ、表示幅で切り詰める。
     * mb_strimwidth は全角を幅2として数えるため、幅120でおよそ全角60文字になる。
     */
    public static function truncate(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strimwidth($text, 0, self::BODY_WIDTH, '…', 'UTF-8');
    }
}
