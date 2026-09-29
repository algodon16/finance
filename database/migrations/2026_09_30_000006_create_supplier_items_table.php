<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('item_name');
            $table->string('category')->nullable();
            $table->decimal('last_unit_cost', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['supplier_id', 'item_name']);
            $table->index('item_name');
        });

        // Backfill mula sa budget items na may supplier.
        if (Schema::hasTable('financial_requests')) {
            $supIdByName = DB::table('suppliers')->pluck('id', 'name');
            $now = now()->toDateTimeString();
            foreach (DB::table('financial_requests')->select('metadata')->whereNotNull('metadata')->get() as $row) {
                $meta = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;
                foreach ((array) ($meta['items'] ?? []) as $it) {
                    $item = trim(preg_replace('/\s+/', ' ', (string) ($it['item_name'] ?? '')));
                    $sup = trim(preg_replace('/\s+/', ' ', (string) ($it['supplier'] ?? '')));
                    if ($item === '' || $sup === '' || ! isset($supIdByName[$sup])) continue;
                    DB::table('supplier_items')->updateOrInsert(
                        ['supplier_id' => $supIdByName[$sup], 'item_name' => $item],
                        [
                            'category' => $it['category'] ?? null,
                            'last_unit_cost' => isset($it['unit_cost']) ? (float) $it['unit_cost'] : null,
                            'created_at' => $now, 'updated_at' => $now,
                        ]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_items');
    }
};
