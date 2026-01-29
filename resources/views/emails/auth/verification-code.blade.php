<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تفعيل الحساب</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .code { font-size: 32px; font-weight: bold; color: #2d3748; letter-spacing: 5px; margin: 20px 0; background: #edf2f7; padding: 15px; border-radius: 4px; display: inline-block;}
        .footer { margin-top: 30px; font-size: 12px; color: #718096; }
    </style>
</head>
<body>
    <div class="container">
        <h2>مرحباً بك في منصة إفاد</h2>
        <p>شكراً لتسجيلك معنا. يرجى استخدام الرمز التالي لتفعيل حسابك:</p>
        
        <div class="code">{{ $code }}</div>
        
        <p>هذا الرمز صالح لمدة 60 دقيقة.</p>
        <p>إذا لم تقم بطلب هذا الرمز، يمكنك تجاهل هذا البريد الإلكتروني.</p>
        
        <div class="footer">
            &copy; {{ date('Y') }} منصة إفاد. جميع الحقوق محفوظة.
        </div>
    </div>
</body>
</html>
