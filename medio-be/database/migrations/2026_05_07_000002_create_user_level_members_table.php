<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_level_members')) {
            Schema::create('user_level_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id');
                $table->foreignId('level_member_id');
                $table->unsignedInteger('points_snapshot')->default(0);
                $table->enum('assignment_type', ['auto', 'manual'])->default('auto');
                $table->foreignId('assigned_by')->nullable();
                $table->timestamp('effective_from')->useCurrent();
                $table->timestamp('effective_until')->nullable();
                $table->unsignedBigInteger('active_membership_user_id')->nullable();
                $table->timestamps();
            });
        }

        if ($this->isGeneratedColumn('user_level_members', 'active_membership_user_id')) {
            if ($this->hasIndex('user_level_members', 'user_level_members_active_unique')) {
                DB::statement('ALTER TABLE `user_level_members` DROP INDEX `user_level_members_active_unique`');
            }

            DB::statement('ALTER TABLE `user_level_members` DROP COLUMN `active_membership_user_id`');

            Schema::table('user_level_members', function (Blueprint $table) {
                $table->unsignedBigInteger('active_membership_user_id')->nullable()->after('effective_until');
            });
        }

        if (! Schema::hasColumn('user_level_members', 'active_membership_user_id')) {
            Schema::table('user_level_members', function (Blueprint $table) {
                $table->unsignedBigInteger('active_membership_user_id')->nullable()->after('effective_until');
            });
        }

        if (! $this->hasIndex('user_level_members', 'user_level_members_user_id_effective_from_index')) {
            Schema::table('user_level_members', function (Blueprint $table) {
                $table->index(['user_id', 'effective_from']);
            });
        }

        if (! $this->hasIndex('user_level_members', 'user_level_members_active_unique')) {
            DB::statement(
                'ALTER TABLE `user_level_members` ADD UNIQUE `user_level_members_active_unique` (`active_membership_user_id`)'
            );
        }

        if (! $this->hasForeignKey('user_level_members', 'user_level_members_user_id_foreign')) {
            DB::statement(
                'ALTER TABLE `user_level_members` ADD CONSTRAINT `user_level_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'
            );
        }

        if (! $this->hasForeignKey('user_level_members', 'user_level_members_level_member_id_foreign')) {
            DB::statement(
                'ALTER TABLE `user_level_members` ADD CONSTRAINT `user_level_members_level_member_id_foreign` FOREIGN KEY (`level_member_id`) REFERENCES `level_members` (`id`) ON DELETE RESTRICT'
            );
        }

        if (! $this->hasForeignKey('user_level_members', 'user_level_members_assigned_by_foreign')) {
            DB::statement(
                'ALTER TABLE `user_level_members` ADD CONSTRAINT `user_level_members_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_level_members');
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }

    private function isGeneratedColumn(string $table, string $column): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->where('extra', 'like', '%GENERATED%')
            ->exists();
    }
};
