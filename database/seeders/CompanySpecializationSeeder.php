<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Company\Models\CompanySpecialization;

class CompanySpecializationSeeder extends Seeder
{
    public function run(): void
    {
        $specializations = [
            ['SpecID' => 1, 'SpecName' => 'تكنولوجيا المعلومات'],
            ['SpecID' => 2, 'SpecName' => 'الاتصالات'],
            ['SpecID' => 3, 'SpecName' => 'الصحة والرعاية الطبية'],
            ['SpecID' => 4, 'SpecName' => 'التعليم والتدريب'],
            ['SpecID' => 5, 'SpecName' => 'البناء والتشييد'],
            ['SpecID' => 6, 'SpecName' => 'التجارة والاستيراد'],
            ['SpecID' => 7, 'SpecName' => 'الصناعة'],
            ['SpecID' => 8, 'SpecName' => 'الخدمات المالية والمصرفية'],
            ['SpecID' => 9, 'SpecName' => 'السياحة والفنادق'],
            ['SpecID' => 10, 'SpecName' => 'النفط والغاز'],
        ];

        CompanySpecialization::unguard();
        foreach ($specializations as $spec) {
            CompanySpecialization::firstOrCreate(['SpecName' => $spec['SpecName']], $spec);
        }
        CompanySpecialization::reguard();
    }
}
