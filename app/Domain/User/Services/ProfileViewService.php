<?php

namespace App\Domain\User\Services;

use App\Domain\User\Models\ProfileViewDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileViewService
{
    /**
     * Track a profile view based on the unique device hash.
     *
     * @param int $viewedUserId
     * @param Request $request
     * @return void
     */
    public function trackDeviceView(int $viewedUserId, Request $request): void
    {
        $userAgent = $request->userAgent();

        // 1. Ignore bots
        if ($this->isBot($userAgent)) {
            return;
        }

        // 2. Generate unique device hash (IP + User Agent)
        $ip = $request->ip();
        $deviceHash = hash('sha256', $ip . $userAgent);

        // 3. Update existing device or create new one (Unique constraint handles safety)
        ProfileViewDevice::updateOrCreate(
            [
                'viewed_user_id' => $viewedUserId,
                'device_hash' => $deviceHash,
            ],
            [
                'viewer_id' => Auth::id(),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'last_seen_at' => now(),
            ]
        );
    }

    /**
     * Check if the user agent belongs to a bot.
     *
     * @param string|null $userAgent
     * @return bool
     */
    private function isBot(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return true;
        }

        $bots = [
            'bot', 'crawler', 'spider', 'slurp', 'googlebot', 'yandexbot', 
            'bingbot', 'baiduspider', 'facebookexternalhit', 'twitterbot'
        ];

        foreach ($bots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }
}
