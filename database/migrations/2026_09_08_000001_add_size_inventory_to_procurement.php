<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_size_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('procurement_items')->cascadeOnDelete();
            $table->string('size', 20);
            $table->integer('quantity_available')->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'size']);
        });

        Schema::table('procurement_items', function (Blueprint $table) {
            $table->boolean('requires_size')->default(false)->after('category');
        });

        Schema::table('procurement_request_items', function (Blueprint $table) {
            $table->string('size', 20)->nullable()->after('item_id');
        });

        // Backfill: existing uniform-type catalog items get size tracking.
        // Their current total stock is distributed across standard sizes
        // so no existing inventory quantity is lost.
        // NOTE: LOWER() is used because PostgreSQL LIKE is case-sensitive.
        $uniforms = DB::table('procurement_items')
            ->whereNull('category')
            ->where(function ($q) {
                $q->whereRaw('LOWER(item_name) LIKE ?', ['%uniform%'])
                  ->orWhereRaw('LOWER(item_name) LIKE ?', ['%gala%'])
                  ->orWhereRaw('LOWER(item_name) LIKE ?', ['%nstp%']);
            })
            ->get(['id', 'stock_quantity']);

        $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        $now = now();

        foreach ($uniforms as $uniform) {
            DB::table('procurement_items')
                ->where('id', $uniform->id)
                ->update(['requires_size' => true]);

            $total = max(0, (int) $uniform->stock_quantity);
            $base = intdiv($total, count($sizes));
            $remainder = $total % count($sizes);

            $rows = [];
            foreach ($sizes as $index => $size) {
                // Remainder goes to M (most commonly requested size).
                $extra = ($size === 'M') ? $remainder : 0;
                $rows[] = [
                    'item_id' => $uniform->id,
                    'size' => $size,
                    'quantity_available' => $base + $extra,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('item_size_inventory')->upsert(
                $rows,
                ['item_id', 'size'],
                ['quantity_available', 'updated_at']
            );
        }
    }

    public function down(): void
    {
        Schema::table('procurement_request_items', function (Blueprint $table) {
            $table->dropColumn('size');
        });

        Schema::table('procurement_items', function (Blueprint $table) {
            $table->dropColumn('requires_size');
        });

        Schema::dropIfExists('item_size_inventory');
    }
};
