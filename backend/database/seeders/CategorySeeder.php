<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Income categories
            ['name' => 'Salário',       'type' => 'income'],
            ['name' => 'Freelance',     'type' => 'income'],
            ['name' => 'Investimento',  'type' => 'income'],
            ['name' => 'Outros',        'type' => 'income'],

            // Expense categories
            ['name' => 'Reserva de Emergência', 'type' => 'expense'],
            ['name' => 'Moradia',               'type' => 'expense'],
            ['name' => 'Alimentação',   'type' => 'expense'],
            ['name' => 'Transporte',    'type' => 'expense'],
            ['name' => 'Saúde',         'type' => 'expense'],
            ['name' => 'Educação',      'type' => 'expense'],
            ['name' => 'Lazer',         'type' => 'expense'],
            ['name' => 'Compras',       'type' => 'expense'],
            ['name' => 'Pets',          'type' => 'expense'],
            ['name' => 'Outros',        'type' => 'expense'],
        ];

        foreach ($categories as $category) {
            $exists = DB::table('categories')
                ->whereNull('user_id')
                ->where('name', $category['name'])
                ->where('type', $category['type'])
                ->exists();

            if (! $exists) {
                DB::table('categories')->insert([
                    'user_id'    => null,
                    'name'       => $category['name'],
                    'type'       => $category['type'],
                    'created_at' => now(),
                ]);
            }
        }
    }
}
