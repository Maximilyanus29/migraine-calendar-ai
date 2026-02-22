<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_triggers')) {
            return;
        }

        Schema::table('custom_triggers', function (Blueprint $table): void {
            $table->string('category', 32)->default('triggers')->after('user_id');
        });

        DB::table('custom_triggers')->whereNull('category')->update(['category' => 'triggers']);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("UPDATE custom_triggers SET category = 'triggers' WHERE category = ''");
        DB::statement('ALTER TABLE custom_triggers DROP CONSTRAINT IF EXISTS custom_triggers_user_id_name_normalized_unique');
        DB::statement('ALTER TABLE custom_triggers ADD CONSTRAINT custom_triggers_user_category_name_unique UNIQUE (user_id, category, name_normalized)');
        DB::statement("ALTER TABLE custom_triggers ADD CONSTRAINT custom_triggers_category_check CHECK (category IN ('triggers','pain_types','localizations','symptoms','auras'))");
        DB::statement('CREATE INDEX IF NOT EXISTS custom_triggers_user_category_created_idx ON custom_triggers(user_id, category, created_at)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('custom_triggers')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS custom_triggers_user_category_created_idx');
            DB::statement('ALTER TABLE custom_triggers DROP CONSTRAINT IF EXISTS custom_triggers_category_check');
            DB::statement('ALTER TABLE custom_triggers DROP CONSTRAINT IF EXISTS custom_triggers_user_category_name_unique');
            DB::statement('ALTER TABLE custom_triggers ADD CONSTRAINT custom_triggers_user_id_name_normalized_unique UNIQUE (user_id, name_normalized)');
        }

        Schema::table('custom_triggers', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
