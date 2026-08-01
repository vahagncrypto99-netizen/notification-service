<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() : void
    {
        /**
         * Очередь почтового канала: приоритет и отложенная отправка.
         */
        Schema::create('notification_mail_queue', function (Blueprint $table) {
            $table->id();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('to_email');
            $table->string('subject');
            $table->text('message');
            $table->json('additionally')->nullable();
            $table->string('sender')->nullable();
            $table->unsignedInteger('priority')->default(1);
            $table->timestamp('send_at')->nullable();

            $table->timestamps();

            $table->index(['priority', 'id']);
            $table->index('send_at');
        });

        /**
         * Очередь мессенджеров.
         */
        Schema::create('notification_messenger_queue', function (Blueprint $table) {
            $table->id();
            $table->string('messenger');
            $table->string('messenger_id');
            $table->text('message');
            $table->timestamp('send_at')->nullable();

            $table->timestamps();

            $table->index(['messenger', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() : void
    {
        Schema::dropIfExists('notification_mail_queue');
        Schema::dropIfExists('notification_messenger_queue');
    }
};
