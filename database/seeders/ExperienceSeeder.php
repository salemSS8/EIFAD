<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\Experience;

class ExperienceSeeder extends Seeder
{
    public function run(): void
    {
        $cvs = CV::all();

        $allExperiences = [
            // For first CV (Web Developer)
            [
                ['JobTitle' => 'مطور ويب', 'CompanyName' => 'شركة البرمجيات المتقدمة', 'StartDate' => '2021-01-01', 'EndDate' => '2023-06-30', 'Responsibilities' => 'تطوير تطبيقات ويب باستخدام Laravel و Vue.js. إدارة قواعد البيانات وتحسين الأداء.'],
                ['JobTitle' => 'مطور مبتدئ', 'CompanyName' => 'وكالة الويب الإبداعية', 'StartDate' => '2019-06-01', 'EndDate' => '2020-12-31', 'Responsibilities' => 'تطوير مواقع ويب ثابتة. المشاركة في تطوير تطبيقات PHP.'],
            ],
            // For second CV (Designer)
            [
                ['JobTitle' => 'مصممة جرافيك', 'CompanyName' => 'استوديو الإبداع', 'StartDate' => '2020-03-01', 'EndDate' => null, 'Responsibilities' => 'تصميم الهوية البصرية للشركات. تصميم المواد التسويقية والإعلانات.'],
                ['JobTitle' => 'مصممة مساعدة', 'CompanyName' => 'وكالة الإعلان العربية', 'StartDate' => '2018-01-01', 'EndDate' => '2020-02-28', 'Responsibilities' => 'المساعدة في تصميم الحملات الإعلانية. تصميم منشورات السوشيال ميديا.'],
            ],
            // For third CV (Accountant)
            [
                ['JobTitle' => 'محاسب أول', 'CompanyName' => 'مجموعة الخليج التجارية', 'StartDate' => '2019-01-01', 'EndDate' => null, 'Responsibilities' => 'إعداد القوائم المالية الشهرية والسنوية. إدارة الحسابات الدائنة والمدينة.'],
                ['JobTitle' => 'محاسب', 'CompanyName' => 'شركة الأمانة للتجارة', 'StartDate' => '2017-06-01', 'EndDate' => '2018-12-31', 'Responsibilities' => 'مسك الدفاتر المحاسبية. إعداد كشوف الرواتب.'],
            ],
            // For fourth CV (Marketing)
            [
                ['JobTitle' => 'أخصائية تسويق رقمي', 'CompanyName' => 'شركة التسويق الذكي', 'StartDate' => '2021-06-01', 'EndDate' => null, 'Responsibilities' => 'إدارة حملات إعلانات Google و Facebook. تحليل بيانات التسويق وإعداد التقارير.'],
            ],
            // For fifth CV (Mobile Developer)
            [
                ['JobTitle' => 'مطور تطبيقات موبايل', 'CompanyName' => 'شركة التطبيقات المبتكرة', 'StartDate' => '2020-01-01', 'EndDate' => null, 'Responsibilities' => 'تطوير تطبيقات Android و iOS باستخدام Flutter. التكامل مع APIs الخارجية.'],
                ['JobTitle' => 'مطور Android', 'CompanyName' => 'ستارت أب تقنية', 'StartDate' => '2018-03-01', 'EndDate' => '2019-12-31', 'Responsibilities' => 'تطوير تطبيقات Android أصلية. اختبار وتحسين أداء التطبيقات.'],
            ],
        ];

        Experience::unguard();
        $expId = 1;

        foreach ($cvs as $index => $cv) {
            if (isset($allExperiences[$index])) {
                foreach ($allExperiences[$index] as $exp) {
                    Experience::firstOrCreate(
                        ['CVID' => $cv->CVID, 'JobTitle' => $exp['JobTitle'], 'CompanyName' => $exp['CompanyName']],
                        array_merge(['ExperienceID' => $expId++, 'CVID' => $cv->CVID], $exp)
                    );
                }
            }
        }
        Experience::reguard();
    }
}
