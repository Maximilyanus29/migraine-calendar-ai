<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE attacks DROP CONSTRAINT IF EXISTS attacks_end_after_start');
        DB::statement('ALTER TABLE attacks ALTER COLUMN end_at DROP NOT NULL');
        DB::statement('ALTER TABLE attacks ADD CONSTRAINT attacks_end_after_start CHECK (end_at IS NULL OR end_at > start_at)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE attacks DROP CONSTRAINT IF EXISTS attacks_end_after_start');
        DB::statement("UPDATE attacks SET end_at = start_at + INTERVAL '1 hour' WHERE end_at IS NULL");
        DB::statement('ALTER TABLE attacks ALTER COLUMN end_at SET NOT NULL');
        DB::statement('ALTER TABLE attacks ADD CONSTRAINT attacks_end_after_start CHECK (end_at > start_at)');
    }
};
