<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Exports\ExportResource;
use App\Contracts\NotifiesAdmin;
use App\Enums\Area;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ExportController extends Controller
{
    public function __construct(private readonly NotifiesAdmin $notifier) {}

    public function store(Request $request, string $resource, ExportResource $exportResource): RedirectResponse
    {
        $area = ExportResource::areaFor($resource);

        abort_if(! $area instanceof Area, 404);
        abort_unless($request->user()?->can('access-area', $area) ?? false, 404);

        $path = $exportResource->handle($resource);

        $this->notifier->success(
            'Export ready',
            URL::temporarySignedRoute('admin.exports.download', now()->addHour(), ['path' => $path]),
        );

        return back();
    }

    public function download(Request $request): StreamedResponse
    {
        $path = $request->string('path')->toString();

        throw_if(! str_starts_with($path, 'exports/') || str_contains($path, '..'), AccessDeniedHttpException::class, 'That file is not downloadable.');

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
