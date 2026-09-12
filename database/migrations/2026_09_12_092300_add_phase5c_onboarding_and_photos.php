<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('notes');
            $table->string('home_phone')->nullable()->after('phone');
            $table->string('cell_phone')->nullable()->after('home_phone');
            $table->string('alternate_phone')->nullable()->after('cell_phone');
            $table->string('employment_type')->nullable()->after('job_title');
            $table->string('preferred_shift_type')->nullable()->after('employment_type');
            $table->unsignedTinyInteger('desired_hours_per_week')->nullable()->after('preferred_shift_type');
            $table->boolean('willing_long_term')->nullable()->after('desired_hours_per_week');
            $table->boolean('willing_short_term')->nullable()->after('willing_long_term');
            $table->boolean('willing_pets')->nullable()->after('willing_short_term');
            $table->boolean('willing_smoke')->nullable()->after('willing_pets');
            $table->string('previous_address_line_1')->nullable()->after('postal_code');
            $table->string('previous_city')->nullable()->after('previous_address_line_1');
            $table->string('previous_state')->nullable()->after('previous_city');
            $table->string('previous_postal_code')->nullable()->after('previous_state');
            $table->string('how_heard')->nullable();
            $table->text('employment_interest')->nullable();
            $table->boolean('has_drivers_license')->nullable();
            $table->string('license_state')->nullable();
            $table->string('license_number')->nullable();
            $table->string('vehicle_make_year')->nullable();
            $table->string('insurance_company')->nullable();
            $table->string('insurance_policy_number')->nullable();
            $table->boolean('has_moving_violations')->nullable();
            $table->text('moving_violations_description')->nullable();
            $table->boolean('license_ever_suspended')->nullable();
            $table->text('license_suspension_explanation')->nullable();
            $table->boolean('may_contact_current_employer')->nullable();
            $table->boolean('ohio_resident_5_years')->nullable();
            $table->text('residence_history')->nullable();
            $table->boolean('used_other_names')->nullable();
            $table->text('other_names')->nullable();
            $table->text('ssn')->nullable();
            $table->text('alternate_ssn')->nullable();
            $table->boolean('has_conviction')->nullable();
            $table->text('security_comments')->nullable();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('notes');
        });

        Schema::table('employee_credentials', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('notes');
        });

        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('level');
            $table->string('institution_name')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->boolean('graduated')->nullable();
            $table->unsignedTinyInteger('years_completed')->nullable();
            $table->string('degree')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('home_phone')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('relationship')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_work_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('job_title')->nullable();
            $table->string('employer')->nullable();
            $table->string('employer_phone')->nullable();
            $table->string('employer_address')->nullable();
            $table->string('reason_for_leaving')->nullable();
            $table->text('job_duties')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_security_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('incident')->nullable();
            $table->string('city_state')->nullable();
            $table->string('charge')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_security_incidents');
        Schema::dropIfExists('employee_work_histories');
        Schema::dropIfExists('employee_references');
        Schema::dropIfExists('employee_educations');

        Schema::table('employee_credentials', function (Blueprint $table) {
            $table->dropColumn('document_path');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('profile_photo_path');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'profile_photo_path',
                'home_phone',
                'cell_phone',
                'alternate_phone',
                'employment_type',
                'preferred_shift_type',
                'desired_hours_per_week',
                'willing_long_term',
                'willing_short_term',
                'willing_pets',
                'willing_smoke',
                'previous_address_line_1',
                'previous_city',
                'previous_state',
                'previous_postal_code',
                'how_heard',
                'employment_interest',
                'has_drivers_license',
                'license_state',
                'license_number',
                'vehicle_make_year',
                'insurance_company',
                'insurance_policy_number',
                'has_moving_violations',
                'moving_violations_description',
                'license_ever_suspended',
                'license_suspension_explanation',
                'may_contact_current_employer',
                'ohio_resident_5_years',
                'residence_history',
                'used_other_names',
                'other_names',
                'ssn',
                'alternate_ssn',
                'has_conviction',
                'security_comments',
            ]);
        });
    }
};
