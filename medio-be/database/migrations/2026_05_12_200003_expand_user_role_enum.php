<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand role enum dari ['admin','user'] ke multi-role.
     * SQLite tidak support ALTER COLUMN untuk enum, jadi kita ganti ke string + check constraint.
     * MySQL: gunakan MODIFY COLUMN.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
                'owner',
                'admin',
                'finance',
                'warehouse',
                'customer_service',
                'content_manager',
                'user'
            ) NOT NULL DEFAULT 'user'");
        } else {
            // SQLite: tidak perlu alter, string column sudah cukup
            // Validasi dilakukan di application layer
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user'");
        }
    }
};
