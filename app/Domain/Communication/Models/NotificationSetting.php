<?php

namespace App\Domain\Communication\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    protected $table = 'notification_settings';
    protected $primaryKey = 'SettingID';

    protected $fillable = [
        'UserID',
        'EmailNotifications',
        'PushNotifications',
        'JobAlerts',
        'ApplicationUpdates',
        'MarketingEmails',
    ];

    protected function casts(): array
    {
        return [
            'EmailNotifications' => 'boolean',
            'PushNotifications' => 'boolean',
            'JobAlerts' => 'boolean',
            'ApplicationUpdates' => 'boolean',
            'MarketingEmails' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }
}
