<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Budget;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class BudgetLimitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Budget/Limits/Index');
    }
}
