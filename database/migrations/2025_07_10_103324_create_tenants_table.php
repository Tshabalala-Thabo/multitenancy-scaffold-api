<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // For tenant identification via subdomain or URL
            $table->string('domain')->unique()->nullable();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->string('privacy_setting')->default('private');
            $table->boolean('two_factor_auth_required')->default(false);
            $table->json('password_policy')->nullable();
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }

    protected function createAddressTable(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('street_address');
            $table->string('suburb');
            $table->string('city');
            $table->string('province');
            $table->string('postal_code');
            $table->timestamps();
        });
    }
};
