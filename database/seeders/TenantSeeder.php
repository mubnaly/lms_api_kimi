<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;

class TenantSeeder extends Seeder
{
    public function run()
    {
        $tenant = Tenant::create([
            'name' => 'Demo Academy',
            'organization_name' => 'Demo Academy Egypt',
            'email' => 'demo@academy.eg',
            'phone' => '01000000000',
            'address' => 'Cairo, Egypt',
            'city' => 'Cairo',
            'country' => 'Egypt',
            'domain' => 'demo.' . env('APP_DOMAIN', 'localhost'),
            'primary_color' => '#3b82f6',
            'secondary_color' => '#10b981',
            'is_active' => true,
        ]);

        $tenant->domains()->create([
            'domain' => 'demo.' . env('APP_DOMAIN', 'localhost'),
        ]);
    }
}
