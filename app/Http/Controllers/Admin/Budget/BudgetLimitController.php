<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Budget;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A placeholder for the budget limits screen, which is not built yet.
 *
 * It exists so the feature flag guards something real rather than a name in a
 * constant. Replace the page when the screen is written; the flag and the route
 * stay as they are.
 */
final class BudgetLimitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Budget/Limits/Index');
    }
}
