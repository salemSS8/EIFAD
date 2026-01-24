<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Skill\Models\Skill;
use App\Domain\Skill\Models\SkillCategory;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skillsByCategory = [
            'البرمجة والتطوير' => [
                'PHP',
                'Laravel',
                'JavaScript',
                'React',
                'Vue.js',
                'Node.js',
                'Python',
                'Django',
                'Java',
                'Spring Boot',
                'C#',
                '.NET',
                'Flutter',
                'React Native',
                'Swift',
                'Kotlin',
                'SQL',
                'MySQL',
                'PostgreSQL',
                'MongoDB',
                'Git',
                'Docker',
                'AWS',
                'Linux',
            ],
            'التصميم والجرافيك' => [
                'Photoshop',
                'Illustrator',
                'Figma',
                'Adobe XD',
                'UI/UX Design',
                'تصميم الهوية البصرية',
                'Motion Graphics',
                'After Effects',
                'Premiere Pro',
                'تصميم المواقع',
            ],
            'الإدارة والقيادة' => [
                'إدارة المشاريع',
                'القيادة',
                'التخطيط الاستراتيجي',
                'إدارة الفرق',
                'حل المشكلات',
                'اتخاذ القرارات',
                'Agile',
                'Scrum',
                'PMP',
            ],
            'التسويق والمبيعات' => [
                'التسويق الرقمي',
                'SEO',
                'Google Ads',
                'Facebook Ads',
                'التسويق بالمحتوى',
                'إدارة وسائل التواصل',
                'المبيعات',
                'خدمة العملاء',
                'التفاوض',
                'العلاقات العامة',
            ],
            'المحاسبة والمالية' => [
                'المحاسبة المالية',
                'محاسبة التكاليف',
                'الميزانيات',
                'التحليل المالي',
                'Excel المتقدم',
                'QuickBooks',
                'SAP',
                'الضرائب',
                'المراجعة',
            ],
        ];

        foreach ($skillsByCategory as $categoryName => $skills) {
            $category = SkillCategory::where('CategoryName', $categoryName)->first();

            if ($category) {
                foreach ($skills as $skillName) {
                    Skill::firstOrCreate(
                        ['SkillName' => $skillName],
                        ['SkillName' => $skillName, 'CategoryID' => $category->CategoryID]
                    );
                }
            }
        }
    }
}
