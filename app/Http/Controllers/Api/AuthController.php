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

/**
 * Authentication Controller - Thin controller for auth endpoints.
 * Supports both Firebase auth and traditional email/password.
 */
class AuthController extends Controller
{
    /**
     * Register with email and password (traditional auth).
     * 
     * Validates:
     * - Full name (required)
     * - Email (required, valid format, unique)
     * - Password (required, min 8 chars, must contain letters and numbers)
     * - Password confirmation (required, must match)
     * - Phone (required, valid format)
     * - Role (required, JobSeeker or Employer)
     */
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
    public function redirectToProvider($provider): JsonResponse
    {
        return response()->json([
            'url' => \Laravel\Socialite\Facades\Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Handle callback from social provider.
     */
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
     * Set user role (for users who registered without a role).
     * This is the second step in two-step registration.
     */
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
     * Send password reset code to email.
     */
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
     * Reset password with token.
     */
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
