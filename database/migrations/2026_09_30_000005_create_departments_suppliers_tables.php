<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Backfill departments mula sa existing records.
        $deptNames = [];
        foreach (['budget_plans', 'financial_requests', 'expenses'] as $tbl) {
            if (! Schema::hasTable($tbl)) continue;
            $col = 'department';
            if (! Schema::hasColumn($tbl, $col)) continue;
            foreach (DB::table($tbl)->select($col)->distinct()->pluck($col) as $n) {
                $n = trim(preg_replace('/\s+/', ' ', (string) $n));
                if ($n !== '') $deptNames[mb_strtolower($n)] = $n;
            }
        }
        // Default na departments ng paaralan kung walang nakuha.
        if (empty($deptNames)) {
            foreach (['College of Computer Studies', 'College of Business Administration', 'College of Hospitality Management', 'College of Education', 'College of Criminology', 'Senior High School', 'Academic Affairs'] as $n) {
                $deptNames[mb_strtolower($n)] = $n;
            }
        }
        $now = now()->toDateTimeString();
        foreach ($deptNames as $n) {
            DB::table('departments')->updateOrInsert(['name' => $n], ['created_at' => $now, 'updated_at' => $now]);
        }

        // Backfill suppliers mula sa budget items ng financial requests.
        if (Schema::hasTable('financial_requests')) {
            $supNames = [];
            foreach (DB::table('financial_requests')->select('metadata')->whereNotNull('metadata')->get() as $row) {
                $meta = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;
                foreach ((array) ($meta['items'] ?? []) as $it) {
                    $n = trim(preg_replace('/\s+/', ' ', (string) ($it['supplier'] ?? '')));
                    if ($n !== '') $supNames[mb_strtolower($n)] = $n;
                }
            }
            foreach ($supNames as $n) {
                DB::table('suppliers')->updateOrInsert(['name' => $n], ['created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('departments');
    }
};
