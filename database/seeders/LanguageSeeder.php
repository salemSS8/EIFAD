<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Skill\Models\Language;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['LanguageID' => 1, 'LanguageName' => 'العربية'], // Arabic
            ['LanguageID' => 2, 'LanguageName' => 'الإنجليزية'], // English
            ['LanguageID' => 3, 'LanguageName' => 'الفرنسية'], // French
            ['LanguageID' => 4, 'LanguageName' => 'الألمانية'], // German
            ['LanguageID' => 5, 'LanguageName' => 'الإسبانية'], // Spanish
            ['LanguageID' => 6, 'LanguageName' => 'التركية'], // Turkish
            ['LanguageID' => 7, 'LanguageName' => 'الصينية'], // Chinese
            ['LanguageID' => 8, 'LanguageName' => 'اليابانية'], // Japanese
        ];

        Language::unguard();
        foreach ($languages as $language) {
            Language::firstOrCreate(
                ['LanguageName' => $language['LanguageName']],
                ['LanguageID' => $language['LanguageID']]
            );
        }
        Language::reguard();
    }
}
