<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->can('dashboard.view')) {
            return view('dashboard.admin', [
                'stats' => $this->dashboard->adminStats(),
                'recentCustomers' => $this->dashboard->recentCustomers(),
                'recentDesigns' => $this->dashboard->recentDesigns(),
                'popularThemes' => $this->dashboard->popularThemes(),
                'cardGeneration' => $this->dashboard->monthlyCardGeneration(),
                'revenue' => $this->dashboard->monthlyRevenue(),
                'engagement' => $this->dashboard->engagementTrend(),
                'canSeeRevenue' => $user->can('revenue.view'),
            ]);
        }

        $customer = $user->customerProfile;

        return view('dashboard.customer', [
            'customer' => $customer,
            'stats' => $customer ? $this->dashboard->customerStats($customer) : null,
            'engagement' => $customer ? $this->dashboard->engagementTrend(30, $customer->id) : null,
            'designs' => $customer ? $customer->cardDesigns()->latest()->limit(4)->get() : collect(),
        ]);
    }
}
