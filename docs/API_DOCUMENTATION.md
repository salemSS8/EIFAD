# 📚 API Documentation - Job Search Platform

> **Base URL**: `/api`  
> **Authentication**: Laravel Sanctum (Bearer Token)  
> **Database**: `final_project_database`

---

## 📑 Table of Contents

1. [Public Routes](#-public-routes-no-authentication)
2. [Authentication Routes](#-authentication-routes)
3. [Profile Routes](#-profile-routes)
4. [Notifications Routes](#-notifications-routes)
5. [CV Management Routes](#-cv-management-routes)
6. [Favorites Routes](#-favorites-routes)
7. [Applications Routes (Job Seeker)](#-applications-routes-job-seeker)
8. [Employer Routes](#-employer-routes)
9. [Companies Routes](#-companies-routes)
10. [Courses Routes](#-courses-routes)
11. [Messaging Routes](#-messaging-routes)
12. [Skills & Languages Routes](#-skills--languages-routes)

---

## 🌐 Public Routes (No Authentication)

### Health Check

#### `GET /api/health`

فحص صحة النظام والاتصال بقاعدة البيانات.

**Response:**

```json
{
    "status": "ok",
    "timestamp": "2024-01-10T13:00:00Z",
    "database": "final_project_database"
}
```

---

## 🔐 Authentication Routes

### Register (Traditional)

#### `POST /api/auth/register`

تسجيل مستخدم جديد باستخدام البريد الإلكتروني وكلمة المرور.

**Request Body:**

```json
{
    "full_name": "Ahmed Mohammed", // required | الاسم الكامل
    "email": "user@example.com", // required | email | unique | البريد الإلكتروني
    "password": "Password123", // required | min:8 | حروف وأرقام
    "password_confirmation": "Password123", // required | must match password
    "phone": "+967771234567", // required | رقم الجوال (9-15 رقم)
    "role": "JobSeeker", // optional | in:JobSeeker,Employer | إذا لم يُرسل استخدم POST /auth/set-role
    "gender": "Male", // optional | in:Male,Female
    "date_of_birth": "1990-01-15" // optional | date | before today
}
```

**شروط كلمة المرور:**

- 8 أحرف على الأقل
- يجب أن تحتوي على حروف وأرقام معاً

**Response (201):**

```json
{
    "message": "Registration successful",
    "data": {
        "user_id": 1,
        "email": "user@example.com",
        "name": "Ahmed Mohammed",
        "role": "JobSeeker", // null إذا لم يُحدد (نظام الخطوتين)
        "token": "1|abcdefghijklmnop..."
    }
}
```

**Error Response (422) - Validation Errors:**

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "full_name": ["الاسم الكامل مطلوب"],
        "email": ["البريد الإلكتروني مستخدم مسبقاً"],
        "password": [
            "كلمة المرور يجب أن تكون 8 أحرف على الأقل",
            "كلمة المرور يجب أن تحتوي على حروف وأرقام"
        ],
        "password_confirmation": ["كلمة المرور وتأكيدها غير متطابقتين"],
        "phone": ["رقم الجوال مطلوب", "صيغة رقم الجوال غير صحيحة"]
    }
}
```

---

### Login (Traditional)

#### `POST /api/auth/login`

تسجيل الدخول باستخدام البريد الإلكتروني وكلمة المرور.

**Request Body:**

```json
{
    "email": "user@example.com", // required | email
    "password": "password123" // required
}
```

**Response (200):**

```json
{
    "message": "Login successful",
    "data": {
        "user_id": 1,
        "email": "user@example.com",
        "name": "Ahmed Mohammed",
        "role": "JobSeeker",
        "token": "2|xyzsecuretoken..."
    }
}
```

**Error Response (401):**

```json
{
    "message": "Invalid credentials"
}
```

---

### Register with Firebase

#### `POST /api/auth/register/firebase`

تسجيل مستخدم جديد باستخدام Firebase Authentication.

**Request Body:**

```json
{
    "firebase_token": "eyJhbGciOiJSUzI1...", // required | Firebase ID Token
    "role": "JobSeeker", // optional | in:JobSeeker,Employer | إذا لم يُرسل استخدم POST /auth/set-role
    "name": "Ahmed Mohammed" // optional | max:255
}
```

**Response (201):**

```json
{
    "message": "Registration successful",
    "data": {
        "user_id": 1,
        "email": "user@example.com",
        "name": "Ahmed Mohammed",
        "role": "JobSeeker", // null إذا لم يُحدد
        "token": "3|sanctumtoken..."
    }
}
```

---

### Login with Firebase

#### `POST /api/auth/login/firebase`

تسجيل الدخول باستخدام Firebase Authentication.

**Request Body:**

```json
{
    "firebase_token": "eyJhbGciOiJSUzI1..." // required | Firebase ID Token
}
```

**Response (200):**

```json
{
    "message": "Login successful",
    "data": {
        "user_id": 1,
        "email": "user@example.com",
        "name": "Ahmed Mohammed",
        "role": "JobSeeker",
        "token": "4|authtoken..."
    }
}
```

---

### Logout

#### `POST /api/auth/logout`

🔒 **Requires Authentication**

تسجيل الخروج وإبطال التوكن الحالي.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200):**

```json
{
    "message": "Logged out successfully"
}
```

---

### Forgot Password

#### `POST /api/auth/forgot-password`

طلب رمز إعادة تعيين كلمة المرور.

**Request Body:**

```json
{
    "email": "user@example.com" // required | email
}
```

**Response (200):**

```json
{
    "message": "تم إرسال رمز إعادة تعيين كلمة المرور إلى بريدك الإلكتروني"
}
```

**Error Response (422):**

```json
{
    "message": "البريد الإلكتروني غير مسجل في النظام"
}
```

---

### Verify Reset Code

#### `POST /api/auth/verify-reset-code`

التحقق من صحة رمز إعادة تعيين كلمة المرور قبل تغييرها.

**Request Body:**

```json
{
    "email": "user@example.com", // required | email
    "token": "123456" // required | 6 أرقام
}
```

**Response (200):**

```json
{
    "message": "رمز إعادة التعيين صالح",
    "valid": true
}
```

**Error Responses (422):**

```json
{
    "message": "رمز إعادة التعيين غير صحيح"
}
// أو
{
    "message": "رمز إعادة التعيين منتهي الصلاحية"
}
```

---

### Reset Password

#### `POST /api/auth/reset-password`

إعادة تعيين كلمة المرور باستخدام الرمز.

**Request Body:**

```json
{
    "email": "user@example.com", // required | email
    "token": "123456", // required | 6 أرقام
    "password": "NewPassword123", // required | min:8 | حروف وأرقام
    "password_confirmation": "NewPassword123" // required | must match
}
```

**Response (200):**

```json
{
    "message": "تم تغيير كلمة المرور بنجاح"
}
```

**Error Responses (422):**

```json
{
    "message": "رمز إعادة التعيين غير صحيح"
}
// أو
{
    "message": "رمز إعادة التعيين منتهي الصلاحية"
}
```

---

### Send Verification Code

#### `POST /api/auth/send-verification`

إرسال رمز التحقق من الحساب.

**Request Body:**

```json
{
    "email": "user@example.com" // required | email
}
```

**Response (200):**

```json
{
    "message": "تم إرسال رمز التحقق إلى بريدك الإلكتروني"
}
```

**Error Responses (422):**

```json
{
    "message": "البريد الإلكتروني غير مسجل في النظام"
}
// أو
{
    "message": "الحساب مفعّل مسبقاً"
}
```

---

### Verify Account

#### `POST /api/auth/verify-account`

تفعيل الحساب باستخدام رمز التحقق.

**Request Body:**

```json
{
    "email": "user@example.com", // required | email
    "token": "123456" // required | 6 أرقام
}
```

**Response (200):**

```json
{
    "message": "تم تفعيل حسابك بنجاح"
}
```

**Error Responses (422):**

```json
{
    "message": "رمز التحقق غير صحيح"
}
// أو
{
    "message": "رمز التحقق منتهي الصلاحية"
}
// أو
{
    "message": "الحساب مفعّل مسبقاً"
}
```

---

### Get Current User

#### `GET /api/auth/me`

🔒 **Requires Authentication**

الحصول على بيانات المستخدم الحالي مع ملفه الشخصي.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200):**

```json
{
    "data": {
        "user_id": 1,
        "full_name": "Ahmed Mohammed",
        "email": "user@example.com",
        "phone": "+967771234567",
        "gender": "Male",
        "date_of_birth": "1990-01-15",
        "is_verified": true,
        "role": "JobSeeker",
        "job_seeker_profile": {
            "JobSeekerID": 1,
            "PersonalPhoto": "/photos/ahmed.jpg",
            "Location": "Sana'a",
            "ProfileSummary": "Software Developer..."
        },
        "company_profile": null
    }
}
```

---

### Change Password

#### `POST /api/auth/change-password`

🔒 **Requires Authentication**

تغيير كلمة المرور للمستخدم الحالي.

**Headers:**

```
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "current_password": "OldPassword123", // required
    "new_password": "NewPassword456", // required | min:8
    "new_password_confirmation": "NewPassword456" // required | must match
}
```

**Response (200):**

```json
{
    "message": "password is Changed successfully"
}
```

**Error Response (422):**

```json
{
    "message": "The current password is incorrect"
}
```

---

### Set Role (Two-Step Registration)

#### `POST /api/auth/set-role`

🔒 **Requires Authentication**

تحديد نوع الحساب للمستخدمين الذين سجلوا بدون تحديد الدور.

**Headers:**

```
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "role": "JobSeeker" // required | in:JobSeeker,Employer
}
```

**Response (200):**

```json
{
    "message": "تم تحديد نوع الحساب بنجاح",
    "data": {
        "role": "JobSeeker",
        "profile_created": true
    }
}
```

**Error Response (403) - Already has role:**

```json
{
    "message": "لديك نوع حساب محدد مسبقاً",
    "data": {
        "current_role": "JobSeeker"
    }
}
```

---

## 📝 Profile Routes

🔒 **All routes require Authentication**

### Get Profile

#### `GET /api/profile`

الحصول على الملف الشخصي للمستخدم الحالي.

**Response (200) - Job Seeker:**

```json
{
    "type": "job_seeker",
    "data": {
        "JobSeekerID": 1,
        "PersonalPhoto": "/photos/ahmed.jpg",
        "Location": "Sana'a",
        "ProfileSummary": "Experienced developer..."
    }
}
```

**Response (200) - Employer:**

```json
{
    "type": "company",
    "data": {
        "CompanyID": 2,
        "CompanyName": "Tech Solutions",
        "OrganizationName": "Tech Solutions LLC",
        "Address": "Sana'a, Yemen",
        "Description": "Leading tech company...",
        "LogoPath": "/logos/tech.png",
        "WebsiteURL": "https://techsolutions.com",
        "EstablishedYear": 2015,
        "EmployeeCount": 50,
        "FieldOfWork": "Information Technology",
        "IsCompanyVerified": true,
        "specializations": [...]
    }
}
```

---

### Create/Update Profile

#### `POST /api/profile` or `PUT /api/profile`

إنشاء أو تحديث الملف الشخصي.

**Request Body (Job Seeker):**

```json
{
    "personal_photo": "/photos/ahmed.jpg", // optional | max:255
    "location": "Sana'a, Yemen", // optional | max:255
    "profile_summary": "Experienced..." // optional
}
```

**Request Body (Employer):**

```json
{
    "company_name": "Tech Solutions", // required | max:255
    "organization_name": "Tech LLC", // optional | max:255
    "address": "Sana'a, Yemen", // optional | max:255
    "description": "Leading company...", // optional
    "logo_path": "/logos/tech.png", // optional | max:255
    "website_url": "https://example.com", // optional | url | max:255
    "established_year": 2015, // optional | 1800-2100
    "employee_count": 50, // optional | min:1
    "field_of_work": "Technology" // optional | max:255
}
```

**Response (200):**

```json
{
    "message": "Profile updated successfully",
    "data": { ... }
}
```

---

## 🔔 Notifications Routes

🔒 **All routes require Authentication**

### Get Notifications

#### `GET /api/notifications`

الحصول على إشعارات المستخدم.

**Response (200):**

```json
{
    "data": [
        {
            "NotificationID": 1,
            "UserID": 1,
            "Title": "New Job Match",
            "Content": "A new job matches your profile",
            "IsRead": false,
            "CreatedAt": "2024-01-10T12:00:00Z"
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

### Mark Notification as Read

#### `PUT /api/notifications/{id}/read`

تعليم إشعار محدد كمقروء.

**Response (200):**

```json
{
    "message": "Notification marked as read"
}
```

---

### Mark All Notifications as Read

#### `POST /api/notifications/mark-all-read`

تعليم جميع الإشعارات كمقروءة.

**Response (200):**

```json
{
    "message": "All notifications marked as read"
}
```

---

## 📄 CV Management Routes

🔒 **All routes require Authentication (Job Seekers only)**

### Get All CVs

#### `GET /api/cvs`

الحصول على جميع السير الذاتية للمستخدم.

**Response (200):**

```json
{
    "data": [
        {
            "CVID": 1,
            "JobSeekerID": 1,
            "Title": "Software Developer CV",
            "PersonalSummary": "Experienced developer...",
            "CreatedAt": "2024-01-01T00:00:00Z",
            "UpdatedAt": "2024-01-10T00:00:00Z"
        }
    ]
}
```

---

### Get CV Details

#### `GET /api/cvs/{id}`

الحصول على تفاصيل سيرة ذاتية محددة مع جميع البيانات المرتبطة.

**Response (200):**

```json
{
    "data": {
        "CVID": 1,
        "JobSeekerID": 1,
        "Title": "Software Developer CV",
        "PersonalSummary": "Experienced developer...",
        "skills": [
            {
                "CVSkillID": 1,
                "CVID": 1,
                "SkillID": 5,
                "SkillLevel": "Expert",
                "skill": {
                    "SkillID": 5,
                    "SkillName": "JavaScript"
                }
            }
        ],
        "languages": [
            {
                "CVLanguageID": 1,
                "CVID": 1,
                "LanguageID": 1,
                "LanguageLevel": "Native",
                "language": {
                    "LanguageID": 1,
                    "LanguageName": "Arabic"
                }
            }
        ],
        "education": [
            {
                "EducationID": 1,
                "CVID": 1,
                "Institution": "Sana'a University",
                "DegreeName": "Bachelor's",
                "Major": "Computer Science",
                "GraduationYear": 2015
            }
        ],
        "experiences": [
            {
                "ExperienceID": 1,
                "CVID": 1,
                "JobTitle": "Senior Developer",
                "CompanyName": "Tech Corp",
                "StartDate": "2018-01-01",
                "EndDate": "2023-12-31",
                "Responsibilities": "Leading development team..."
            }
        ],
        "volunteering": [ ... ],
        "courses": [ ... ]
    }
}
```

---

### Create CV

#### `POST /api/cvs`

إنشاء سيرة ذاتية جديدة.

**Request Body:**

```json
{
    "title": "My Professional CV", // required | max:255
    "personal_summary": "Summary text..." // optional
}
```

**Response (201):**

```json
{
    "message": "CV created successfully",
    "data": {
        "CVID": 2,
        "JobSeekerID": 1,
        "Title": "My Professional CV",
        "PersonalSummary": "Summary text...",
        "CreatedAt": "2024-01-10T13:00:00Z",
        "UpdatedAt": "2024-01-10T13:00:00Z"
    }
}
```

---

### Update CV

#### `PUT /api/cvs/{id}`

تحديث سيرة ذاتية.

**Request Body:**

```json
{
    "title": "Updated Title", // optional | max:255
    "personal_summary": "New summary..." // optional
}
```

**Response (200):**

```json
{
    "message": "CV updated successfully",
    "data": { ... }
}
```

---

### Delete CV

#### `DELETE /api/cvs/{id}`

حذف سيرة ذاتية.

**Response (200):**

```json
{
    "message": "CV deleted successfully"
}
```

---

### Add Skill to CV

#### `POST /api/cvs/{cvId}/skills`

إضافة مهارة إلى السيرة الذاتية.

**Request Body:**

```json
{
    "skill_id": 5, // required | exists in skill table
    "skill_level": "Expert" // optional | max:255
}
```

**Response (201):**

```json
{
    "message": "Skill added to CV",
    "data": {
        "CVSkillID": 1,
        "CVID": 1,
        "SkillID": 5,
        "SkillLevel": "Expert",
        "skill": {
            "SkillID": 5,
            "SkillName": "JavaScript"
        }
    }
}
```

---

### Remove Skill from CV

#### `DELETE /api/cvs/{cvId}/skills/{skillId}`

إزالة مهارة من السيرة الذاتية.

**Response (200):**

```json
{
    "message": "Skill removed from CV"
}
```

---

### Add Education to CV

#### `POST /api/cvs/{cvId}/education`

إضافة مؤهل تعليمي.

**Request Body:**

```json
{
    "institution": "Sana'a University", // required | max:255
    "degree_name": "Bachelor's Degree", // required | max:255
    "major": "Computer Science", // optional | max:255
    "graduation_year": 2020 // optional | 1950-2050
}
```

**Response (201):**

```json
{
    "message": "Education added to CV",
    "data": { ... }
}
```

---

### Remove Education from CV

#### `DELETE /api/cvs/{cvId}/education/{educationId}`

إزالة مؤهل تعليمي.

**Response (200):**

```json
{
    "message": "Education removed from CV"
}
```

---

### Add Experience to CV

#### `POST /api/cvs/{cvId}/experience`

إضافة خبرة عملية.

**Request Body:**

```json
{
    "job_title": "Senior Developer", // required | max:255
    "company_name": "Tech Corp", // required | max:255
    "start_date": "2018-01-01", // required | date
    "end_date": "2023-12-31", // optional | date | after start
    "responsibilities": "Leading team..." // optional
}
```

**Response (201):**

```json
{
    "message": "Experience added to CV",
    "data": { ... }
}
```

---

### Remove Experience from CV

#### `DELETE /api/cvs/{cvId}/experience/{experienceId}`

إزالة خبرة عملية.

**Response (200):**

```json
{
    "message": "Experience removed from CV"
}
```

---

### Add Language to CV

#### `POST /api/cvs/{cvId}/languages`

إضافة لغة.

**Request Body:**

```json
{
    "language_id": 1, // required | exists in language table
    "language_level": "Native" // optional | max:255
}
```

**Response (201):**

```json
{
    "message": "Language added to CV",
    "data": { ... }
}
```

---

### Remove Language from CV

#### `DELETE /api/cvs/{cvId}/languages/{languageId}`

إزالة لغة.

**Response (200):**

```json
{
    "message": "Language removed from CV"
}
```

---

## ⭐ Favorites Routes

🔒 **Requires Authentication (Job Seekers only)**

### Get Favorite Jobs

#### `GET /api/favorites`

الحصول على الوظائف المفضلة.

**Response (200):**

```json
{
    "data": [
        {
            "FavoriteID": 1,
            "JobSeekerID": 1,
            "JobAdID": 5,
            "SavedAt": "2024-01-10T12:00:00Z",
            "jobAd": {
                "JobAdID": 5,
                "Title": "Full Stack Developer",
                "company": {
                    "CompanyID": 2,
                    "CompanyName": "Tech Solutions",
                    "LogoPath": "/logos/tech.png"
                }
            }
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

### Add to Favorites

#### `POST /api/favorites/{jobId}`

إضافة وظيفة إلى المفضلة.

**Response (200):**

```json
{
    "message": "Job saved to favorites"
}
```

---

### Remove from Favorites

#### `DELETE /api/favorites/{jobId}`

إزالة وظيفة من المفضلة.

**Response (200):**

```json
{
    "message": "Job removed from favorites"
}
```

---

## 📬 Applications Routes (Job Seeker)

🔒 **Requires Authentication (Job Seekers only)**

### Get My Applications

#### `GET /api/applications`

الحصول على طلبات التوظيف المقدمة.

**Response (200):**

```json
{
    "data": [
        {
            "ApplicationID": 1,
            "JobAdID": 5,
            "JobSeekerID": 1,
            "CVID": 1,
            "AppliedAt": "2024-01-10T10:00:00Z",
            "Status": "Pending",
            "MatchScore": 85.5,
            "Notes": "Interested in this position",
            "jobAd": {
                "JobAdID": 5,
                "Title": "Full Stack Developer",
                "company": {
                    "CompanyID": 2,
                    "CompanyName": "Tech Solutions",
                    "LogoPath": "/logos/tech.png"
                }
            },
            "cv": {
                "CVID": 1,
                "Title": "My Professional CV"
            }
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

### Get Application Details

#### `GET /api/applications/{id}`

الحصول على تفاصيل طلب محدد.

**Response (200):**

```json
{
    "data": {
        "ApplicationID": 1,
        "JobAdID": 5,
        "JobSeekerID": 1,
        "CVID": 1,
        "AppliedAt": "2024-01-10T10:00:00Z",
        "Status": "Pending",
        "MatchScore": 85.5,
        "Notes": "Interested in this position",
        "jobAd": {
            "JobAdID": 5,
            "Title": "Full Stack Developer",
            "Description": "We are looking for...",
            "company": { ... },
            "skills": [ ... ]
        },
        "cv": { ... }
    }
}
```

---

### Apply to Job

#### `POST /api/applications`

التقديم على وظيفة.

**Request Body:**

```json
{
    "job_id": 5, // required | exists in jobad
    "cv_id": 1, // required | exists in cv (owned by user)
    "notes": "I am interested because..." // optional | max:1000
}
```

**Response (201):**

```json
{
    "message": "Application submitted successfully",
    "data": {
        "ApplicationID": 2,
        "JobAdID": 5,
        "JobSeekerID": 1,
        "CVID": 1,
        "AppliedAt": "2024-01-10T13:00:00Z",
        "Status": "Pending",
        "MatchScore": 85.5,
        "Notes": "I am interested because...",
        "jobAd": { ... },
        "cv": { ... }
    }
}
```

**Error Responses:**

```json
// 422 - CV doesn't belong to user
{ "message": "Invalid CV specified" }

// 422 - Job not active
{ "message": "This job is not accepting applications" }

// 422 - Already applied
{ "message": "You have already applied to this job" }
```

---

### Withdraw Application

#### `POST /api/applications/{id}/withdraw`

سحب طلب التوظيف.

**Response (200):**

```json
{
    "message": "Application withdrawn successfully"
}
```

**Error Response (422):**

```json
{
    "message": "Application already withdrawn"
}
```

---

## 💼 Employer Routes

🔒 **Requires Authentication (Employers only)**

### Get My Jobs

#### `GET /api/employer/jobs`

الحصول على وظائف الشركة.

**Response (200):**

```json
{
    "data": [
        {
            "JobAdID": 5,
            "CompanyID": 2,
            "Title": "Full Stack Developer",
            "Description": "We are looking for...",
            "Status": "Active",
            "PostedAt": "2024-01-01T00:00:00Z",
            "applications_count": 15
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

### Create Job

#### `POST /api/employer/jobs`

إنشاء وظيفة جديدة.

**Request Body:**

```json
{
    "title": "Full Stack Developer", // required | max:255
    "description": "Job description...", // required
    "responsibilities": "Your duties...", // optional
    "requirements": "Must have...", // optional
    "location": "Sana'a, Yemen", // optional | max:255
    "workplace_type": "Remote", // optional | remote/onsite/hybrid
    "work_type": "full_time", // optional | full_time/part_time/contract
    "salary_min": 500, // optional | min:0
    "salary_max": 1500, // optional | min:0
    "currency": "USD", // optional | default:USD | max:10
    "skills": [
        // optional | array
        {
            "skill_id": 5, // required | exists in skill
            "required_level": "Advanced", // optional
            "is_mandatory": true // optional | boolean
        }
    ]
}
```

**Response (201):**

```json
{
    "message": "Job created successfully",
    "data": {
        "JobAdID": 6,
        "CompanyID": 2,
        "Title": "Full Stack Developer",
        "Status": "Draft",
        "PostedAt": "2024-01-10T13:00:00Z",
        "skills": [ ... ]
    }
}
```

---

### Update Job

#### `PUT /api/employer/jobs/{id}`

تحديث وظيفة.

**Request Body:**

```json
{
    "Title": "Updated Title",
    "Description": "New description...",
    "Responsibilities": "...",
    "Requirements": "...",
    "Location": "Aden",
    "WorkplaceType": "Hybrid",
    "WorkType": "full_time",
    "SalaryMin": 600,
    "SalaryMax": 1800,
    "Currency": "USD",
    "Status": "Active"
}
```

**Response (200):**

```json
{
    "message": "Job updated successfully",
    "data": { ... }
}
```

---

### Publish Job

#### `POST /api/employer/jobs/{id}/publish`

نشر وظيفة (تغيير الحالة إلى Active).

**Response (200):**

```json
{
    "message": "Job published successfully",
    "data": {
        "JobAdID": 6,
        "Status": "Active",
        "PostedAt": "2024-01-10T14:00:00Z"
    }
}
```

---

### Close Job

#### `POST /api/employer/jobs/{id}/close`

إغلاق وظيفة (تغيير الحالة إلى Closed).

**Response (200):**

```json
{
    "message": "Job closed successfully",
    "data": {
        "JobAdID": 6,
        "Status": "Closed"
    }
}
```

---

### Delete Job

#### `DELETE /api/employer/jobs/{id}`

حذف وظيفة.

**Response (200):**

```json
{
    "message": "Job deleted successfully"
}
```

---

### Get Job Applications

#### `GET /api/employer/jobs/{jobId}/applications`

الحصول على الطلبات المقدمة لوظيفة محددة.

**Response (200):**

```json
{
    "data": [
        {
            "ApplicationID": 1,
            "JobAdID": 5,
            "JobSeekerID": 1,
            "CVID": 1,
            "AppliedAt": "2024-01-10T10:00:00Z",
            "Status": "Pending",
            "MatchScore": 85.5,
            "jobSeeker": {
                "JobSeekerID": 1,
                "user": {
                    "UserID": 1,
                    "FullName": "Ahmed Mohammed",
                    "Email": "ahmed@example.com"
                }
            },
            "cv": {
                "CVID": 1,
                "Title": "Professional CV",
                "skills": [ ... ],
                "experiences": [ ... ]
            }
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

### Update Application Status

#### `PUT /api/employer/applications/{id}/status`

تحديث حالة طلب التوظيف.

**Request Body:**

```json
{
    "status": "Shortlisted", // required | Pending,Reviewed,Shortlisted,Interviewing,Offered,Hired,Rejected
    "notes": "Great candidate" // optional | max:1000
}
```

**Response (200):**

```json
{
    "message": "Application status updated",
    "data": {
        "ApplicationID": 1,
        "Status": "Shortlisted",
        "Notes": "Great candidate"
    }
}
```

---

## 🏬 Companies Routes

🔒 **Requires Authentication**

### Get Companies

#### `GET /api/companies`

الحصول على قائمة الشركات الموثقة.

**Response (200):**

```json
{
    "data": [
        {
            "CompanyID": 2,
            "CompanyName": "Tech Solutions",
            "LogoPath": "/logos/tech.png",
            "IsCompanyVerified": true
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

### Get Company Details

#### `GET /api/companies/{id}`

الحصول على تفاصيل شركة مع وظائفها.

**Response (200):**

```json
{
    "data": {
        "CompanyID": 2,
        "CompanyName": "Tech Solutions",
        "Description": "Leading tech company...",
        "LogoPath": "/logos/tech.png",
        "WebsiteURL": "https://techsolutions.com",
        "EmployeeCount": 50,
        "jobAds": [
            {
                "JobAdID": 5,
                "Title": "Full Stack Developer",
                "Status": "Active"
            }
        ]
    }
}
```

---

### Follow Company

#### `POST /api/companies/{id}/follow`

متابعة شركة (للباحثين عن عمل فقط).

**Response (200):**

```json
{
    "message": "Company followed"
}
```

**Error Response (403):**

```json
{
    "message": "Only job seekers can follow companies"
}
```

---

### Unfollow Company

#### `DELETE /api/companies/{id}/follow`

إلغاء متابعة شركة.

**Response (200):**

```json
{
    "message": "Unfollowed company"
}
```

---

## 📚 Courses Routes

🔒 **Requires Authentication**

### Get Courses

#### `GET /api/courses`

الحصول على الدورات النشطة.

**Response (200):**

```json
{
    "data": [
        {
            "CourseAdID": 1,
            "Title": "Web Development Bootcamp",
            "Description": "Learn full stack...",
            "Status": "Active",
            "company": {
                "CompanyID": 2,
                "CompanyName": "Tech Academy"
            }
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

### Get Course Details

#### `GET /api/courses/{id}`

الحصول على تفاصيل دورة.

**Response (200):**

```json
{
    "data": {
        "CourseAdID": 1,
        "Title": "Web Development Bootcamp",
        "Description": "Learn full stack development...",
        "Duration": "12 weeks",
        "Price": 500,
        "Status": "Active",
        "company": { ... }
    }
}
```

---

### Enroll in Course

#### `POST /api/courses/{id}/enroll`

التسجيل في دورة (للباحثين عن عمل فقط).

**Response (200):**

```json
{
    "message": "Enrolled successfully"
}
```

**Error Response (403):**

```json
{
    "message": "Only job seekers can enroll"
}
```

---

## 💬 Messaging Routes

🔒 **Requires Authentication**

### Get Conversations

#### `GET /api/conversations`

الحصول على المحادثات.

**Response (200):**

```json
{
    "data": [
        {
            "ConversationID": 1,
            "CreatedAt": "2024-01-01T00:00:00Z",
            "participants": [
                {
                    "ParticipantID": 1,
                    "UserID": 1,
                    "user": {
                        "UserID": 1,
                        "FullName": "Ahmed"
                    }
                }
            ],
            "messages": [
                {
                    "MessageID": 10,
                    "Content": "Last message...",
                    "SentAt": "2024-01-10T12:00:00Z"
                }
            ]
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

### Get Conversation Messages

#### `GET /api/conversations/{id}/messages`

الحصول على رسائل محادثة.

**Response (200):**

```json
{
    "data": [
        {
            "MessageID": 1,
            "ConversationID": 1,
            "SenderID": 1,
            "Content": "Hello!",
            "SentAt": "2024-01-10T10:00:00Z",
            "IsDeleted": false,
            "sender": {
                "UserID": 1,
                "FullName": "Ahmed"
            }
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

**Error Response (403):**

```json
{
    "message": "Unauthorized"
}
```

---

### Send Message

#### `POST /api/conversations/{id}/messages`

إرسال رسالة.

**Request Body:**

```json
{
    "content": "Hello, I'm interested!" // required | max:5000
}
```

**Response (201):**

```json
{
    "message": "Message sent",
    "data": {
        "MessageID": 11,
        "ConversationID": 1,
        "SenderID": 1,
        "Content": "Hello, I'm interested!",
        "SentAt": "2024-01-10T13:00:00Z",
        "IsDeleted": false
    }
}
```

---

## 🛠 Skills & Languages Routes

### Get Skills

#### `GET /api/skills`

الحصول على قائمة المهارات.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `category_id` | integer | Filter by category |
| `search` | string | Search by skill name |

**Response (200):**

```json
{
    "data": [
        {
            "SkillID": 1,
            "SkillName": "JavaScript",
            "CategoryID": 2,
            "category": {
                "CategoryID": 2,
                "CategoryName": "Programming Languages"
            }
        }
    ]
}
```

---

### Get Skill Categories

#### `GET /api/skill-categories`

الحصول على تصنيفات المهارات.

**Response (200):**

```json
{
    "data": [
        {
            "CategoryID": 1,
            "CategoryName": "Programming Languages",
            "skills_count": 25
        },
        {
            "CategoryID": 2,
            "CategoryName": "Frameworks",
            "skills_count": 18
        }
    ]
}
```

---

### Get Languages

#### `GET /api/languages`

الحصول على قائمة اللغات.

**Response (200):**

```json
{
    "data": [
        {
            "LanguageID": 1,
            "LanguageName": "Arabic"
        },
        {
            "LanguageID": 2,
            "LanguageName": "English"
        }
    ]
}
```

---

## 💼 Jobs Routes (Public)

### Search Jobs

#### `GET /api/jobs`

البحث والفلترة في الوظائف.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `keyword` | string | Search in title and description |
| `location` | string | Filter by location |
| `work_type` | string | `full_time`, `part_time`, `contract` |
| `workplace_type` | string | `remote`, `onsite`, `hybrid` |
| `salary_min` | integer | Minimum salary |
| `salary_max` | integer | Maximum salary |
| `company_id` | integer | Filter by company |
| `skill_ids` | array/string | Comma-separated skill IDs |
| `sort_by` | string | Column to sort by (default: `PostedAt`) |
| `sort_order` | string | `asc` or `desc` (default: `desc`) |
| `per_page` | integer | Items per page (max: 50, default: 15) |

**Response (200):**

```json
{
    "data": [
        {
            "JobAdID": 5,
            "CompanyID": 2,
            "Title": "Full Stack Developer",
            "Description": "We are looking for...",
            "Location": "Sana'a",
            "WorkplaceType": "Remote",
            "WorkType": "full_time",
            "SalaryMin": 500,
            "SalaryMax": 1500,
            "Currency": "USD",
            "Status": "Active",
            "PostedAt": "2024-01-01T00:00:00Z",
            "company": {
                "CompanyID": 2,
                "CompanyName": "Tech Solutions",
                "LogoPath": "/logos/tech.png"
            },
            "skills": [
                {
                    "JobSkillID": 1,
                    "SkillID": 5,
                    "RequiredLevel": "Advanced",
                    "IsMandatory": true,
                    "skill": {
                        "SkillID": 5,
                        "SkillName": "JavaScript"
                    }
                }
            ]
        }
    ],
    "links": { ... },
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 72
    }
}
```

---

### Get Job Details

#### `GET /api/jobs/{id}`

الحصول على تفاصيل وظيفة.

**Response (200):**

```json
{
    "data": {
        "JobAdID": 5,
        "CompanyID": 2,
        "Title": "Full Stack Developer",
        "Description": "Detailed job description...",
        "Responsibilities": "Your duties will include...",
        "Requirements": "Must have 3+ years experience...",
        "Location": "Sana'a, Yemen",
        "WorkplaceType": "Remote",
        "WorkType": "full_time",
        "SalaryMin": 500,
        "SalaryMax": 1500,
        "Currency": "USD",
        "Status": "Active",
        "PostedAt": "2024-01-01T00:00:00Z",
        "company": {
            "CompanyID": 2,
            "CompanyName": "Tech Solutions",
            "Description": "Leading tech company...",
            "LogoPath": "/logos/tech.png",
            "WebsiteURL": "https://techsolutions.com"
        },
        "skills": [
            {
                "JobSkillID": 1,
                "SkillID": 5,
                "RequiredLevel": "Advanced",
                "IsMandatory": true,
                "skill": {
                    "SkillID": 5,
                    "SkillName": "JavaScript",
                    "category": {
                        "CategoryID": 2,
        ]
    }
}
```

---

### Get Suggested Jobs

#### `GET /api/jobs/suggested`

🔒 **Requires Authentication (Job Seeker)**

الحصول على وظائف مقترحة بناءً على مهارات الباحث عن عمل ومجال تخصصه.

**Response (200):**

```json
{
    "data": [
        {
            "JobAdID": 10,
            "Title": "Senior Laravel Developer",
            "company": {
                "CompanyName": "Future Tech"
            }
        }
    ],
    "meta": { "total": 25 }
}
```

---

## 🤖 AI Analytics & Market Trends Routes

🔒 **Requires Authentication**

### Get Market Trends

#### `GET /api/market-trends`

الحصول على اتجاهات السوق (أكثر المهارات طلباً والوظائف الرائجة).

**Response (200):**

```json
{
    "data": {
        "top_skills": [
            {
                "skill_id": 1,
                "skill_name": "Laravel",
                "demand_count": 150,
                "popularity_percentage": 85.5
            }
        ],
        "trending_jobs": [
            {
                "JobTitle": "Backend Developer",
                "AverageSalary": 5500.00,
                "PostCount": 45,
                "SnapshotDate": "2024-01-10"
            }
        ]
    },
    "meta": {
        "snapshot_date": "2024-01-10",
        "total_active_jobs": 500
    }
}
```

---

### Get AI Match Details

#### `GET /api/applications/{id}/ai-match`

الحصول على تحليل المطابقة الذكي لطلب توظيف محدد (لأصحاب العمل).

**Response (200):**

```json
{
    "data": {
        "application_id": 1,
        "match_score": 85,
        "compatibility_level": "HIGH",
        "notes": "Excellent technical fit...",
        "cv_job_match": {
            "skills_score": 90,
            "experience_score": 80,
            "education_score": 85,
            "strengths": ["Strong PHP skills", "5 years experience"],
            "gaps": ["Missing Docker experience"]
        }
    }
}
```

---

### Get CV AI Analysis

#### `GET /api/cvs/{id}/analysis`

الحصول على تقييم ذكي للسيرة الذاتية (للباحثين عن عمل).

**Response (200):**

```json
{
    "data": {
        "scores": {
            "overall": 85,
            "skills": 90,
            "experience": 80,
            "education": 85
        },
        "strengths": [
            {"en": "1. Strong Laravel experience", "ar": "1. خبرة قوية في لارافيل"},
            {"en": "2. High demand skills", "ar": "2. مهارات عالية الطلب"}
        ],
        "weaknesses": [
            {"en": "1. Lack of Cloud experience", "ar": "1. نقص الخبرة في السحابة"}
        ],
        "gaps": [
            {"en": "1. Missing Docker certification", "ar": "1. شهادة دوكر مفقودة"}
        ],
        "ai_explanation": "Summary text...",
        "analyzed_at": "2024-01-10T12:00:00Z"
    }
}
```

---

### Get Skill Gaps

#### `GET /api/cvs/{id}/skill-gaps`

الحصول على فجوات المهارات مقارنة بمتطلبات السوق أو وظيفة محددة.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `job_ad_id` | integer | Filter gaps for a specific job |

**Response (200):**

```json
{
    "data": [
        {
            "skill": { "name": "Docker" },
            "gap_type": "MISSING",
            "required_level": "Intermediate"
        }
    ],
    "summary": {
        "total_gaps": 5
    }
}
```

---

---

## 📊 Summary Statistics

| Category                  | Endpoints Count |
| ------------------------- | --------------- |
| Health Check              | 1               |
| Authentication            | 12              |
| Profile                   | 4               |
| Notifications             | 3               |
| CV Management             | 11              |
| Favorites                 | 3               |
| Applications (Job Seeker) | 4               |
| Employer Jobs             | 7               |
| Employer Courses          | 8               |
| Companies                 | 4               |
| Courses (Job Seeker)      | 5               |
| Messaging                 | 3               |
| Skills & Languages        | 3               |
| Jobs (Public)             | 2               |
| Admin Panel               | 7               |
| **Total**                 | **77**          |

---

## 🛡️ Admin Routes

> Requires Admin role

### Get All Users

#### `GET /api/admin/users`

الحصول على قائمة جميع المستخدمين مع إمكانية البحث والتصفية.

**Query Parameters:**

| Parameter | Type   | Description                                 |
| --------- | ------ | ------------------------------------------- |
| search    | string | بحث بالاسم أو البريد                        |
| role      | string | تصفية بالدور (JobSeeker, Employer, Admin)   |
| status    | string | تصفية بالحالة (active, blocked, unverified) |
| page      | int    | رقم الصفحة                                  |

**Response (200):**

```json
{
    "data": [
        {
            "UserID": 1,
            "FullName": "Ahmed Mohammed",
            "Email": "ahmed@example.com",
            "Phone": "+967771234567",
            "IsVerified": true,
            "IsBlocked": false,
            "CreatedAt": "2024-01-01T00:00:00Z",
            "roles": [{ "RoleID": 1, "RoleName": "JobSeeker" }]
        }
    ],
    "meta": { "current_page": 1, "total": 150 }
}
```

---

### Get User Statistics

#### `GET /api/admin/users/statistics`

الحصول على إحصائيات المستخدمين.

**Response (200):**

```json
{
    "data": {
        "total_users": 500,
        "job_seekers": 350,
        "employers": 145,
        "verified_users": 480,
        "blocked_users": 5,
        "new_users_today": 12,
        "new_users_this_week": 45
    }
}
```

---

### Create User (Admin)

#### `POST /api/admin/users`

إنشاء مستخدم جديد بواسطة المدير.

**Request Body:**

```json
{
    "full_name": "New User", // required
    "email": "user@example.com", // required | unique
    "password": "Password123", // required | min:8
    "role": "JobSeeker", // required | in:JobSeeker,Employer,Admin
    "phone": "+967771234567" // optional
}
```

**Response (201):**

```json
{
    "message": "تم إنشاء المستخدم بنجاح",
    "data": { "UserID": 25, "FullName": "New User", ... }
}
```

---

### Block User

#### `POST /api/admin/users/{id}/block`

حظر مستخدم.

**Request Body:**

```json
{
    "reason": "مخالفة شروط الاستخدام" // required | سبب الحظر
}
```

**Response (200):**

```json
{
    "message": "تم حظر المستخدم بنجاح",
    "data": {
        "UserID": 1,
        "IsBlocked": true
    }
}
```

---

### Sync Market Trends (Admin)

#### `POST /api/admin/market-trends/sync`

🔒 **Requires Authentication (Admin)**

تحديث بيانات اتجاهات السوق يدوياً من خلال تحليل الوظائف الحالية.

**Response (200):**

```json
{
    "message": "Market trends sync completed successfully.",
    "output": "Command output logs..."
}
```
        "IsBlocked": true,
        "BlockedAt": "2024-01-10",
        "BlockReason": "..."
    }
}
```

---

### Unblock User

#### `POST /api/admin/users/{id}/unblock`

إلغاء حظر مستخدم.

**Response (200):**

```json
{
    "message": "تم إلغاء حظر المستخدم بنجاح"
}
```

---

## 📚 Employer Courses Routes

> Requires Employer role

### Get My Courses

#### `GET /api/employer/courses`

الحصول على دورات الشركة الحالية.

**Response (200):**

```json
{
    "data": [
        {
            "CourseAdID": 1,
            "CourseTitle": "Laravel Advanced Course",
            "Topics": "API, Testing, Security",
            "Duration": "40 hours",
            "Location": "Online",
            "Trainer": "Dr. Ahmed",
            "Fees": 150.0,
            "StartDate": "2024-02-01",
            "Status": "Active",
            "enrollments_count": 25
        }
    ]
}
```

---

### Create Course Ad

#### `POST /api/employer/courses`

إنشاء إعلان دورة جديدة.

**Request Body:**

```json
{
    "title": "React Masterclass", // required
    "topics": "Components, Hooks, Redux",
    "duration": "30 hours",
    "location": "Online",
    "trainer": "Mohammed Ali",
    "fees": 200.0,
    "start_date": "2024-03-01",
    "status": "Draft" // Draft, Active, Closed
}
```

**Response (201):**

```json
{
    "message": "تم إنشاء إعلان الدورة بنجاح",
    "data": { "CourseAdID": 5, ... }
}
```

---

### Publish Course

#### `POST /api/employer/courses/{id}/publish`

نشر الدورة وجعلها مرئية للباحثين.

**Response (200):**

```json
{
    "message": "تم نشر الدورة بنجاح",
    "data": { "Status": "Active" }
}
```

---

### Close Course

#### `POST /api/employer/courses/{id}/close`

إغلاق الدورة ومنع التسجيل الجديد.

**Response (200):**

```json
{
    "message": "تم إغلاق الدورة بنجاح",
    "data": { "Status": "Closed" }
}
```

---

### Get Course Enrollments

#### `GET /api/employer/courses/{id}/enrollments`

الحصول على قائمة المسجلين في الدورة.

**Response (200):**

```json
{
    "data": [
        {
            "EnrollmentID": 1,
            "CourseAdID": 5,
            "JobSeekerID": 10,
            "EnrolledAt": "2024-01-15T10:00:00Z",
            "Status": "Enrolled",
            "job_seeker": {
                "user": {
                    "FullName": "Ahmed Mohammed",
                    "Email": "ahmed@example.com",
                    "Phone": "+967771234567"
                }
            }
        }
    ]
}
```

---

### Notify Course Participants

#### `POST /api/employer/courses/{id}/notify`

إرسال إشعار لجميع المسجلين في الدورة.

**Request Body:**

```json
{
    "title": "تذكير بموعد الدورة", // required
    "message": "نذكركم بأن الدورة تبدأ غداً", // required
    "type": "reminder" // optional: reminder, update, cancellation, info
}
```

**Response (200):**

```json
{
    "message": "تم إرسال الإشعار إلى 25 مشترك بنجاح",
    "sent_count": 25
}
```

---

## 👤 Job Seeker Courses Routes

### Get My Enrollments

#### `GET /api/courses/my-enrollments`

الحصول على الدورات المسجل بها.

**Response (200):**

```json
{
    "data": [
        {
            "EnrollmentID": 1,
            "CourseAdID": 5,
            "EnrolledAt": "2024-01-15",
            "Status": "Enrolled",
            "course": {
                "CourseTitle": "Laravel Advanced",
                "StartDate": "2024-02-01",
                "company": { "CompanyName": "Tech Academy" }
            }
        }
    ]
}
```

---

### Enroll in Course

#### `POST /api/courses/{id}/enroll`

التسجيل في دورة.

**Response (201):**

```json
{
    "message": "تم التسجيل في الدورة بنجاح"
}
```

**Error Response (422):**

```json
{
    "message": "أنت مسجّل مسبقاً في هذه الدورة"
}
```

---

### Unenroll from Course

#### `DELETE /api/courses/{id}/enroll`

إلغاء التسجيل من دورة.

**Response (200):**

```json
{
    "message": "تم إلغاء التسجيل من الدورة بنجاح"
}
```

---

## 🔑 Authentication Header

For all protected routes, include the Bearer token in the Authorization header:

```
Authorization: Bearer {your-sanctum-token}
```

---

## ⚠️ Common Error Responses

### 401 Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

### 403 Forbidden

```json
{
    "message": "الحساب غير مفعّل، يرجى التحقق من بريدك الإلكتروني لتفعيل الحساب"
}
```

### 404 Not Found

```json
{
    "message": "Resource not found"
}
```

### 422 Validation Error

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["البريد الإلكتروني مطلوب"],
        "password": ["كلمة المرور يجب أن تكون 8 أحرف على الأقل"]
    }
}
```

---

> 📅 **Last Updated**: January 2024  
> 📦 **API Version**: 2.0
