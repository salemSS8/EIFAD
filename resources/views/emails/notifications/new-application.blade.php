<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقديم جديد على وظيفتك</title>
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
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
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
        .info-card {
            background: #f8fafc;
            border: 1px dashed #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
        }
        .job-tag {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .highlight {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
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
            width: 120px;
            flex-shrink: 0;
        }
        .detail-value {
            color: #4f46e5;
            font-weight: 700;
        }
        .btn-container {
            text-align: center;
            margin-top: 35px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 35px;
            font-weight: 700;
            font-size: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2), 0 2px 4px -1px rgba(15, 23, 42, 0.1);
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
                <p class="tagline">بوابتكم لتوظيف الكفاءات وتحقيق النمو</p>
            </div>
            
            <!-- Content -->
            <div class="content">
                <h1 class="greeting">شركاءنا في النجاح 👋</h1>
                <p class="intro-text">
                    نود إعلامكم بأن هناك طلباً جديداً للتقديم قد تم استلامه للوظيفة الشاغرة لديكم.
                </p>
                
                <!-- Info Card -->
                <div class="info-card">
                    <div class="job-tag">إعلان الوظيفة</div>
                    <div class="highlight">{{ $jobTitle }}</div>
                    
                    <div class="detail-row">
                        <span class="detail-label">اسم المتقدم:</span>
                        <span class="detail-value">{{ $applicantName }}</span>
                    </div>
                </div>
                
                <p class="intro-text" style="text-align: center; margin-bottom: 10px;">
                    يرجى زيارة لوحة التحكم لمراجعة السيرة الذاتية للمتقدم وتقييم الطلب.
                </p>
                
                <!-- Action Button -->
                <div class="btn-container">
                    <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}" class="btn">مراجعة طلب التقديم</a>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p class="footer-text">
                    تصلكم هذه الرسالة بناءً على تفضيلات البريد الإلكتروني لحساب شركتكم في منصة إفاد.
                </p>
                <div class="footer-copyright">
                    &copy; {{ date('Y') }} منصة إفاد. جميع الحقوق محفوظة.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
