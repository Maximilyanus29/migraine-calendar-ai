<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('start_at')->index();
            $table->timestampTz('end_at')->index();
            $table->unsignedTinyInteger('intensity');
            $table->text('medications')->nullable();
            $table->boolean('relief')->nullable();
            $table->jsonb('pain_types')->default('[]');
            $table->jsonb('localizations')->default('[]');
            $table->jsonb('triggers')->default('[]');
            $table->jsonb('symptoms')->default('[]');
            $table->jsonb('auras')->default('[]');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        DB::statement('ALTER TABLE attacks ADD CONSTRAINT attacks_end_after_start CHECK (end_at > start_at)');
        DB::statement('ALTER TABLE attacks ADD CONSTRAINT attacks_intensity_range CHECK (intensity >= 1 AND intensity <= 10)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attacks');
    }
};
