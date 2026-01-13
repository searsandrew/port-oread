<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('planets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('flavor');
            $table->string('type')->nullable();
            $table->string('class');
            $table->integer('victory_point_value');
            $table->string('filename');
            $table->boolean('is_standard')->default(false);
            $table->boolean('is_purchasable')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_promotional')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planets');
    }
};
