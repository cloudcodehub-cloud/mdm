<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['client_id', 'role']);
        });

        Schema::create('client_service_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 32)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'is_primary', 'is_active']);
        });

        Schema::create('care_plan_effective_date_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_plan_id')->constrained()->cascadeOnDelete();
            $table->date('previous_starts_on');
            $table->timestamp('previous_effective_at')->nullable();
            $table->date('corrected_starts_on');
            $table->timestamp('corrected_effective_at')->nullable();
            $table->text('reason');
            $table->foreignId('corrected_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('client_admin_history_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('summary');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->date('effective_on')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
        });

        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->foreignId('client_service_location_id')
                ->nullable()
                ->after('client_id')
                ->constrained('client_service_locations')
                ->nullOnDelete();
            $table->string('service_location_label')->nullable()->after('client_service_location_id');
            $table->string('service_address_line_1')->nullable()->after('service_location_label');
            $table->string('service_address_line_2')->nullable()->after('service_address_line_1');
            $table->string('service_city')->nullable()->after('service_address_line_2');
            $table->string('service_state', 32)->nullable()->after('service_city');
            $table->string('service_postal_code', 32)->nullable()->after('service_state');
            $table->decimal('service_latitude', 10, 7)->nullable()->after('service_postal_code');
            $table->decimal('service_longitude', 10, 7)->nullable()->after('service_latitude');
        });

        $this->backfillContactsAndLocations();
    }

    public function down(): void
    {
        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_service_location_id');
            $table->dropColumn([
                'service_location_label',
                'service_address_line_1',
                'service_address_line_2',
                'service_city',
                'service_state',
                'service_postal_code',
                'service_latitude',
                'service_longitude',
            ]);
        });

        Schema::dropIfExists('client_admin_history_events');
        Schema::dropIfExists('care_plan_effective_date_corrections');
        Schema::dropIfExists('client_service_locations');
        Schema::dropIfExists('client_contacts');
    }

    private function backfillContactsAndLocations(): void
    {
        $clients = DB::table('clients')->orderBy('id')->get([
            'id',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_phone',
        ]);

        $now = now();

        foreach ($clients as $client) {
            if (filled($client->emergency_contact_name)) {
                DB::table('client_contacts')->insert([
                    'client_id' => $client->id,
                    'role' => 'emergency',
                    'name' => $client->emergency_contact_name,
                    'relationship' => $client->emergency_contact_relationship,
                    'phone' => $client->emergency_contact_phone,
                    'email' => null,
                    'notes' => null,
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (filled($client->address_line_1)) {
                DB::table('client_service_locations')->insert([
                    'client_id' => $client->id,
                    'label' => 'Primary',
                    'address_line_1' => $client->address_line_1,
                    'address_line_2' => $client->address_line_2,
                    'city' => $client->city,
                    'state' => $client->state,
                    'postal_code' => $client->postal_code,
                    'latitude' => null,
                    'longitude' => null,
                    'is_primary' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
