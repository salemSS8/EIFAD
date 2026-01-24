<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\User\Models\JobSeekerProfile;
use App\Domain\Company\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profile Controller - Manages user profiles (JobSeeker or Company).
 */
class ProfileController extends Controller
{
    /**
     * Get current user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->roles->first();

        if ($role?->RoleName === 'JobSeeker') {
            $profile = $user->jobSeekerProfile;
            return response()->json([
                'type' => 'job_seeker',
                'data' => $profile,
            ]);
        }

        if ($role?->RoleName === 'Employer') {
            $profile = $user->companyProfile;
            return response()->json([
                'type' => 'company',
                'data' => $profile?->load('specializations'),
            ]);
        }

        return response()->json(['message' => 'Profile not found'], 404);
    }

    /**
     * Create or update profile.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->roles->first();

        if ($role?->RoleName === 'JobSeeker') {
            return $this->updateJobSeekerProfile($request, $user);
        }

        if ($role?->RoleName === 'Employer') {
            return $this->updateCompanyProfile($request, $user);
        }

        return response()->json(['message' => 'Unknown role'], 400);
    }

    /**
     * Alias for store (both create and update use same logic).
     */
    public function update(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Update job seeker profile.
     */
    private function updateJobSeekerProfile(Request $request, $user): JsonResponse
    {
        $request->validate([
            'personal_photo' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'profile_summary' => 'nullable|string',
        ]);

        $profile = JobSeekerProfile::updateOrCreate(
            ['JobSeekerID' => $user->UserID],
            [
                'PersonalPhoto' => $request->input('personal_photo'),
                'Location' => $request->input('location'),
                'ProfileSummary' => $request->input('profile_summary'),
            ]
        );

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $profile,
        ]);
    }

    /**
     * Update company profile.
     */
    private function updateCompanyProfile(Request $request, $user): JsonResponse
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'organization_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'logo_path' => 'nullable|string|max:255',
            'website_url' => 'nullable|url|max:255',
            'established_year' => 'nullable|integer|min:1800|max:2100',
            'employee_count' => 'nullable|integer|min:1',
            'field_of_work' => 'nullable|string|max:255',
        ]);

        $profile = CompanyProfile::updateOrCreate(
            ['CompanyID' => $user->UserID],
            [
                'CompanyName' => $request->input('company_name'),
                'OrganizationName' => $request->input('organization_name'),
                'Address' => $request->input('address'),
                'Description' => $request->input('description'),
                'LogoPath' => $request->input('logo_path'),
                'WebsiteURL' => $request->input('website_url'),
                'EstablishedYear' => $request->input('established_year'),
                'EmployeeCount' => $request->input('employee_count'),
                'FieldOfWork' => $request->input('field_of_work'),
            ]
        );

        return response()->json([
            'message' => 'Company profile updated successfully',
            'data' => $profile,
        ]);
    }

    /**
     * Delete current user's profile.
     * User Story: Create & Update & Delete Profile
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->roles->first();

        if ($role?->RoleName === 'JobSeeker') {
            $profile = $user->jobSeekerProfile;
            if (!$profile) {
                return response()->json([
                    'message' => 'الملف الشخصي غير موجود',
                ], 404);
            }

            $profile->delete();

            return response()->json([
                'message' => 'تم حذف الملف الشخصي بنجاح',
            ]);
        }

        if ($role?->RoleName === 'Employer') {
            $profile = $user->companyProfile;
            if (!$profile) {
                return response()->json([
                    'message' => 'ملف الشركة غير موجود',
                ], 404);
            }

            // Check if company has active job ads
            if ($profile->jobAds()->where('Status', 'Active')->exists()) {
                return response()->json([
                    'message' => 'لا يمكن حذف ملف الشركة وهناك إعلانات وظائف نشطة، يرجى إغلاقها أولاً',
                ], 422);
            }

            $profile->delete();

            return response()->json([
                'message' => 'تم حذف ملف الشركة بنجاح',
            ]);
        }

        return response()->json([
            'message' => 'نوع الحساب غير معروف',
        ], 400);
    }
}
