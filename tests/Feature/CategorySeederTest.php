<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Subcategory;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SubcategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_consolidated_category_and_subcategory_catalogue_idempotently(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);

        $this->assertDatabaseHas('categories', [
            'name' => 'EPF',
            'type' => 'income',
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Others',
            'type' => 'income',
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Others',
            'type' => 'expense',
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Reimbursement',
            'type' => 'income',
        ]);
        $this->assertDatabaseMissing('categories', ['name' => 'Other']);
        $this->assertDatabaseMissing('categories', ['name' => 'Modified Bal.']);
        $this->assertDatabaseCount('categories', 35);

        $foodId = (int) Category::query()
            ->where('name', 'Food')
            ->where('type', 'expense')
            ->value('id');
        $this->assertDatabaseHas('subcategories', [
            'category_id' => $foodId,
            'name' => 'Lunch',
        ]);
        $this->assertSame(
            1,
            Subcategory::query()->where('category_id', $foodId)->where('name', 'Lunch')->count(),
        );
    }
}
