<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Company\Models\CompanyProfile;
use App\Domain\Course\Models\CourseAd;

class CourseAdSeeder extends Seeder
{
    public function run(): void
    {
        // Only education company offers courses
        $eduCompany = CompanyProfile::where('CompanyName', 'أكاديمية المستقبل للتدريب')->first();

        if (!$eduCompany) return;

        $courses = [
            [
                'CourseTitle' => 'دورة تطوير الويب الشاملة',
                'Topics' => 'HTML, CSS, JavaScript, PHP, Laravel, MySQL, Git',
                'Duration' => '3 أشهر',
                'Location' => 'تعز - حضوري',
                'Trainer' => 'م. أحمد العريقي',
                'Fees' => 500,
                'StartDate' => now()->addDays(15),
                'Status' => 'Active',
            ],
            [
                'CourseTitle' => 'دورة التصميم الجرافيكي الاحترافي',
                'Topics' => 'Photoshop, Illustrator, InDesign, الهوية البصرية',
                'Duration' => '2 أشهر',
                'Location' => 'تعز - حضوري',
                'Trainer' => 'أ. سارة المحمدي',
                'Fees' => 400,
                'StartDate' => now()->addDays(10),
                'Status' => 'Active',
            ],
            [
                'CourseTitle' => 'دورة التسويق الرقمي',
                'Topics' => 'SEO, Google Ads, Facebook Ads, التحليلات, المحتوى',
                'Duration' => '6 أسابيع',
                'Location' => 'أونلاين',
                'Trainer' => 'أ. خالد الصالح',
                'Fees' => 300,
                'StartDate' => now()->addDays(5),
                'Status' => 'Active',
            ],
            [
                'CourseTitle' => 'دورة Flutter لتطوير التطبيقات',
                'Topics' => 'Dart, Flutter, Firebase, REST APIs',
                'Duration' => '2 أشهر',
                'Location' => 'أونلاين',
                'Trainer' => 'م. عمر السعيد',
                'Fees' => 450,
                'StartDate' => now()->addDays(20),
                'Status' => 'Active',
            ],
            [
                'CourseTitle' => 'دورة إدارة المشاريع PMP',
                'Topics' => 'إدارة المشاريع, Agile, Scrum, التخطيط, المتابعة',
                'Duration' => '1 شهر',
                'Location' => 'تعز - حضوري',
                'Trainer' => 'د. محمد الأمين',
                'Fees' => 350,
                'StartDate' => now()->addDays(30),
                'Status' => 'Active',
            ],
        ];

        CourseAd::unguard();
        $courseAdId = 1;

        foreach ($courses as $course) {
            CourseAd::firstOrCreate(
                ['CompanyID' => $eduCompany->CompanyID, 'CourseTitle' => $course['CourseTitle']],
                array_merge(['CourseAdID' => $courseAdId++, 'CompanyID' => $eduCompany->CompanyID, 'CreatedAt' => now()], $course)
            );
        }
        CourseAd::reguard();
    }
}
