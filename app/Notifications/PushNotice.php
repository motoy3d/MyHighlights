<?php

namespace App\Notifications;

use App\Support\PushNoticeLog;
use App\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\DeclarativeWebPushMessage;
use NotificationChannels\WebPush\WebPushMessageInterface;

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

    /**
     * 通知ごとの目印。tag は同じ投稿で共通だが、iOS は同じ tag の通知を置き換えずに並べるため、
     * どの通知がタップされた（通知センターから消えた）かを見分けるのに使う（PushNoticeLog）
     */
    public readonly string $nid;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $tag,
        public readonly string $url,
    ) {
        $this->nid = Str::random(12);
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Declarative Web Push の形式（{ web_push: 8030, notification: {...}, mutable: true }）で送る。
     *
     * mutable にして、iOS 18.4 以降でも push を Service Worker に渡させ、通知は sw.js が表示する。
     * 2026-09-22 の実機確認で、iOS に表示を任せても（navigate を指定しても）、アプリがバックグラウンドだと
     * タップで目的の画面に移れなかった（WebKit の既知の不具合 bug 268797）。sw.js が表示した通知なら、
     * 前面に戻ったときに消えた通知からタップされた通知を割り出せる（resources/assets/js/deep-link.js）。
     * Service Worker が動かなかったときは、この形式のおかげで iOS が代わりに表示する。
     * 対応していないブラウザ（Android の Chrome など）には通常の push として届く。どちらも sw.js が data.url を使う。
     */
    public function toWebPush(mixed $notifiable, mixed $notification = null): WebPushMessageInterface
    {
        $origin = rtrim((string) config('app.url'), '/');

        return (new DeclarativeWebPushMessage)
            ->title($this->title)
            ->body(self::truncate($this->body))
            // アドレスは完全な形にする。iOS は icon を基準なしで読むため、'/appicon.png' だと
            // この形式として読めず（2026-09-22 実機で確認）、通常の push 扱いになってタップで画面を移れない
            ->icon($origin . self::ICON)
            ->badge($origin . self::ICON)
            ->tag($this->tag)
            // 同じtagで置き換えたときも音・バイブで知らせる
            ->renotify(true)
            ->data($this->data($notifiable))
            // タップしたときに iOS が移る先。完全なアドレスでなければならない
            ->navigate($origin . $this->url)
            ->lang('ja')
            ->mutable(true)
            // 端末がオフラインでも1日は再送を試みる。iOSは urgency が低いと届くのが遅れる
            ->options(['TTL' => 86400, 'urgency' => 'high']);
    }

    /**
     * 通知の data。
     * - url：開く画面(相対)。Declarative Web Push に対応していないブラウザ向け
     * - nid：通知ごとの目印(#110。どの通知がタップされたかを見分ける)
     * - badge：アイコンに出す、まだ見ていないお知らせの数(#123)。この通知を含める。
     *   送った記録(PushNoticeLog::record)は送った後に付くので、ここで 1 を足す
     *
     * @return array<string, mixed>
     */
    private function data(mixed $notifiable): array
    {
        $data = ['url' => $this->url, 'nid' => $this->nid];
        if ($notifiable instanceof User) {
            $data['badge'] = PushNoticeLog::unseenCount($notifiable) + 1;
        }

        return $data;
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
