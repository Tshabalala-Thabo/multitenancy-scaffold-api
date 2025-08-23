<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_user_bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('reason')->nullable();
            $table->timestamp('banned_at')->useCurrent();
            $table->foreignId('banned_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('unbanned_at')->nullable();
            $table->foreignId('unbanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('unban_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'banned_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_bans');
    }
};
