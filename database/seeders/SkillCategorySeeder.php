<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Skill\Models\SkillCategory;

class SkillCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['CategoryName' => 'البرمجة والتطوير'],
            ['CategoryName' => 'التصميم والجرافيك'],
            ['CategoryName' => 'الإدارة والقيادة'],
            ['CategoryName' => 'التسويق والمبيعات'],
            ['CategoryName' => 'المحاسبة والمالية'],
            ['CategoryName' => 'الموارد البشرية'],
            ['CategoryName' => 'خدمة العملاء'],
            ['CategoryName' => 'اللغات والترجمة'],
            ['CategoryName' => 'الهندسة'],
            ['CategoryName' => 'الصحة والطب'],
        ];

        foreach ($categories as $category) {
            SkillCategory::firstOrCreate(['CategoryName' => $category['CategoryName']], $category);
        }
    }
}
