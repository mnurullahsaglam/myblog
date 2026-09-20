<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filled in by the next task; the routes exist now so invite links can be
 * generated and asserted.
 */
final class InviteController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        abort(404);
    }

    public function store(Request $request, string $token): Response
    {
        abort(404);
    }
}
