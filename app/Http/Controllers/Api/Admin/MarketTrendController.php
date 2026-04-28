<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AI\Models\SyncLog;
use App\Http\Controllers\Controller;
use App\Jobs\SyncMarketTrendsJob;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Admin Market Trend Controller - Manage data synchronization.
 */
class MarketTrendController extends Controller
{
    /**
     * Trigger a direct sync of market trends.
     */
    #[OA\Post(
        path: '/admin/market-trends/sync',
        operationId: 'syncMarketTrendsAdmin',
        tags: ['Admin', 'Market Trends'],
        summary: 'Trigger direct sync',
        description: 'Initiates a synchronous aggregation of market trends data. Restricted to Admins.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'Sync completed')]
    public function sync(Request $request, \App\Domain\AI\Services\SyncMarketTrendsService $service)
    {
        $service->aggregate(auth()->id());

        return response()->json([
            'message' => 'Market trends synchronization completed successfully.',
        ]);
    }

    /**
     * Get the recent sync logs.
     */
    #[OA\Get(
        path: '/admin/market-trends/logs',
        operationId: 'getMarketTrendSyncLogs',
        tags: ['Admin', 'Market Trends'],
        summary: 'Get sync history',
        description: 'Returns list of recent synchronization job logs. Restricted to Admins.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'Sync logs retrieved')]
    public function logs()
    {
        $logs = SyncLog::latest()->limit(20)->get();

        return response()->json($logs);
    }
}
