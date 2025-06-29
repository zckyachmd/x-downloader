<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tweets', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('username');
            $table->string('tweet_id')->unique();
            $table->text('tweet');
            $table->string('related_tweet_id')->nullable();
            $table->json('urls')->nullable();
            $table->json('media')->nullable();
            $table->string('status', 20)->default('queue');
            $table->timestamps();

            $table->unique(['user_id', 'tweet_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tweets');
    }
};
