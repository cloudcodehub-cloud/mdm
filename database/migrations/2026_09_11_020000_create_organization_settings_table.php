<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name');
            $table->string('timezone');
            $table->string('date_format');
            $table->string('time_format', 8);
            $table->unsignedTinyInteger('first_day_of_week');
            $table->unsignedSmallInteger('credential_expiring_soon_days');
            $table->timestamps();
        });

        DB::table('organization_settings')->insert([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => 'America/New_York',
            'date_format' => 'm/d/Y',
            'time_format' => '12',
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
