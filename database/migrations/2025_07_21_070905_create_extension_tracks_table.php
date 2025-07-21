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
        Schema::create('extension_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_twitter_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('user_agent')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extension_tracks');
    }
};
