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
        Schema::create('demo_seed_records', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('seedable_type');
            $table->unsignedBigInteger('seedable_id');
            $table->timestamps();

            $table->index(['seedable_type', 'seedable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_seed_records');
    }
};
