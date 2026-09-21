<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Shared password for all local/demo login accounts. Not production configuration.
     */
    public const PASSWORD = 'password';

    public const ADMIN_EMAIL = 'admin@mdm.test';

    public const SUPERVISOR_EMAIL = 'jordan.hale@mdm.test';

    public const DSP_EMAIL = 'maya.chen@mdm.test';

    /**
     * Seed the Lakeside Supported Living demo agency.
     */
    public function run(): void
    {
        $this->call(DemoDataSeeder::class);
    }
}
