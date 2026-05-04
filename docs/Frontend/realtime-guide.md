# دليل الربط اللحظي (Real-time Integration Guide)

يوضح هذا الملف كيفية ربط الواجهة الأمامية (Frontend) مع نظام الإشعارات والدردشة في مشروع "إيفاد" باستخدام Laravel Reverb و Laravel Echo.

---

## 1. البنية التحتية (Laravel Reverb & Echo)

المشروع يستخدم **Laravel Reverb** كمحرك Websocket عالي الأداء.

### إعدادات البيئة (.env)
يجب التأكد من تطابق الإعدادات التالية في الفرونت إند:
- `VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"`
- `VITE_REVERB_HOST="${REVERB_HOST}"`
- `VITE_REVERB_PORT="${REVERB_PORT}"`
- `VITE_REVERB_SCHEME="${REVERB_SCHEME}"`

---

## 2. نظام الإشعارات (Notifications)

### الحالة الراهنة
النظام مكتمل برمجياً ويدعم التنبيهات اللحظية.

### الربط البرمجي (API)
- **جلب الإشعارات**: `GET /api/notifications` (Paginanted)
- **تحديد كـ مقروء**: `PUT /api/notifications/{id}/read`
- **تحديد الكل كـ مقروء**: `PUT /api/notifications/read-all`
- **إعدادات الإشعارات**: `GET/PUT /api/notifications/settings`

### الربط اللحظي (Websockets)
يتم الاشتراك في قناة المستخدم الخاصة للاستماع للإشعارات الجديدة:
- **القناة**: `private-App.Models.User.{UserID}`
- **الحدث**: `App\Events\NotificationReceived`

**مثال برمجي (Javascript):**
```javascript
Echo.private(`App.Models.User.${userId}`)
    .listen('.App\\Events\\NotificationReceived', (e) => {
        console.log('New Notification:', e.notification);
        // إظهار التنبيه وتحديث العداد
    });
```

---

## 3. نظام الدردشة (Chat / Messaging)

### الحالة الراهنة
تم تنظيم النظام في `ChatController` مخصص ليدعم المحادثات الخاصة والرسائل اللحظية.

### الربط البرمجي (API)
- **قائمة المحادثات**: `GET /api/conversations` (تعيد آخر رسالة لكل محادثة)
- **بدء محادثة جديدة**: `POST /api/conversations` (ترسل `user_id` الخاص بالطرف الآخر)
- **جلب الرسائل**: `GET /api/conversations/{id}/messages`
- **إرسال رسالة**: `POST /api/conversations/{id}/messages`

### الربط اللحظي (Websockets)
يتم الاشتراك في قناة المحادثة للاستماع للرسائل الجديدة:
- **القناة**: `private-conversation.{ConversationID}`
- **الحدث**: `App\Events\MessageSent`

**مثال برمجي (Javascript):**
```javascript
Echo.private(`conversation.${conversationId}`)
    .listen('.App\\Events\\MessageSent', (e) => {
        console.log('New Message:', e.message);
        // إضافة الرسالة إلى واجهة الدردشة
    });
```

---

## 4. ملاحظات هامة للمطورين

1.  **المصادقة (Authentication)**: جميع قنوات الـ Websocket هي قنوات خاصة (`PrivateChannels`) وتتطلب أن يكون المستخدم مسجلاً للدخول عبر `Sanctum`.
2.  **الأحداث (Events)**: لاحظ وجود النقطة `.` قبل اسم الحدث في `Echo.listen` إذا كان الاسم كاملاً (Full Namespace).
3.  **تحديث الحالة**: يفضل تحديث واجهة المستخدم (UI) محلياً فور الإرسال (Optimistic UI) ثم استلام التأكيد من السيرفر أو عبر الـ Websocket.
