<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Library;

use App\Actions\Library\ResolveIsbn;
use App\Http\Controllers\Controller;
use App\Support\Isbn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

final class IsbnLookupController extends Controller
{
    public function __invoke(Request $request, ResolveIsbn $resolveIsbn): JsonResponse
    {
        /** @var array{isbn: string} $validated */
        $validated = $request->validate(['isbn' => ['required', 'string', 'max:20']]);

        $isbn = Isbn::tryFrom($validated['isbn']);

        if (! $isbn instanceof Isbn) {
            throw ValidationException::withMessages([
                'isbn' => 'That is not a valid ISBN. Check the digits and try again.',
            ]);
        }

        $resolved = $resolveIsbn->handle($isbn);
        $cover = $resolved['image'];

        return response()->json(['data' => [
            'duplicate' => $resolved['duplicate'],
            'values' => (object) $resolved['values'],
            'writer' => $resolved['writer'],
            'publisher' => $resolved['publisher'],
            'image' => is_string($cover) ? URL::to(Storage::disk('public')->url($cover)) : null,
        ]]);
    }
}
