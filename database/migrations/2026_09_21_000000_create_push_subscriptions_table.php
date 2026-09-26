<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Webプッシュの購読情報(#110)。
 *
 * laravel-notification-channels/webpush 13.x 同梱のマイグレーション
 * (create_push_subscriptions_table.php.stub)と同じ定義。
 * 端末(ブラウザ)ごとに1行。同じ利用者がiPhoneとPCの両方で購読できる。
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var string|null $connection */
        $connection = config('webpush.database_connection');
        /** @var string $tableName */
        $tableName = config('webpush.table_name', 'push_subscriptions');

        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('subscribable', 'push_subscriptions_subscribable_morph_idx');
            // ASCIIにしているのは、1024文字でもユニークインデックスの長さ制限に収めるため
            $table->string('endpoint', PushSubscription::ENDPOINT_MAX_LENGTH)
                ->charset('ascii')
                ->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        /** @var string|null $connection */
        $connection = config('webpush.database_connection');
        /** @var string $tableName */
        $tableName = config('webpush.table_name', 'push_subscriptions');

        Schema::connection($connection)->dropIfExists($tableName);
    }
};
