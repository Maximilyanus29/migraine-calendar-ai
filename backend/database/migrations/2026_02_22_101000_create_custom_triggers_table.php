<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_triggers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('name_normalized', 80);
            $table->string('status', 20)->default('pending');
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->unique(['user_id', 'name_normalized']);
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE custom_triggers ADD CONSTRAINT custom_triggers_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_triggers');
    }
};

