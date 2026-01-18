<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planet_user', function (Blueprint $table) {
            $table->id();
            $table->ulid('user_id');
            $table->ulid('planet_id');
            $table->timestamps();

            $table->unique(['user_id', 'planet_id']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('planet_id')->references('id')->on('planets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planet_user');
    }
};
