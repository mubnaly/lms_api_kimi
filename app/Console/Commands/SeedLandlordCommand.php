<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedLandlordCommand extends Command
{
    protected $signature = 'db:seed:landlord {--fresh : Drop all tables before seeding}';
    protected $description = 'Seed the landlord database with test data';

    public function handle(): int
    {
        $this->info('🌱 Seeding landlord database...');

        if ($this->option('fresh')) {
            $this->warn('🗑️  Dropping all landlord tables...');
            Artisan::call('migrate:fresh', [
                '--path' => 'database/migrations/landlord',
                '--database' => 'landlord',
                '--force' => true,
            ]);
        }

        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\Landlord\\DatabaseSeeder',
            '--database' => 'landlord',
            '--force' => true,
        ]);

        $this->info('✅ Landlord database seeded successfully!');
        $this->line('');
        $this->line('📧 Login Credentials:');
        $this->line('   Super Admin: superadmin@lms.com / password123');
        $this->line('   Admin: admin@lms.com / password123');
        $this->line('   Support: support@lms.com / password123');

        return Command::SUCCESS;
    }
}
