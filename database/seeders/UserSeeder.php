<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            // Admin
            [
                'FullName' => 'مدير النظام',
                'Email' => 'admin@example.com',
                'PasswordHash' => Hash::make('admin123'),
                'Phone' => '777000001',
                'Gender' => 'Male',
                'DateOfBirth' => '1985-01-15',
                'IsVerified' => true,
                'CreatedAt' => now(),
            ],
            // Employers
            [
                'FullName' => 'أحمد محمد الشركة',
                'Email' => 'ahmed@techcompany.com',
                'PasswordHash' => Hash::make('password123'),
                'Phone' => '777000002',
                'Gender' => 'Male',
                'DateOfBirth' => '1980-05-20',
                'IsVerified' => true,
                'CreatedAt' => now(),
            ],
            [
                'FullName' => 'سارة علي المنصور',
                'Email' => 'sara@healthco.com',
                'PasswordHash' => Hash::make('password123'),
                'Phone' => '777000003',
                'Gender' => 'Female',
                'DateOfBirth' => '1982-08-10',
                'IsVerified' => true,
                'CreatedAt' => now(),
            ],
            [
                'FullName' => 'خالد عبدالله',
                'Email' => 'khaled@edutech.com',
                'PasswordHash' => Hash::make('password123'),
                'Phone' => '777000004',
                'Gender' => 'Male',
                'DateOfBirth' => '1978-03-25',
                'IsVerified' => true,
                'CreatedAt' => now(),
            ],
            // Job Seekers
            [
                'FullName' => 'محمد علي السعيد',
                'Email' => 'mohammed.ali@email.com',
                'PasswordHash' => Hash::make('password123'),
                'Phone' => '777100001',
                'Gender' => 'Male',
                'DateOfBirth' => '1995-06-15',
                'IsVerified' => true,
                'CreatedAt' => now(),
            ],
            [
                'FullName' => 'فاطمة أحمد الحسني',
                'Email' => 'fatima.ahmed@email.com',
                'PasswordHash' => Hash::make('password123'),
                'Phone' => '777100002',
                'Gender' => 'Female',
                'DateOfBirth' => '1998-02-20',
                'IsVerified' => true,
                'CreatedAt' => now(),
            ],
            [
                'FullName' => 'عمر سالم العمري',
                'Email' => 'omar.salem@email.com',
                'PasswordHash' => Hash::make('password123'),
                'Phone' => '777100003',
                'Gender' => 'Male',
                'DateOfBirth' => '1993-11-08',
                'IsVerified' => true,
                'CreatedAt' => now(),
            ],
            [
                'FullName' => 'نورة خالد الزهراني',
                'Email' => 'noura.khaled@email.com',
                'PasswordHash' => Hash::make('password123'),
                'Phone' => '777100004',
                'Gender' => 'Female',
                'DateOfBirth' => '1996-07-12',
                'IsVerified' => true,
                'CreatedAt' => now(),
            ],
            [
                'FullName' => 'ياسر عبدالرحمن',
                'Email' => 'yasser.abdulrahman@email.com',
                'PasswordHash' => Hash::make('password123'),
                'Phone' => '777100005',
                'Gender' => 'Male',
                'DateOfBirth' => '1994-04-18',
                'IsVerified' => true,
                'CreatedAt' => now(),
            ],
            [
                'FullName' => 'هدى محمد الصالح',
                'Email' => 'huda.mohammed@email.com',
                'PasswordHash' => Hash::make('password123'),
                'Phone' => '777100006',
                'Gender' => 'Female',
                'DateOfBirth' => '1997-09-25',
                'IsVerified' => false,
                'CreatedAt' => now(),
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(['Email' => $user['Email']], $user);
        }
    }
}
