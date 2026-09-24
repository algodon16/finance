<?php

namespace Database\Seeders;

use App\Models\ProcurementItem;
use Illuminate\Database\Seeder;

class LearningMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $books = [
            [
                'item_name' => 'Introduction to Programming',
                'description' => 'Basic concepts of programming.',
                'category' => 'Programming',
                'price' => 650.00,
                'stock_quantity' => 120,
                'is_available' => true,
            ],
            [
                'item_name' => 'Database Management Systems',
                'description' => 'Comprehensive guide to database systems.',
                'category' => 'Database',
                'price' => 700.00,
                'stock_quantity' => 80,
                'is_available' => true,
            ],
            [
                'item_name' => 'Web Development Fundamentals',
                'description' => 'Learn HTML, CSS, and JavaScript.',
                'category' => 'Web Development',
                'price' => 550.00,
                'stock_quantity' => 50,
                'is_available' => true,
            ],
            [
                'item_name' => 'Computer Networking',
                'description' => 'Fundamentals of computer networks.',
                'category' => 'Networking',
                'price' => 750.00,
                'stock_quantity' => 40,
                'is_available' => true,
            ],
            [
                'item_name' => 'System Analysis and Design',
                'description' => 'Principles and techniques in system development.',
                'category' => 'Systems Analysis',
                'price' => 600.00,
                'stock_quantity' => 30,
                'is_available' => true,
            ],
        ];

        // Rename legacy seed rows to the canonical titles, then align values.
        $renames = [
            'Database Management' => 'Database Management Systems',
            'Web Development Basics' => 'Web Development Fundamentals',
        ];
        foreach ($renames as $old => $new) {
            ProcurementItem::where('item_name', $old)->update(['item_name' => $new]);
        }

        foreach ($books as $book) {
            ProcurementItem::updateOrCreate(
                ['item_name' => $book['item_name']],
                $book
            );
        }
    }
}
