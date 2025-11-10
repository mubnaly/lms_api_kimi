<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

class SeedTenantCommand extends Command
{
    protected $signature = 'db:seed:tenant {tenant? : The tenant ID or domain} {--all : Seed all tenants} {--fresh : Drop tables before seeding}';
    protected $description = 'Seed tenant database(s) with test data';

    public function handle(): int
    {
        if ($this->option('all')) {
            $tenants = Tenant::where('is_active', true)->get();
            $this->info("🌱 Seeding {$tenants->count()} tenant databases...");

            foreach ($tenants as $tenant) {
                $this->seedTenant($tenant);
            }
        } elseif ($tenantId = $this->argument('tenant')) {
            $tenant = Tenant::where('id', $tenantId)
                ->orWhere('domain', $tenantId)
                ->first();

            if (!$tenant) {
                $this->error("❌ Tenant not found: {$tenantId}");
                return Command::FAILURE;
            }

            $this->seedTenant($tenant);
        } else {
            $this->error('❌ Please specify a tenant ID/domain or use --all option');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function seedTenant(Tenant $tenant): void
    {
        $this->info("🌱 Seeding tenant: {$tenant->name} ({$tenant->domain})");

        // Switch to tenant database
        Config::set('database.connections.tenant.database', $tenant->database);
        Config::set('database.default', 'tenant');

        // Clear connection cache
        app('db')->purge('tenant');
        app('db')->reconnect('tenant');

        if ($this->option('fresh')) {
            $this->warn("🗑️  Dropping all tables for {$tenant->name}...");
            Artisan::call('migrate:fresh', [
                '--path' => 'database/migrations/tenant',
                '--database' => 'tenant',
                '--force' => true,
            ]);
        }

        // Run tenant migrations if not fresh
        if (!$this->option('fresh')) {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--database' => 'tenant',
                '--force' => true,
            ]);
        }

        // Seed tenant data
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\Tenant\\DatabaseSeeder',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        $this->info("✅ Tenant {$tenant->name} seeded successfully!");
        $this->line('📧 Sample Login Credentials:');
        $this->line("   Admin: admin@{$tenant->domain} / password123");
        $this->line("   Instructor: instructor1@{$tenant->domain} / password123");
        $this->line("   Student: student1@{$tenant->domain} / password123");
        $this->line('');
    }
}
