<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotIn('role', ['admin', 'user'])
            ->update(['role' => 'admin']);

        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        // Roles removed intentionally; old staff roles cannot be restored safely.
    }
};
