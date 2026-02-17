<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVLanguage;
use App\Domain\Skill\Models\Language;

class CVLanguageSeeder extends Seeder
{
    public function run(): void
    {
        $cvs = CV::all();
        $arabic = Language::where('LanguageName', 'العربية')->first();
        $english = Language::where('LanguageName', 'الإنجليزية')->first();

        CVLanguage::unguard();
        $cvLangId = 1;

        foreach ($cvs as $cv) {
            // All have Arabic as native
            if ($arabic) {
                CVLanguage::firstOrCreate(
                    ['CVID' => $cv->CVID, 'LanguageID' => $arabic->LanguageID],
                    ['CVLanguageID' => $cvLangId++, 'CVID' => $cv->CVID, 'LanguageID' => $arabic->LanguageID, 'LanguageLevel' => 'اللغة الأم']
                );
            }

            // All have English at varying levels
            if ($english) {
                $levels = ['متوسط', 'متقدم', 'متوسط', 'متقدم', 'متقدم'];
                CVLanguage::firstOrCreate(
                    ['CVID' => $cv->CVID, 'LanguageID' => $english->LanguageID],
                    ['CVLanguageID' => $cvLangId++, 'CVID' => $cv->CVID, 'LanguageID' => $english->LanguageID, 'LanguageLevel' => $levels[min($cv->CVID - 1, 4)]]
                );
            }
        }
        CVLanguage::reguard();
    }
}
