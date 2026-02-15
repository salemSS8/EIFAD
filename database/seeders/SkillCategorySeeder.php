<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Skill\Models\SkillCategory;

class SkillCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['CategoryID' => 1, 'CategoryName' => 'البرمجة والتطوير'],
            ['CategoryID' => 2, 'CategoryName' => 'التصميم والجرافيك'],
            ['CategoryID' => 3, 'CategoryName' => 'الإدارة والقيادة'],
            ['CategoryID' => 4, 'CategoryName' => 'التسويق والمبيعات'],
            ['CategoryID' => 5, 'CategoryName' => 'المحاسبة والمالية'],
            ['CategoryID' => 6, 'CategoryName' => 'الموارد البشرية'],
            ['CategoryID' => 7, 'CategoryName' => 'خدمة العملاء'],
            ['CategoryID' => 8, 'CategoryName' => 'اللغات والترجمة'],
            ['CategoryID' => 9, 'CategoryName' => 'الهندسة'],
            ['CategoryID' => 10, 'CategoryName' => 'الصحة والطب'],
        ];

        SkillCategory::unguard();

        foreach ($categories as $category) {
            SkillCategory::firstOrCreate(['CategoryName' => $category['CategoryName']], $category);
        }
        SkillCategory::reguard();
    }
}
