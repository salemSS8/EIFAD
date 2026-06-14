<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>وظيفة جديدة تناسبك</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            background-color: #f8fafc;
            font-family: 'Cairo', 'Tajawal', 'Segoe UI', Tahoma, Geneva, sans-serif;
            -webkit-font-smoothing: antialiased;
            text-size-adjust: none;
        }
        .wrapper {
            width: 100%;
            background-color: #f8fafc;
            padding: 40px 20px;
            box-sizing: border-box;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
            border: 1px solid #f1f5f9;
        }
        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            padding: 40px 30px;
            text-align: center;
            color: #ffffff;
        }
        .logo {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .tagline {
            font-size: 13px;
            opacity: 0.9;
            margin: 0;
            font-weight: 500;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 0;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        .intro-text {
            font-size: 15px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .job-card {
            background: #f8fafc;
            border: 1px dashed #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
        }
        .job-title {
            font-size: 20px;
            font-weight: 700;
            color: #4f46e5;
            margin-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 12px;
        }
        .detail-row {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            font-size: 14px;
            color: #475569;
        }
        .detail-row:last-child {
            margin-bottom: 0;
        }
        .detail-label {
            font-weight: 600;
            color: #1e293b;
            width: 100px;
            flex-shrink: 0;
        }
        .detail-value {
            color: #64748b;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            background-color: #eff6ff;
            color: #2563eb;
            font-size: 12px;
            font-weight: 600;
            border-radius: 9999px;
        }
        .btn-container {
            text-align: center;
            margin-top: 35px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 35px;
            font-weight: 700;
            font-size: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2), 0 2px 4px -1px rgba(79, 70, 229, 0.1);
            transition: all 0.2s ease-in-out;
        }
        .footer {
            background-color: #f8fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #f1f5f9;
        }
        .footer-text {
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .footer-copyright {
            margin-top: 15px;
            font-size: 11px;
            color: #cbd5e1;
        }
        @media only screen and (max-width: 600px) {
            .wrapper {
                padding: 15px 10px;
            }
            .content {
                padding: 30px 20px;
            }
            .btn {
                display: block;
                padding: 14px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="logo">إفاد • Eifad</div>
                <p class="tagline">منصتك المهنية المتكاملة لتحقيق طموحاتك</p>
            </div>
            
            <!-- Content -->
            <div class="content">
                <h1 class="greeting">مرحباً {{ $userName }} 👋</h1>
                <p class="intro-text">
                    لقد رصدنا وظيفة شاغرة جديدة تم نشرها للتو وتتطابق مع مسماك الوظيفي وخبراتك المسجلة في سيرتك الذاتية.
                </p>
                
                <!-- Job Card -->
                <div class="job-card">
                    <div class="job-title">{{ $jobTitle }}</div>
                    
                    <div class="detail-row">
                        <span class="detail-label">الشركة:</span>
                        <span class="detail-value">{{ $companyName }}</span>
                    </div>
                    
                    @if($location)
                    <div class="detail-row">
                        <span class="detail-label">الموقع:</span>
                        <span class="detail-value">{{ $location }}</span>
                    </div>
                    @endif
                    
                    @if($workType)
                    <div class="detail-row">
                        <span class="detail-label">نوع العمل:</span>
                        <span class="detail-value"><span class="badge">{{ $workType }}</span></span>
                    </div>
                    @endif
                    
                    @if($workplaceType)
                    <div class="detail-row">
                        <span class="detail-label">بيئة العمل:</span>
                        <span class="detail-value">{{ $workplaceType }}</span>
                    </div>
                    @endif
                </div>
                
                <p class="intro-text" style="text-align: center; margin-bottom: 10px;">
                    سارع بالتقديم على هذه الفرصة قبل انتهاء فترة الإعلان!
                </p>
                
                <!-- Action Button -->
                <div class="btn-container">
                    <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}" class="btn">عرض الوظيفة والتقديم</a>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p class="footer-text">
                    تصلك هذه الرسالة بناءً على تفضيلات الإشعارات الخاصة بحسابك في منصة إفاد. يمكنك تعديل إعدادات البريد الإلكتروني في أي وقت من خلال لوحة التحكم الخاصة بك.
                </p>
                <div class="footer-copyright">
                    &copy; {{ date('Y') }} منصة إفاد. جميع الحقوق محفوظة.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
