<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\User;
use App\Domain\Company\Models\CompanyProfile;

class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'email' => 'ahmed@techcompany.com',
                'CompanyName' => 'شركة التقنية المتقدمة',
                'OrganizationName' => 'Advanced Tech Solutions',
                'Address' => 'شارع حدة، صنعاء، اليمن',
                'Description' => 'شركة رائدة في مجال تطوير البرمجيات وحلول تكنولوجيا المعلومات. نقدم خدمات تطوير المواقع والتطبيقات والاستشارات التقنية للشركات والمؤسسات.',
                'LogoPath' => 'logos/techcompany.png',
                'WebsiteURL' => 'https://techcompany.com.ye',
                'EstablishedYear' => 2015,
                'EmployeeCount' => 50,
                'FieldOfWork' => 'تكنولوجيا المعلومات',
                'IsCompanyVerified' => true,
            ],
            [
                'email' => 'sara@healthco.com',
                'CompanyName' => 'مجموعة الصحة المتكاملة',
                'OrganizationName' => 'Integrated Health Group',
                'Address' => 'شارع 45، عدن، اليمن',
                'Description' => 'مجموعة طبية متكاملة تقدم خدمات الرعاية الصحية عالية الجودة. نمتلك مراكز طبية متخصصة ونوظف أفضل الكوادر الطبية.',
                'LogoPath' => 'logos/healthco.png',
                'WebsiteURL' => 'https://healthco.com.ye',
                'EstablishedYear' => 2010,
                'EmployeeCount' => 200,
                'FieldOfWork' => 'الصحة والرعاية الطبية',
                'IsCompanyVerified' => true,
            ],
            [
                'email' => 'khaled@edutech.com',
                'CompanyName' => 'أكاديمية المستقبل للتدريب',
                'OrganizationName' => 'Future Academy',
                'Address' => 'شارع جمال، تعز، اليمن',
                'Description' => 'أكاديمية تدريب متخصصة في تطوير المهارات المهنية والتقنية. نقدم دورات معتمدة في البرمجة والتصميم والإدارة.',
                'LogoPath' => 'logos/edutech.png',
                'WebsiteURL' => 'https://edutech.com.ye',
                'EstablishedYear' => 2018,
                'EmployeeCount' => 30,
                'FieldOfWork' => 'التعليم والتدريب',
                'IsCompanyVerified' => true,
            ],
        ];

        foreach ($companies as $company) {
            $user = User::where('Email', $company['email'])->first();
            if ($user) {
                unset($company['email']);
                CompanyProfile::firstOrCreate(
                    ['CompanyID' => $user->UserID],
                    array_merge(['CompanyID' => $user->UserID], $company)
                );
            }
        }
    }
}
