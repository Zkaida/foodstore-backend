<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@foodstore.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
            ]
        );

        $categories = [
            ['name' => 'Nasi Goreng', 'image' => 'categories/default.png'],
            ['name' => 'Mie & Bakso', 'image' => 'categories/default.png'],
            ['name' => 'Minuman Segar', 'image' => 'categories/default.png'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name']], $cat);
        }

        $nasiGoreng = Category::where('name', 'Nasi Goreng')->first();
        $minuman = Category::where('name', 'Minuman Segar')->first();

        if ($nasiGoreng) {
            Product::firstOrCreate(
                ['title' => 'Nasi Goreng Spesial'],
                [
                    'category_id' => $nasiGoreng->id,
                    'image' => 'products/default.png',
                    'description' => '<p>Nasi goreng spesial dengan telur mata sapi, ayam suwir, dan kerupuk udang.</p>',
                    'price' => 25000,
                    'weight' => 450,
                ]
            );
        }

        if ($minuman) {
            Product::firstOrCreate(
                ['title' => 'Es Teh Manis Jumbo'],
                [
                    'category_id' => $minuman->id,
                    'image' => 'products/default.png',
                    'description' => '<p>Es teh melati manis segar ukuran jumbo.</p>',
                    'price' => 8000,
                    'weight' => 300,
                ]
            );
        }
    }
}
