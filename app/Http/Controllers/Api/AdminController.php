<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
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
        if (!$request->user()->roles()->where('RoleName', 'Admin')->exists()) {
            abort(403, 'Unauthorized access');
        }
    }

    /**
     * Get all users with pagination and filters.
     */
    #[OA\Get(
        path: "/admin/users",
        operationId: "getUsers",
        tags: ["Admin"],
        summary: "Get all users",
        description: "Returns a paginated list of users with optional filtering.",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "search", in: "query", description: "Search by name or email", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "role", in: "query", description: "Filter by role (JobSeeker, Employer, Admin)", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "status", in: "query", description: "Filter by status (active, blocked, unverified)", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "List of users")]
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

        // Filter by status
        if ($request->filled('status')) {
            if ($request->input('status') === 'blocked') {
                $query->where('IsBlocked', true);
            } elseif ($request->input('status') === 'active') {
                $query->where('IsBlocked', false)->where('IsVerified', true);
            } elseif ($request->input('status') === 'unverified') {
                $query->where('IsVerified', false);
            }
        }

        $users = $query->orderByDesc('CreatedAt')->paginate(20);

        return response()->json($users);
    }

    /**
     * Get a specific user details.
     */
    #[OA\Get(
        path: "/admin/users/{id}",
        operationId: "getUserDetails",
        tags: ["Admin"],
        summary: "Get user details",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "User details")]
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
        path: "/admin/users",
        operationId: "createUser",
        tags: ["Admin"],
        summary: "Create a new user",
        security: [["bearerAuth" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["full_name", "email", "password", "role"],
            properties: [
                new OA\Property(property: "full_name", type: "string"),
                new OA\Property(property: "email", type: "string"),
                new OA\Property(property: "password", type: "string"),
                new OA\Property(property: "role", type: "string", enum: ["JobSeeker", "Employer", "Admin"]),
                new OA\Property(property: "phone", type: "string"),
            ]
        )
    )]
    #[OA\Response(response: 201, description: "User created successfully")]
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
        path: "/admin/users/{id}",
        operationId: "updateUser",
        tags: ["Admin"],
        summary: "Update user details",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "full_name", type: "string"),
                new OA\Property(property: "email", type: "string"),
                new OA\Property(property: "phone", type: "string"),
                new OA\Property(property: "is_verified", type: "boolean"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "User updated successfully")]
    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);
        $user = User::where('UserID', $id)->firstOrFail();

        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:user,Email,' . $id . ',UserID',
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

        return response()->json([
            'message' => 'تم تحديث بيانات المستخدم بنجاح',
            'data' => $user->fresh()->load('roles'),
        ]);
    }

    /**
     * Block a user.
     */
    #[OA\Post(
        path: "/admin/users/{id}/block",
        operationId: "blockUser",
        tags: ["Admin"],
        summary: "Block a user",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["reason"],
            properties: [
                new OA\Property(property: "reason", type: "string"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "User blocked successfully")]
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
     * Unblock a user.
     */
    #[OA\Post(
        path: "/admin/users/{id}/unblock",
        operationId: "unblockUser",
        tags: ["Admin"],
        summary: "Unblock a user",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "User unblocked successfully")]
    public function unblock(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);
        $user = User::where('UserID', $id)->firstOrFail();

        if (!$user->IsBlocked) {
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
     * Get user statistics.
     */
    #[OA\Get(
        path: "/admin/statistics",
        operationId: "getStatistics",
        tags: ["Admin"],
        summary: "Get system statistics",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "System statistics")]
    public function statistics(Request $request): JsonResponse
    {
        $this->ensureIsAdmin($request);
        $stats = [
            'total_users' => User::count(),
            'job_seekers' => User::whereHas('roles', fn($q) => $q->where('RoleName', 'JobSeeker'))->count(),
            'employers' => User::whereHas('roles', fn($q) => $q->where('RoleName', 'Employer'))->count(),
            'verified_users' => User::where('IsVerified', true)->count(),
            'blocked_users' => User::where('IsBlocked', true)->count(),
            'new_users_today' => User::whereDate('CreatedAt', today())->count(),
            'new_users_this_week' => User::whereBetween('CreatedAt', [now()->startOfWeek(), now()])->count(),
        ];

        return response()->json(['data' => $stats]);
    }
}
