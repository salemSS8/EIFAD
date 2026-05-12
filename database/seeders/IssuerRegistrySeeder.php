<?php

namespace Database\Seeders;

use App\Domain\Certificate\Models\IssuerRegistry;
use Illuminate\Database\Seeder;

class IssuerRegistrySeeder extends Seeder
{
    /**
     * Seed the issuer registry with known certificate issuers.
     */
    public function run(): void
    {
        $issuers = [
            // Course Platforms
            ['IssuerName' => 'Coursera', 'IssuerDomain' => 'coursera.org', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => false, 'CredentialPattern' => null],
            ['IssuerName' => 'Udemy', 'IssuerDomain' => 'udemy.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => false, 'CredentialPattern' => null],
            ['IssuerName' => 'LinkedIn Learning', 'IssuerDomain' => 'linkedin.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => false, 'CredentialPattern' => null],
            ['IssuerName' => 'edX', 'IssuerDomain' => 'edx.org', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => false, 'CredentialPattern' => null],
            ['IssuerName' => 'Pluralsight', 'IssuerDomain' => 'pluralsight.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => false, 'CredentialPattern' => null],
            ['IssuerName' => 'Udacity', 'IssuerDomain' => 'udacity.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => false, 'CredentialPattern' => null],

            // Tech Companies
            ['IssuerName' => 'Google', 'IssuerDomain' => 'google.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
            ['IssuerName' => 'Microsoft', 'IssuerDomain' => 'microsoft.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
            ['IssuerName' => 'AWS', 'IssuerDomain' => 'aws.amazon.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
            ['IssuerName' => 'Oracle', 'IssuerDomain' => 'oracle.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
            ['IssuerName' => 'Cisco', 'IssuerDomain' => 'cisco.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
            ['IssuerName' => 'Salesforce', 'IssuerDomain' => 'salesforce.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
            ['IssuerName' => 'IBM', 'IssuerDomain' => 'ibm.com', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],

            // Professional Certification Bodies
            ['IssuerName' => 'PMI', 'IssuerDomain' => 'pmi.org', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
            ['IssuerName' => 'CompTIA', 'IssuerDomain' => 'comptia.org', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
            ['IssuerName' => 'ISACA', 'IssuerDomain' => 'isaca.org', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
            ['IssuerName' => 'Scrum.org', 'IssuerDomain' => 'scrum.org', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
            ['IssuerName' => 'CFA Institute', 'IssuerDomain' => 'cfainstitute.org', 'VerificationMethod' => 'manual', 'IsVerifiable' => true, 'RequiresHumanReview' => true, 'CredentialPattern' => null],
        ];

        foreach ($issuers as $issuer) {
            IssuerRegistry::firstOrCreate(
                ['IssuerName' => $issuer['IssuerName']],
                $issuer
            );
        }
    }
}
