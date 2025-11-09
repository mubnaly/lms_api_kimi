// database/migrations/landlord/2024_01_01_100000_create_tenants_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('organization_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Egypt');
            $table->string('database')->nullable();
            $table->string('domain')->unique();
            $table->string('primary_color')->default('#3b82f6');
            $table->string('secondary_color')->default('#10b981');
            $table->boolean('is_active')->default(true);
            $table->timestamp('subscription_ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('subscription_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
