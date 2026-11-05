<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        $user = $request->user();

        if ($user->can('dashboard.view')) {
            $stats = $dashboard->adminStats();
            if (! $user->can('revenue.view')) {
                unset($stats['revenue']);
            }

            return response()->json(['data' => [
                'scope' => 'platform',
                'stats' => $stats,
                'card_generation' => $dashboard->monthlyCardGeneration(),
                'engagement' => $dashboard->engagementTrend(),
            ]]);
        }

        $customer = $user->customerProfile;
        abort_unless($customer, 404, 'No customer profile is linked to this account.');

        return response()->json(['data' => [
            'scope' => 'customer',
            'stats' => $dashboard->customerStats($customer),
            'engagement' => $dashboard->engagementTrend(30, $customer->id),
        ]]);
    }
}
