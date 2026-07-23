<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_epf_as_an_income_category_idempotently(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertDatabaseHas('categories', [
            'name' => 'EPF',
            'type' => 'income',
        ]);

        $this->assertDatabaseCount('categories', 33);
    }
}
