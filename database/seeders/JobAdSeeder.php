<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Company\Models\CompanyProfile;
use App\Domain\Job\Models\JobAd;

class JobAdSeeder extends Seeder
{
    public function run(): void
    {
        $companies = CompanyProfile::all();

        $jobsByCompany = [
            // Tech Company Jobs
            [
                ['Title' => 'مطور Laravel أول', 'Description' => 'نبحث عن مطور Laravel ذو خبرة لتطوير وصيانة تطبيقات الويب.', 'Responsibilities' => ['تطوير APIs', 'إدارة قواعد البيانات', 'مراجعة الكود'], 'Requirements' => ['خبرة 3+ سنوات في Laravel', 'معرفة بـ Vue.js أو React'], 'Benefits' => ['راتب تنافسي', 'تأمين طبي', 'عمل عن بعد جزئياً'], 'Location' => 'صنعاء', 'WorkplaceType' => 'Hybrid', 'WorkType' => 'Full-time', 'SalaryMin' => 800, 'SalaryMax' => 1200, 'Currency' => 'USD', 'ExpiryDate' => now()->addDays(15)],
                ['Title' => 'مطور React Frontend', 'Description' => 'مطلوب مطور React لبناء واجهات مستخدم تفاعلية.', 'Responsibilities' => ['تطوير مكونات React', 'التكامل مع APIs', 'تحسين الأداء'], 'Requirements' => ['خبرة 2+ سنوات في React', 'معرفة TypeScript'], 'Benefits' => ['بيئة عمل مرنة', 'جهاز ماك'], 'Location' => 'صنعاء', 'WorkplaceType' => 'Remote', 'WorkType' => 'Full-time', 'SalaryMin' => 600, 'SalaryMax' => 900, 'Currency' => 'USD', 'ExpiryDate' => now()->addDays(30)],
                ['Title' => 'مهندس DevOps', 'Description' => 'مطلوب مهندس DevOps لإدارة البنية التحتية السحابية.', 'Responsibilities' => ['إدارة AWS', 'أتمتة النشر', 'مراقبة الأنظمة'], 'Requirements' => ['خبرة 2+ سنوات في DevOps', 'معرفة Docker و Kubernetes'], 'Benefits' => ['تدريب مستمر', 'مكافآت سنوية'], 'Location' => 'عن بعد', 'WorkplaceType' => 'Remote', 'WorkType' => 'Full-time', 'SalaryMin' => 1000, 'SalaryMax' => 1500, 'Currency' => 'USD', 'ExpiryDate' => now()->subDays(5)],
            ],
            // Health Company Jobs
            [
                ['Title' => 'أخصائي موارد بشرية', 'Description' => 'نبحث عن أخصائي HR لإدارة شؤون الموظفين.', 'Responsibilities' => ['التوظيف', 'إدارة الرواتب', 'تطوير السياسات'], 'Requirements' => ['شهادة في إدارة الموارد البشرية', 'خبرة 2+ سنوات'], 'Benefits' => ['تأمين صحي عائلي'], 'Location' => 'عدن', 'WorkplaceType' => 'On-site', 'WorkType' => 'Full-time', 'SalaryMin' => 400, 'SalaryMax' => 600, 'Currency' => 'USD', 'ExpiryDate' => now()->addDays(10)],
                ['Title' => 'محاسب مالي', 'Description' => 'مطلوب محاسب لإدارة الحسابات المالية للمجموعة.', 'Responsibilities' => ['إعداد القوائم المالية', 'إدارة الميزانيات', 'التقارير الشهرية'], 'Requirements' => ['بكالوريوس محاسبة', 'خبرة 3+ سنوات'], 'Benefits' => ['مكافأة أداء', 'بدل سكن'], 'Location' => 'عدن', 'WorkplaceType' => 'On-site', 'WorkType' => 'Full-time', 'SalaryMin' => 500, 'SalaryMax' => 700, 'Currency' => 'USD', 'ExpiryDate' => null],
                ['Title' => 'مسؤول تسويق', 'Description' => 'مطلوب مسؤول تسويق لتطوير الحملات التسويقية.', 'Responsibilities' => ['إدارة السوشيال ميديا', 'تطوير المحتوى', 'تحليل الأداء'], 'Requirements' => ['خبرة في التسويق الرقمي', 'مهارات تواصل ممتازة'], 'Benefits' => ['عمولات مبيعات'], 'Location' => 'عدن', 'WorkplaceType' => 'Hybrid', 'WorkType' => 'Full-time', 'SalaryMin' => 350, 'SalaryMax' => 500, 'Currency' => 'USD', 'ExpiryDate' => now()->addDays(20)],
            ],
            // Education Company Jobs
            [
                ['Title' => 'مدرب برمجة', 'Description' => 'نبحث عن مدرب لتقديم دورات البرمجة.', 'Responsibilities' => ['تقديم الدورات', 'إعداد المناهج', 'متابعة المتدربين'], 'Requirements' => ['خبرة في البرمجة والتدريب', 'مهارات تواصل'], 'Benefits' => ['بدل نقل', 'مكافآت بحسب الأداء'], 'Location' => 'تعز', 'WorkplaceType' => 'On-site', 'WorkType' => 'Part-time', 'SalaryMin' => 300, 'SalaryMax' => 500, 'Currency' => 'USD', 'ExpiryDate' => now()->addDays(7)],
                ['Title' => 'مصمم جرافيك', 'Description' => 'مطلوب مصمم لتصميم المواد التعليمية.', 'Responsibilities' => ['تصميم الكتيبات', 'إنشاء الإنفوجرافيك', 'تصميم السوشيال'], 'Requirements' => ['إتقان Adobe Creative Suite', 'خبرة 2+ سنوات'], 'Benefits' => ['دوام مرن'], 'Location' => 'تعز', 'WorkplaceType' => 'Hybrid', 'WorkType' => 'Full-time', 'SalaryMin' => 300, 'SalaryMax' => 450, 'Currency' => 'USD', 'ExpiryDate' => now()->subDays(2)],
                ['Title' => 'منسق إداري', 'Description' => 'مطلوب منسق لإدارة العمليات الإدارية.', 'Responsibilities' => ['تنسيق الجداول', 'التواصل مع العملاء', 'إعداد التقارير'], 'Requirements' => ['مهارات تنظيمية', 'إجادة الحاسوب'], 'Benefits' => ['تأمين طبي'], 'Location' => 'تعز', 'WorkplaceType' => 'On-site', 'WorkType' => 'Full-time', 'SalaryMin' => 250, 'SalaryMax' => 350, 'Currency' => 'USD', 'ExpiryDate' => now()->addDays(14)],
                ['Title' => 'أخصائي محتوى تعليمي', 'Description' => 'مطلوب أخصائي لتطوير المحتوى التعليمي الرقمي.', 'Responsibilities' => ['كتابة المحتوى', 'تطوير المناهج', 'إنتاج الفيديوهات'], 'Requirements' => ['خبرة في التعليم الإلكتروني', 'مهارات كتابة'], 'Benefits' => ['العمل عن بعد بالكامل'], 'Location' => 'تعز', 'WorkplaceType' => 'Remote', 'WorkType' => 'Full-time', 'SalaryMin' => 350, 'SalaryMax' => 500, 'Currency' => 'USD', 'ExpiryDate' => null],
            ],
        ];

        JobAd::unguard();
        $jobId = 1;

        foreach ($companies as $index => $company) {
            if (isset($jobsByCompany[$index])) {
                foreach ($jobsByCompany[$index] as $jobData) {
                    JobAd::firstOrCreate(
                        ['CompanyID' => $company->CompanyID, 'Title' => $jobData['Title']],
                        array_merge([
                            'JobAdID' => $jobId++,
                            'CompanyID' => $company->CompanyID,
                            'PostedAt' => now()->subDays(rand(1, 30)),
                            'Status' => 'Active',
                        ], $jobData)
                    );
                }
            }
        }
        JobAd::reguard();
    }
}
