<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Course\Models\Course;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            ['CourseID' => 1, 'CourseName' => 'أساسيات البرمجة'],
            ['CourseID' => 2, 'CourseName' => 'تطوير تطبيقات الويب'],
            ['CourseID' => 3, 'CourseName' => 'تطوير تطبيقات الموبايل'],
            ['CourseID' => 4, 'CourseName' => 'قواعد البيانات'],
            ['CourseID' => 5, 'CourseName' => 'الأمن السيبراني'],
            ['CourseID' => 6, 'CourseName' => 'الذكاء الاصطناعي'],
            ['CourseID' => 7, 'CourseName' => 'التصميم الجرافيكي'],
            ['CourseID' => 8, 'CourseName' => 'التسويق الرقمي'],
            ['CourseID' => 9, 'CourseName' => 'إدارة المشاريع'],
            ['CourseID' => 10, 'CourseName' => 'اللغة الإنجليزية للأعمال'],
            ['CourseID' => 11, 'CourseName' => 'المحاسبة المالية'],
            ['CourseID' => 12, 'CourseName' => 'الموارد البشرية'],
        ];

        Course::unguard();
        foreach ($courses as $course) {
            Course::firstOrCreate(['CourseName' => $course['CourseName']], $course);
        }
        Course::reguard();
    }
}
