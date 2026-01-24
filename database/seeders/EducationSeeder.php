<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\Education;

class EducationSeeder extends Seeder
{
    public function run(): void
    {
        $cvs = CV::all();

        $educations = [
            ['Institution' => 'جامعة صنعاء', 'DegreeName' => 'بكالوريوس', 'Major' => 'هندسة البرمجيات', 'GraduationYear' => 2020],
            ['Institution' => 'جامعة عدن', 'DegreeName' => 'بكالوريوس', 'Major' => 'التصميم الجرافيكي', 'GraduationYear' => 2019],
            ['Institution' => 'جامعة تعز', 'DegreeName' => 'بكالوريوس', 'Major' => 'المحاسبة', 'GraduationYear' => 2018],
            ['Institution' => 'جامعة صنعاء', 'DegreeName' => 'بكالوريوس', 'Major' => 'التسويق', 'GraduationYear' => 2021],
            ['Institution' => 'جامعة العلوم والتكنولوجيا', 'DegreeName' => 'بكالوريوس', 'Major' => 'علوم الحاسوب', 'GraduationYear' => 2019],
        ];

        foreach ($cvs as $index => $cv) {
            if (isset($educations[$index])) {
                Education::firstOrCreate(
                    ['CVID' => $cv->CVID, 'Institution' => $educations[$index]['Institution']],
                    array_merge(['CVID' => $cv->CVID], $educations[$index])
                );
            }
        }

        // Add master's degree for some
        $firstCV = CV::first();
        if ($firstCV) {
            Education::firstOrCreate(
                ['CVID' => $firstCV->CVID, 'DegreeName' => 'ماجستير'],
                [
                    'CVID' => $firstCV->CVID,
                    'Institution' => 'جامعة الملك سعود',
                    'DegreeName' => 'ماجستير',
                    'Major' => 'هندسة البرمجيات',
                    'GraduationYear' => 2022,
                ]
            );
        }
    }
}
