<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Company\Models\CompanySpecialization;

class CompanySpecializationSeeder extends Seeder
{
    public function run(): void
    {
        $specializations = [
            ['SpecName' => 'تكنولوجيا المعلومات'],
            ['SpecName' => 'الاتصالات'],
            ['SpecName' => 'الصحة والرعاية الطبية'],
            ['SpecName' => 'التعليم والتدريب'],
            ['SpecName' => 'البناء والتشييد'],
            ['SpecName' => 'التجارة والاستيراد'],
            ['SpecName' => 'الصناعة'],
            ['SpecName' => 'الخدمات المالية والمصرفية'],
            ['SpecName' => 'السياحة والفنادق'],
            ['SpecName' => 'النفط والغاز'],
        ];

        foreach ($specializations as $spec) {
            CompanySpecialization::firstOrCreate(['SpecName' => $spec['SpecName']], $spec);
        }
    }
}
