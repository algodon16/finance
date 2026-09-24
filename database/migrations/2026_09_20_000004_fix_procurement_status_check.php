<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend the procurement_requests status CHECK constraint with the FMS
 * lifecycle statuses. Existing rows are preserved.
 * (Laravel maps ->enum() to VARCHAR + CHECK on PostgreSQL.)
 */
return new class extends Migration
{
    public function up(): void
    {
        $values = "'submitted','pending_approval','approved','for_payment','processing','ready_for_pickup','released','completed','rejected','draft','under_review','ordered','fulfilled','cancelled','pending'";
        try {
            DB::statement('ALTER TABLE procurement_requests DROP CONSTRAINT IF EXISTS procurement_requests_status_check');
        } catch (\Throwable $e) {
        }
        DB::statement("ALTER TABLE procurement_requests ADD CONSTRAINT procurement_requests_status_check CHECK (status IN ({$values}))");
    }

    public function down(): void
    {
    }
};
