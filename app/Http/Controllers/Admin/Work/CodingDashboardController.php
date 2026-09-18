<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Http\Controllers\Controller;
use App\Support\WakaTime\DashboardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CodingDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Work/CodingDashboard', [
            'data' => DashboardData::for($request->string('range')->toString()),
            'ranges' => [
                ['value' => '7', 'label' => '7D'],
                ['value' => '14', 'label' => '14D'],
                ['value' => '30', 'label' => '30D'],
                ['value' => '90', 'label' => '90D'],
                ['value' => 'all', 'label' => 'All'],
            ],
        ]);
    }
}
