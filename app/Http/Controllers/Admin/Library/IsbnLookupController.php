<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Library;

use App\Actions\Library\FindOrCreateNamedRecord;
use App\Actions\Library\ResolveIsbn;
use App\Http\Controllers\Controller;
use App\Models\Publisher;
use App\Models\Writer;
use App\Support\Isbn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class IsbnLookupController extends Controller
{
    /**
     * The two model classes this endpoint may create.
     *
     * Models are unguarded, so without this map the relation endpoint would be
     * a way to create any record in the application.
     *
     * @var array<string, class-string<Model>>
     */
    private const array RELATIONS = [
        'writer' => Writer::class,
        'publisher' => Publisher::class,
    ];

    public function store(Request $request, ResolveIsbn $resolveIsbn): JsonResponse
    {
        /** @var array{isbn: string} $validated */
        $validated = $request->validate(['isbn' => ['required', 'string', 'max:20']]);

        $isbn = Isbn::tryFrom($validated['isbn']);

        if (! $isbn instanceof Isbn) {
            throw ValidationException::withMessages([
                'isbn' => 'That is not a valid ISBN. Check the digits and try again.',
            ]);
        }

        return response()->json($resolveIsbn->handle($isbn));
    }

    public function relation(Request $request, FindOrCreateNamedRecord $findOrCreate): JsonResponse
    {
        /** @var array{type: string, name: string} $validated */
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(self::RELATIONS))],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $record = $findOrCreate->handle(self::RELATIONS[$validated['type']], $validated['name']);

        return response()->json([
            'id' => $record->getKey(),
            'name' => $record->getAttribute('name'),
        ]);
    }
}
