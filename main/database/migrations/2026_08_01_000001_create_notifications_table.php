<?php

use App\Base\Notification\Enum\NotificationStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() : void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('message', 500);
            $table->string('status')->default(NotificationStatusEnum::Processing->value);
            $table->unsignedBigInteger('user_id');
            $table->string('channel');
            $table->unsignedInteger('attempts_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status', 'channel']);
            $table->index(['status', 'updated_at']);
        });

        /**
         * Покрывающий индекс под агрегацию отчётов: equality (user_id) +
         * range (created_at) — в ключе, channel/status — в листьях (INCLUDE).
         * Запрос отчёта выполняется index-only scan-ом, без походов в heap.
         */
        DB::statement(
            'create index notifications_report_aggregate_index'
            .' on notifications (user_id, created_at) include (channel, status)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down() : void
    {
        Schema::dropIfExists('notifications');
    }
};
