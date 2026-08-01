<?php

declare(strict_types=1);

use App\Base\Notification\Enum\ReportStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Применение миграции.
     */
    public function up() : void
    {
        Schema::create('notification_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status')->default(ReportStatusEnum::Pending->value);
            $table->string('file_path')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        /**
         * Partial-индекс под watchdog: «горячих» строк единицы,
         * индекс остаётся крошечным при любом размере таблицы.
         */
        DB::statement(
            'create index notification_reports_stuck_index'
            .' on notification_reports (status, updated_at)'
            ." where status in ('pending', 'processing')"
        );
    }

    /**
     * Откат миграции.
     */
    public function down() : void
    {
        Schema::dropIfExists('notification_reports');
    }
};
