<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Course\Models\Course;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            ['CourseName' => 'أساسيات البرمجة'],
            ['CourseName' => 'تطوير تطبيقات الويب'],
            ['CourseName' => 'تطوير تطبيقات الموبايل'],
            ['CourseName' => 'قواعد البيانات'],
            ['CourseName' => 'الأمن السيبراني'],
            ['CourseName' => 'الذكاء الاصطناعي'],
            ['CourseName' => 'التصميم الجرافيكي'],
            ['CourseName' => 'التسويق الرقمي'],
            ['CourseName' => 'إدارة المشاريع'],
            ['CourseName' => 'اللغة الإنجليزية للأعمال'],
            ['CourseName' => 'المحاسبة المالية'],
            ['CourseName' => 'الموارد البشرية'],
        ];

        foreach ($courses as $course) {
            Course::firstOrCreate(['CourseName' => $course['CourseName']], $course);
        }
    }
}
