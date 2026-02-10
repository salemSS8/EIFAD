<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Actions\LogoutAction;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;
use App\Mail\ResetPasswordCodeMail;
use OpenApi\Attributes as OA;

/**
 * Authentication Controller - Thin controller for auth endpoints.
 * Supports both Firebase auth and traditional email/password.
 */
class AuthController extends Controller
{
    #[OA\Get(
        path: "/health",
        operationId: "healthCheck",
        tags: ["Public"],
        summary: "Check API health",
        description: "Returns the health status of the API"
    )]
    #[OA\Response(
        response: 200,
        description: "Successful operation"
    )]
    public function healthCheck(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now(),
        ]);
    }

    /**
     * Register with email and password.
     */
    #[OA\Post(
        path: "/auth/register",
        operationId: "register",
        tags: ["Authentication"],
        summary: "Register new user",
        description: "Creates a new user account with email and password."
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["full_name", "email", "password", "password_confirmation", "phone"],
            properties: [
                new OA\Property(property: "full_name", type: "string", example: "John Doe"),
                new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                new OA\Property(property: "password", type: "string", format: "password", example: "Password123", description: "Must contain letters and numbers, min 8 chars"),
                new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "Password123"),
                new OA\Property(property: "phone", type: "string", example: "+967770000000"),
                new OA\Property(property: "role", type: "string", enum: ["JobSeeker", "Employer"], example: "JobSeeker"),
                new OA\Property(property: "gender", type: "string", enum: ["Male", "Female"], example: "Male"),
                new OA\Property(property: "date_of_birth", type: "string", format: "date", example: "1990-01-01"),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "User registered successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string", example: "Registration successful"),
                new OA\Property(property: "data", type: "object", properties: [
                    new OA\Property(property: "user_id", type: "integer"),
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "role", type: "string"),
                    new OA\Property(property: "token", type: "string"),
                ])
            ]
        )
    )]
    #[OA\Response(response: 422, description: "Validation error")]
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:user,Email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', // Must contain letters and numbers
            ],
            'password_confirmation' => 'required|same:password',
            'phone' => [
                'required',
                'string',
                'regex:/^(\+?[0-9]{9,15})$/', // Valid phone format
            ],
            'role' => 'nullable|in:JobSeeker,Employer',
            'gender' => 'nullable|string|in:Male,Female',
            'date_of_birth' => 'nullable|date|before:today',
        ], [
            // Arabic error messages
            'full_name.required' => 'الاسم الكامل مطلوب',
            'full_name.max' => 'الاسم الكامل يجب ألا يتجاوز 255 حرفاً',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
            'email.unique' => 'البريد الإلكتروني مستخدم مسبقاً',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.regex' => 'كلمة المرور يجب أن تحتوي على حروف وأرقام',
            'password_confirmation.required' => 'تأكيد كلمة المرور مطلوب',
            'password_confirmation.same' => 'كلمة المرور وتأكيدها غير متطابقتين',
            'phone.required' => 'رقم الجوال مطلوب',
            'phone.regex' => 'صيغة رقم الجوال غير صحيحة',
            'role.in' => 'نوع الحساب يجب أن يكون باحث عن عمل أو صاحب شركة',
            'gender.in' => 'الجنس يجب أن يكون ذكر أو أنثى',
            'date_of_birth.before' => 'تاريخ الميلاد يجب أن يكون قبل اليوم',
        ]);

        return DB::transaction(function () use ($request) {
            // Create user
            $user = User::create([
                'Email' => $request->input('email'),
                'PasswordHash' => Hash::make($request->input('password')),
                'FullName' => $request->input('full_name'),
                'Phone' => $request->input('phone'),
                'Gender' => $request->input('gender'),
                'DateOfBirth' => $request->input('date_of_birth'),
                'IsVerified' => false,
                'CreatedAt' => now(),
            ]);

            // Assign role (if provided)
            $roleName = $request->input('role');
            if ($roleName) {
                $role = Role::where('RoleName', $roleName)->first();
                if ($role) {
                    DB::table('userrole')->insert([
                        'UserID' => $user->UserID,
                        'RoleID' => $role->RoleID,
                        'AssignedAt' => now(),
                    ]);
                }

                // Create profile based on role
                if ($roleName === 'JobSeeker') {
                    DB::table('jobseekerprofile')->insert([
                        'JobSeekerID' => $user->UserID,
                    ]);
                } else {
                    DB::table('companyprofile')->insert([
                        'CompanyID' => $user->UserID,
                    ]);
                }
            }

            // Generate Sanctum token
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful',
                'data' => [
                    'user_id' => $user->UserID,
                    'email' => $user->Email,
                    'name' => $user->FullName,
                    'role' => $request->input('role'),
                    'token' => $token,
                ],
            ], 201);
        });
    }

    /**
     * Login with email and password.
     */
    #[OA\Post(
        path: "/auth/login",
        operationId: "login",
        tags: ["Authentication"],
        summary: "Login user",
        description: "Authenticates a user and returns a token."
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email", "password"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                new OA\Property(property: "password", type: "string", format: "password", example: "Password123"),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Login successful",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string", example: "Login successful"),
                new OA\Property(property: "data", type: "object", properties: [
                    new OA\Property(property: "user_id", type: "integer"),
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "role", type: "string"),
                    new OA\Property(property: "token", type: "string"),
                ])
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Invalid credentials")]
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('Email', $request->input('email'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->PasswordHash)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة',
            ], 401);
        }

        // Check if account is verified (User Story: Login - معيار القبول #2)
        if (!$user->IsVerified) {
            return response()->json([
                'message' => 'الحساب غير مفعّل، يرجى التحقق من بريدك الإلكتروني لتفعيل الحساب',
                'requires_verification' => true,
                'email' => $user->Email,
            ], 403);
        }

        // Get user's role
        $role = $user->roles()->first();

        // Generate Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'user_id' => $user->UserID,
                'email' => $user->Email,
                'name' => $user->FullName,
                'role' => $role?->RoleName,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Redirect to social provider authentication page.
     * Note: For an API, this will return the redirect URL. Frontend should use it.
     */
    #[OA\Get(
        path: "/auth/login/{provider}",
        operationId: "socialLoginRedirect",
        tags: ["Authentication"],
        summary: "Social Login Redirect",
        description: "Returns the redirection URL for social login (Google/LinkedIn)."
    )]
    #[OA\Parameter(
        name: "provider",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "string", enum: ["google", "linkedin"])
    )]
    #[OA\Response(
        response: 200,
        description: "Redirect URL generated",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "url", type: "string", example: "https://accounts.google.com/...")
            ]
        )
    )]
    public function redirectToProvider($provider): JsonResponse
    {
        return response()->json([
            'url' => \Laravel\Socialite\Facades\Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Handle callback from social provider.
     */
    #[OA\Get(
        path: "/auth/login/{provider}/callback",
        operationId: "socialLoginCallback",
        tags: ["Authentication"],
        summary: "Social Login Callback",
        description: "Handles the callback from social providers."
    )]
    #[OA\Parameter(
        name: "provider",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "string", enum: ["google", "linkedin"])
    )]
    #[OA\Response(
        response: 200,
        description: "Login successful",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string", example: "Login successful"),
                new OA\Property(property: "data", type: "object")
            ]
        )
    )]
    public function handleProviderCallback(
        $provider,
        \App\Domain\Auth\Actions\SocialLoginAction $action
    ): JsonResponse {
        try {
            $socialUser = \Laravel\Socialite\Facades\Socialite::driver($provider)->stateless()->user();

            $result = $action->execute($socialUser, $provider);

            return response()->json([
                'message' => 'Login successful',
                'data' => $result->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Logout current user.
     */
    #[OA\Post(
        path: "/auth/logout",
        operationId: "logout",
        tags: ["Authentication"],
        summary: "Logout user",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "Logged out successfully")]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current authenticated user.
     */
    #[OA\Get(
        path: "/auth/me",
        operationId: "me",
        tags: ["Authentication"],
        summary: "Get current user profile",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "User profile data")]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['roles', 'jobSeekerProfile', 'companyProfile']);

        $role = $user->roles->first();

        return response()->json([
            'data' => [
                'user_id' => $user->UserID,
                'full_name' => $user->FullName,
                'email' => $user->Email,
                'phone' => $user->Phone,
                'gender' => $user->Gender,
                'date_of_birth' => $user->DateOfBirth,
                'is_verified' => $user->IsVerified,
                'role' => $role?->RoleName,
                'job_seeker_profile' => $user->jobSeekerProfile,
                'company_profile' => $user->companyProfile,
            ],
        ]);
    }

    /**
     * Change user password
     */
    #[OA\Post(
        path: "/auth/change-password",
        operationId: "changePassword",
        tags: ["Authentication"],
        summary: "Change Password",
        security: [["bearerAuth" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["current_password", "new_password"],
            properties: [
                new OA\Property(property: "current_password", type: "string", format: "password"),
                new OA\Property(property: "new_password", type: "string", format: "password"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Password changed")]
    public function changePassword(Request $request): JsonResponse
    {
        if (Hash::check($request->input('current_password'), $request->user()->PasswordHash)) {
            $request->user()->update([
                'PasswordHash' => Hash::make($request->input('new_password')),
            ]);
            return response()->json([
                'message' => 'password is Changed successfully',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Current password is not correct',
            ], 400);
        }
    }

    /**
     * Set user role.
     */
    #[OA\Post(
        path: "/auth/set-role",
        operationId: "setRole",
        tags: ["Authentication"],
        summary: "Set User Role",
        security: [["bearerAuth" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["role"],
            properties: [
                new OA\Property(property: "role", type: "string", enum: ["JobSeeker", "Employer"]),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Role set successfully")]
    public function setRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:JobSeeker,Employer',
        ], [
            'role.required' => 'نوع الحساب مطلوب',
            'role.in' => 'نوع الحساب يجب أن يكون باحث عن عمل أو صاحب شركة',
        ]);

        $user = $request->user();

        // Check if user already has a role
        $existingRole = $user->roles()->first();
        if ($existingRole) {
            return response()->json([
                'message' => 'لديك نوع حساب محدد مسبقاً',
                'data' => [
                    'current_role' => $existingRole->RoleName,
                ],
            ], 403);
        }

        return DB::transaction(function () use ($user, $request) {
            $roleName = $request->input('role');

            // Assign role
            $role = Role::where('RoleName', $roleName)->first();
            if ($role) {
                DB::table('userrole')->insert([
                    'UserID' => $user->UserID,
                    'RoleID' => $role->RoleID,
                    'AssignedAt' => now(),
                ]);
            }

            // Create profile based on role
            if ($roleName === 'JobSeeker') {
                DB::table('jobseekerprofile')->insert([
                    'JobSeekerID' => $user->UserID,
                ]);
            } else {
                DB::table('companyprofile')->insert([
                    'CompanyID' => $user->UserID,
                ]);
            }

            return response()->json([
                'message' => 'تم تحديد نوع الحساب بنجاح',
                'data' => [
                    'role' => $roleName,
                    'profile_created' => true,
                ],
            ]);
        });
    }

    /**
     * Send password reset code.
     */
    #[OA\Post(
        path: "/auth/forgot-password",
        operationId: "forgotPassword",
        tags: ["Authentication"],
        summary: "Request Password Reset Code"
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Code sent")]
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
        ]);

        $user = User::where('Email', $request->input('email'))->first();

        if (!$user) {
            return response()->json([
                'message' => 'البريد الإلكتروني غير مسجل في النظام'
            ], 422);
        }

        // Generate 6-digit code
        $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed token
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->input('email')],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Send email
        try {
            Mail::to($user->Email)->send(new ResetPasswordCodeMail($token));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Mail sending failed: ' . $e->getMessage());
            // Continue even if mail fails in dev, or handle error
        }

        return response()->json([
            'message' => 'تم إرسال رمز إعادة تعيين كلمة المرور إلى بريدك الإلكتروني',
        ]);
    }

    /**
     * Verify reset password code.
     */
    #[OA\Post(
        path: "/auth/verify-reset-code",
        operationId: "verifyResetCode",
        tags: ["Authentication"],
        summary: "Verify Reset Code",
        description: "Verifies the password reset code before changing password."
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email", "token"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email"),
                new OA\Property(property: "token", type: "string", example: "123456"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Code is valid")]
    #[OA\Response(response: 422, description: "Code invalid or expired")]
    public function verifyResetCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:6',
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
            'token.required' => 'رمز إعادة التعيين مطلوب',
            'token.size' => 'رمز إعادة التعيين يجب أن يكون 6 أرقام',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'رمز إعادة التعيين غير صالح'
            ], 422);
        }

        // Check expiry (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete();
            return response()->json([
                'message' => 'رمز إعادة التعيين منتهي الصلاحية'
            ], 422);
        }

        // Verify token
        if (!Hash::check($request->input('token'), $record->token)) {
            return response()->json([
                'message' => 'رمز إعادة التعيين غير صحيح'
            ], 422);
        }

        return response()->json([
            'message' => 'رمز إعادة التعيين صالح',
            'valid' => true
        ]);
    }

    /**
     * Reset password with token.
     */
    #[OA\Post(
        path: "/auth/reset-password",
        operationId: "resetPassword",
        tags: ["Authentication"],
        summary: "Reset Password",
        description: "Sets a new password using a valid reset token."
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email", "token", "password", "password_confirmation"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email"),
                new OA\Property(property: "token", type: "string", example: "123456"),
                new OA\Property(property: "password", type: "string", format: "password"),
                new OA\Property(property: "password_confirmation", type: "string", format: "password"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Password reset successfully")]
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:6',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
            ],
            'password_confirmation' => 'required|same:password',
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب',
            'token.required' => 'رمز إعادة التعيين مطلوب',
            'token.size' => 'رمز إعادة التعيين يجب أن يكون 6 أرقام',
            'password.required' => 'كلمة المرور الجديدة مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.regex' => 'كلمة المرور يجب أن تحتوي على حروف وأرقام',
            'password_confirmation.same' => 'كلمة المرور وتأكيدها غير متطابقتين',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'رمز إعادة التعيين غير صالح'
            ], 422);
        }

        // Check expiry (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete();
            return response()->json([
                'message' => 'رمز إعادة التعيين منتهي الصلاحية'
            ], 422);
        }

        // Verify token
        if (!Hash::check($request->input('token'), $record->token)) {
            return response()->json([
                'message' => 'رمز إعادة التعيين غير صحيح'
            ], 422);
        }

        // Update password
        User::where('Email', $request->input('email'))->update([
            'PasswordHash' => Hash::make($request->input('password')),
        ]);

        // Delete used token
        DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete();

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    }

    /**
     * Send verification code to email.
     */
    #[OA\Post(
        path: "/auth/send-verification",
        operationId: "sendVerification",
        tags: ["Authentication"],
        summary: "Send Email Verification Code"
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Verification code sent")]
    public function sendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
        ]);

        $user = User::where('Email', $request->input('email'))->first();

        if (!$user) {
            return response()->json([
                'message' => 'البريد الإلكتروني غير مسجل في النظام'
            ], 422);
        }

        if ($user->IsVerified) {
            return response()->json([
                'message' => 'الحساب مفعّل مسبقاً'
            ], 422);
        }

        // Generate 6-digit code
        $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed token
        DB::table('email_verification_tokens')->updateOrInsert(
            ['email' => $request->input('email')],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Send email
        try {
            Mail::to($user->Email)->send(new VerificationCodeMail($token));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Mail sending failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني',
        ]);
    }

    /**
     * Verify account with token.
     */
    #[OA\Post(
        path: "/auth/verify-account",
        operationId: "verifyAccount",
        tags: ["Authentication"],
        summary: "Verify Email Account"
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email", "token"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email"),
                new OA\Property(property: "token", type: "string", example: "123456"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Account verified")]
    public function verifyAccount(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:6',
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
            'token.required' => 'رمز التحقق مطلوب',
            'token.size' => 'رمز التحقق يجب أن يكون 6 أرقام',
        ]);

        $user = User::where('Email', $request->input('email'))->first();

        if (!$user) {
            return response()->json([
                'message' => 'البريد الإلكتروني غير مسجل في النظام'
            ], 422);
        }

        if ($user->IsVerified) {
            return response()->json([
                'message' => 'الحساب مفعّل مسبقاً'
            ], 422);
        }

        $record = DB::table('email_verification_tokens')
            ->where('email', $request->input('email'))
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'رمز التحقق غير صالح'
            ], 422);
        }

        // Check expiry (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('email_verification_tokens')->where('email', $request->input('email'))->delete();
            return response()->json([
                'message' => 'رمز التحقق منتهي الصلاحية'
            ], 422);
        }

        // Verify token
        if (!Hash::check($request->input('token'), $record->token)) {
            return response()->json([
                'message' => 'رمز التحقق غير صحيح'
            ], 422);
        }

        // Activate account
        User::where('Email', $request->input('email'))->update([
            'IsVerified' => true,
        ]);

        // Delete used token
        DB::table('email_verification_tokens')->where('email', $request->input('email'))->delete();

        return response()->json([
            'message' => 'تم تفعيل حسابك بنجاح'
        ]);
    }
}
