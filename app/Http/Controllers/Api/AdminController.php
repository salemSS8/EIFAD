<?php

namespace App\Http\Controllers\Api;

use App\Domain\Application\Models\JobApplication;
use App\Domain\Communication\Models\Notification;
use App\Domain\Company\Models\CompanyProfile;
use App\Domain\CV\Models\CVCertification;
use App\Domain\Job\Models\JobAd;
use App\Domain\User\Models\JobSeekerProfile;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
/**
 * Admin Controller - Manages users for system administrators.
 * User Story: Add & Update & Block User for Manager
 */

use OpenApi\Attributes as OA;

/**
 * Admin Controller - Manages users for system administrators.
 * User Story: Add & Update & Block User for Manager
 */
class AdminController extends Controller
{
    /**
     * Ensure user is Admin.
     */
    private function ensureIsAdmin(Request $request)
    {
        if (! $request->user()->roles()->where('RoleName', 'Admin')->exists()) {
            abort(403, 'Unauthorized access');
        }
    }

    /**
     * Get all users with pagination and filters.
     */
    #[OA\Get(
        path: '/admin/users',
        operationId: 'getUsers',
        tags: ['Admin'],
        summary: 'Get all users',
        description: 'Returns a paginated list of users with optional filtering.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Search by name or email', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'role', in: 'query', description: 'Filter by role (JobSeeker, Employer, Admin)', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'account_status', in: 'query', description: 'Filter by account status (active, blocked, inactive)', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'blocked', 'inactive']))]
    #[OA\Parameter(name: 'user_status', in: 'query', description: 'Filter by user status (trusted, nottrusted, pending)', required: false, schema: new OA\Schema(type: 'string', enum: ['trusted', 'nottrusted', 'pending']))]
    #[OA\Response(response: 200, description: 'List of users')]
    public function index(Request $request): JsonResponse
    {
        $this->ensureIsAdmin($request);
        $query = User::with('roles');

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('FullName', 'like', "%{$search}%")
                    ->orWhere('Email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('RoleName', $request->input('role'));
            });
        }

        // Filter by Account Status (active, blocked, inactive)
        if ($request->filled('account_status')) {
            $status = $request->input('account_status');
            if ($status === 'blocked') {
                $query->where('IsBlocked', true);
            } elseif ($status === 'active') {
                $query->where('IsBlocked', false)->where('IsVerified', true);
            } elseif ($status === 'inactive') {
                $query->where('IsBlocked', false)->where('IsVerified', false);
            }
        }

        // Filter by User Status (trusted, nottrusted)
        // This includes VerificationStatus for companies and Status for job seekers
        if ($request->filled('user_status')) {
            $status = $request->input('user_status');
            if ($status === 'trusted') {
                $query->where(function ($q) {
                    $q->whereHas('companyProfile', function ($q2) {
                        $q2->where('VerificationStatus', 'Verified');
                    })->orWhereHas('jobSeekerProfile', function ($q2) {
                        $q2->where('Status', 'trusted');
                    });
                });
            } elseif ($status === 'nottrusted') {
                $query->where(function ($q) {
                    $q->whereHas('companyProfile', function ($q2) {
                        $q2->where('VerificationStatus', 'Pending');
                    })->orWhereHas('jobSeekerProfile', function ($q2) {
                        $q2->where('Status', 'notrusted');
                    });
                });
            } elseif ($status === 'pending') {
                $query->where(function ($q) {
                    $q->whereHas('companyProfile', function ($q2) {
                        $q2->where('VerificationStatus', 'Pending');
                    });
                });
            }
        }

        // Filter by specific verification status (verified, pending, rejected)
        // if ($request->filled('verification_status')) {
        //     $vStatus = $request->input('verification_status');
        //     if ($vStatus === 'verified') {
        //         $query->whereHas('companyProfile', function ($q) {
        //             $q->where('VerificationStatus', 'Verified');
        //         });
        //     } elseif ($vStatus === 'pending') {
        //         $query->whereHas('companyProfile', function ($q) {
        //             $q->where('VerificationStatus', 'Pending');
        //         });
        //     } elseif ($vStatus === 'rejected') {
        //         $query->whereHas('companyProfile', function ($q) {
        //             $q->where('VerificationStatus', 'Rejected');
        //         });
        //     }
        // }

        $users = $query->with('companyProfile','jobSeekerProfile')->orderByDesc('CreatedAt')->paginate(20);

        return response()->json($users);
    }

    /**
     * Get a specific user details.
     */
    #[OA\Get(
        path: '/admin/users/{id}',
        operationId: 'getUserDetails',
        tags: ['Admin'],
        summary: 'Get user details',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'User details')]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);
        $user = User::with(['roles', 'jobSeekerProfile', 'companyProfile'])
            ->where('UserID', $id)
            ->firstOrFail();

        return response()->json(['data' => $user]);
    }

    /**
     * Create a new user (by admin).
     */
    #[OA\Post(
        path: '/admin/users',
        operationId: 'createUser',
        tags: ['Admin'],
        summary: 'Create a new user',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['full_name', 'email', 'password', 'role'],
            properties: [
                new OA\Property(property: 'full_name', type: 'string'),
                new OA\Property(property: 'email', type: 'string'),
                new OA\Property(property: 'password', type: 'string'),
                new OA\Property(property: 'role', type: 'string', enum: ['JobSeeker', 'Employer', 'Admin']),
                new OA\Property(property: 'phone', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'User created successfully')]
    public function store(Request $request): JsonResponse
    {
        $this->ensureIsAdmin($request);
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:user,Email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:JobSeeker,Employer,Admin',
            'phone' => 'nullable|string|max:20',
        ], [
            'full_name.required' => 'الاسم الكامل مطلوب',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.unique' => 'البريد الإلكتروني مستخدم مسبقاً',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'role.required' => 'نوع الحساب مطلوب',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::create([
                'FullName' => $request->input('full_name'),
                'Email' => $request->input('email'),
                'PasswordHash' => Hash::make($request->input('password')),
                'Phone' => $request->input('phone'),
                'IsVerified' => true, // Admin-created users are verified
                'IsBlocked' => false,
                'CreatedAt' => now(),
            ]);

            // Assign role
            $role = Role::where('RoleName', $request->input('role'))->first();
            if ($role) {
                DB::table('userrole')->insert([
                    'UserID' => $user->UserID,
                    'RoleID' => $role->RoleID,
                    'AssignedAt' => now(),
                ]);
            }

            // Create profile based on role
            $roleName = $request->input('role');
            if ($roleName === 'JobSeeker') {
                DB::table('jobseekerprofile')->insert([
                    'JobSeekerID' => $user->UserID,
                ]);
            } elseif ($roleName === 'Employer') {
                DB::table('companyprofile')->insert([
                    'CompanyID' => $user->UserID,
                ]);
            } elseif ($roleName === 'Admin') {
                DB::table('adminprofile')->insert([
                    'AdminID' => $user->UserID,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'message' => 'تم إنشاء المستخدم بنجاح',
                'data' => $user->load('roles'),
            ], 201);
        });
    }

    /**
     * Update user information.
     */
    #[OA\Put(
        path: '/admin/users/{id}',
        operationId: 'updateUser',
        tags: ['Admin'],
        summary: 'Update user details',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'full_name', type: 'string'),
                new OA\Property(property: 'email', type: 'string'),
                new OA\Property(property: 'phone', type: 'string'),
                new OA\Property(property: 'is_verified', type: 'boolean'),
                new OA\Property(property: 'status', type: 'string', enum: ['trusted', 'notrusted'], description: 'Only for JobSeekers'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'User updated successfully')]
    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);
        $user = User::where('UserID', $id)->firstOrFail();

        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:user,Email,'.$id.',UserID',
            'phone' => 'nullable|string|max:20',
            'is_verified' => 'sometimes|boolean',
        ]);

        $user->update($request->only([
            'full_name' => 'FullName',
            'email' => 'Email',
            'phone' => 'Phone',
        ]));

        if ($request->has('full_name')) {
            $user->FullName = $request->input('full_name');
        }
        if ($request->has('email')) {
            $user->Email = $request->input('email');
        }
        if ($request->has('phone')) {
            $user->Phone = $request->input('phone');
        }
        if ($request->has('is_verified')) {
            $user->IsVerified = $request->input('is_verified');
        }

        $user->save();

        // Update JobSeeker status if provided and user is JobSeeker
        if ($request->has('status') && $user->hasRole('JobSeeker')) {
            $newStatus = $request->input('status');
            $user->jobSeekerProfile()->update(['Status' => $newStatus]);

            // Notify user if status changed to trusted
            if ($newStatus === 'trusted') {
                Notification::create([
                    'UserID' => $user->UserID,
                    'Type' => 'account_update',
                    'Content' => '🎉 تهانينا! تم توثيق حسابك كباحث عن عمل موثوق (Trusted). سيظهر ملفك بشكل أفضل لأصحاب العمل الآن.',
                    'IsRead' => false,
                    'CreatedAt' => now(),
                ]);
            }
        }

        return response()->json([
            'message' => 'تم تحديث بيانات المستخدم بنجاح',
            'data' => $user->fresh()->load(['roles', 'jobSeekerProfile', 'companyProfile']),
        ]);
    }

    /**
     * Block a user.
     */
    #[OA\Post(
        path: '/admin/users/{id}/block',
        operationId: 'blockUser',
        tags: ['Admin'],
        summary: 'Block a user',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['reason'],
            properties: [
                new OA\Property(property: 'reason', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'User blocked successfully')]
    public function block(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);
        $user = User::where('UserID', $id)->firstOrFail();

        $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'سبب الحظر مطلوب',
        ]);

        // Prevent blocking yourself
        if ($user->UserID === $request->user()->UserID) {
            return response()->json([
                'message' => 'لا يمكنك حظر نفسك',
            ], 422);
        }

        $user->update([
            'IsBlocked' => true,
            'BlockedAt' => now(),
            'BlockReason' => $request->input('reason'),
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'تم حظر المستخدم بنجاح',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Verify or Unverify a Job Seeker.
     */
    #[OA\Post(
        path: '/admin/users/{id}/verify-jobseeker',
        operationId: 'verifyJobSeeker',
        tags: ['Admin'],
        summary: 'Verify or Unverify a Job Seeker',
        description: 'Toggles the trusted status of a job seeker profile.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['trusted', 'notrusted']),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Status updated successfully')]
    public function verifyJobSeeker(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);
        $user = User::where('UserID', $id)->firstOrFail();

        if (! $user->hasRole('JobSeeker')) {
            return response()->json(['message' => 'المستخدم ليس باحث عن عمل'], 422);
        }

        $request->validate(['status' => 'required|in:trusted,notrusted']);

        $newStatus = $request->input('status');
        $user->jobSeekerProfile()->update(['Status' => $newStatus]);

        // Notify user
        $message = $newStatus === 'trusted'
            ? '🎉 تهانينا! تم توثيق حسابك كباحث عن عمل موثوق (Trusted). سيظهر ملفك بشكل أفضل لأصحاب العمل الآن.'
            : 'تم تحديث حالة ملفك الشخصي من قبل الإدارة.';

        Notification::create([
            'UserID' => $user->UserID,
            'Type' => 'account_update',
            'Content' => $message,
            'IsRead' => false,
            'CreatedAt' => now(),
        ]);

        return response()->json([
            'message' => 'تم تحديث حالة الباحث عن عمل بنجاح',
            'data' => $user->fresh()->load('jobSeekerProfile'),
        ]);
    }

    /**
     * Unblock a user.
     */
    #[OA\Post(
        path: '/admin/users/{id}/unblock',
        operationId: 'unblockUser',
        tags: ['Admin'],
        summary: 'Unblock a user',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'User unblocked successfully')]
    public function unblock(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);
        $user = User::where('UserID', $id)->firstOrFail();

        if (! $user->IsBlocked) {
            return response()->json([
                'message' => 'المستخدم غير محظور',
            ], 422);
        }

        $user->update([
            'IsBlocked' => false,
            'BlockedAt' => null,
            'BlockReason' => null,
        ]);

        return response()->json([
            'message' => 'تم إلغاء حظر المستخدم بنجاح',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Get admin dashboard statistics.
     */
    #[OA\Get(
        path: '/admin/users/statistics',
        operationId: 'getStatistics',
        tags: ['Admin'],
        summary: 'Get admin dashboard statistics',
        description: 'Returns total job seekers, companies, active job ads, applications with monthly growth percentages, plus pending verifications, certificate reviews, and AI alert counts.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Dashboard statistics',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'total_job_seekers', type: 'object', properties: [
                        new OA\Property(property: 'count', type: 'integer', example: 12458),
                        new OA\Property(property: 'growth_percentage', type: 'number', example: 12),
                    ]),
                    new OA\Property(property: 'total_companies', type: 'object', properties: [
                        new OA\Property(property: 'count', type: 'integer', example: 3247),
                        new OA\Property(property: 'growth_percentage', type: 'number', example: 8),
                    ]),
                    new OA\Property(property: 'active_job_ads', type: 'object', properties: [
                        new OA\Property(property: 'count', type: 'integer', example: 1892),
                        new OA\Property(property: 'growth_percentage', type: 'number', example: 15),
                    ]),
                    new OA\Property(property: 'total_applications', type: 'object', properties: [
                        new OA\Property(property: 'count', type: 'integer', example: 28456),
                        new OA\Property(property: 'growth_percentage', type: 'number', example: 23),
                    ]),
                    new OA\Property(property: 'pending_company_verifications', type: 'integer', example: 24),
                    new OA\Property(property: 'trusted_job_seekers', type: 'integer', example: 8500),
                    new OA\Property(property: 'pending_certificate_reviews', type: 'integer', example: 18),
                    new OA\Property(property: 'ai_alerts_count', type: 'integer', example: 7),
                ]),
            ]
        )
    )]
    public function statistics(Request $request): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $currentMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        // Row 1: Total Job Seekers (with growth)
        $totalJobSeekers = User::whereHas('roles', fn ($q) => $q->where('RoleName', 'JobSeeker'))->count();
        $jobSeekersThisMonth = User::whereHas('roles', fn ($q) => $q->where('RoleName', 'JobSeeker'))
            ->where('CreatedAt', '>=', $currentMonthStart)->count();
        $jobSeekersLastMonth = User::whereHas('roles', fn ($q) => $q->where('RoleName', 'JobSeeker'))
            ->whereBetween('CreatedAt', [$lastMonthStart, $lastMonthEnd])->count();

        // Row 1: Total Companies (with growth)
        $totalCompanies = CompanyProfile::count();
        $companiesThisMonth = CompanyProfile::whereHas('user', fn ($q) => $q->where('CreatedAt', '>=', $currentMonthStart))->count();
        $companiesLastMonth = CompanyProfile::whereHas('user', fn ($q) => $q->whereBetween('CreatedAt', [$lastMonthStart, $lastMonthEnd]))->count();

        // Row 1: Active Job Ads (with growth)
        $activeJobAds = JobAd::where('Status', 'Active')->count();
        $jobAdsThisMonth = JobAd::where('PostedAt', '>=', $currentMonthStart)->count();
        $jobAdsLastMonth = JobAd::whereBetween('PostedAt', [$lastMonthStart, $lastMonthEnd])->count();

        // Row 1: Total Applications (with growth)
        $totalApplications = JobApplication::count();
        $applicationsThisMonth = JobApplication::where('AppliedAt', '>=', $currentMonthStart)->count();
        $applicationsLastMonth = JobApplication::whereBetween('AppliedAt', [$lastMonthStart, $lastMonthEnd])->count();

        // Row 2: Pending actions and Trust levels
        $pendingVerifications = CompanyProfile::where('VerificationStatus', 'Pending')->count();
        $trustedJobSeekers = JobSeekerProfile::where('Status', 'trusted')->count();
        $pendingCertificates = CVCertification::whereIn('VerificationStatus', ['pending', 'ai_reviewed'])->count();

        // Row 2: AI Alerts — unread notifications of type 'ai_alert' sent to admin users
        $adminUserIds = User::whereHas('roles', fn ($q) => $q->where('RoleName', 'Admin'))->pluck('UserID');
        $aiAlertsCount = Notification::whereIn('UserID', $adminUserIds)
            ->where('Type', 'ai_alert')
            ->where('IsRead', false)
            ->count();

        return response()->json(['data' => [
            'total_job_seekers' => [
                'count' => $totalJobSeekers,
                'growth_percentage' => $this->calculateGrowthPercentage($jobSeekersThisMonth, $jobSeekersLastMonth),
            ],
            'total_companies' => [
                'count' => $totalCompanies,
                'growth_percentage' => $this->calculateGrowthPercentage($companiesThisMonth, $companiesLastMonth),
            ],
            'active_job_ads' => [
                'count' => $activeJobAds,
                'growth_percentage' => $this->calculateGrowthPercentage($jobAdsThisMonth, $jobAdsLastMonth),
            ],
            'total_applications' => [
                'count' => $totalApplications,
                'growth_percentage' => $this->calculateGrowthPercentage($applicationsThisMonth, $applicationsLastMonth),
            ],
            'pending_company_verifications' => $pendingVerifications,
            'trusted_job_seekers' => $trustedJobSeekers,
            'pending_certificate_reviews' => $pendingCertificates,
            'ai_alerts_count' => $aiAlertsCount,
        ]]);
    }

    /**
     * Calculate growth percentage between current and previous period.
     */
    private function calculateGrowthPercentage(int $currentCount, int $previousCount): float
    {
        if ($previousCount === 0) {
            return $currentCount > 0 ? 100.0 : 0.0;
        }

        return round((($currentCount - $previousCount) / $previousCount) * 100, 1);
    }
}
