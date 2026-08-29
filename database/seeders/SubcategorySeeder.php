<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class SubcategorySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'expense' => [
                'Food' => ['Brunch', 'Ingredients', 'Beverages', 'Snacks', 'Candies', 'Pastries', 'Supper', 'Dessert', 'Dinner', 'Breakfast', 'Lunch'],
                'Household' => ['Utilities', 'Gardening Tools', 'Home Fragrances', 'Home Tools', 'Appliances', 'Furniture', 'Kitchen', 'Toiletries', 'Chandlery', 'Pet Supplies'],
                'Health' => ['Clinic', 'Gym', 'Prescriptions', 'Supplements', 'Health', 'Hospital', 'Medicine', 'Sports'],
                'Personal Care' => ['Body Care', 'Skincare', 'Hair Care'],
                'IT Product' => ['Hardware', 'Accessories', 'Software', 'Peripherals', 'Devices', 'Maintenance'],
                'Prepaid Reload' => ['KK Parking', 'Maxis Hotlink', 'Digi', 'XOX', 'Celcom'],
                'Transportation' => ['Accessories', 'Fuel', 'Maintenance', 'Parking', 'Public Transport', 'Car', 'Grab/Taxi'],
                'Apparel' => ['Bags', 'Clothing', 'Fashion', 'Shoes', 'Laundry', 'Accessories'],
                'Books and Stationery' => ['Books', 'Stationery'],
                'Fees' => ['Banking', 'University', 'Utility'],
                'Subscriptions' => ['ChatGPT', 'Deepseek', 'GitHub Copilot', 'Google', 'Spotify', 'YouTube', 'Others'],
                'Entertainment' => ['Collectibles', 'Experiences', 'Events', 'Music', 'Physical Games and Toys', 'Video Games', 'Lottery', 'Others'],
                'Gifts and Giving' => ['Donations', 'Souvenir'],
                'Travel' => ['Vietnam 2025', 'KL-SAIA 2026'],
                'Payments' => ['BNPL'],
                'Special Projects' => ['Project Firelight', 'Project Silicon', 'Money Pool', 'Snacktime'],
            ],
            'income' => [
                'Allowance' => ['Family', 'Blaze Tech', 'GAMUDA'],
                'Salary' => ['Blaze Tech', 'EPF'],
                'EPF' => ['Blaze Tech'],
                'Interest' => ['GO+', 'Bank', 'Ryt'],
                'Reimbursement' => ['Blaze Tech'],
            ],
        ];

        foreach ($catalog as $type => $categories) {
            foreach ($categories as $categoryName => $subcategoryNames) {
                $category = Category::query()->where('type', $type)->where('name', $categoryName)->first();
                if (! $category) {
                    continue;
                }

                foreach ($subcategoryNames as $name) {
                    Subcategory::query()->firstOrCreate([
                        'category_id' => $category->id,
                        'name' => $name,
                    ]);
                }
            }
        }
    }
}
