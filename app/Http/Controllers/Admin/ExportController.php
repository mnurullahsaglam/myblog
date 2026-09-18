<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunResourceExport;
use App\Support\AdminNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ExportController extends Controller
{
    public function __construct(private readonly AdminNotifier $notifier) {}

    public function store(string $resource): RedirectResponse
    {
        abort_unless(array_key_exists($resource, RunResourceExport::EXPORTS), 404);

        // Run inline so the download link can be handed over straight away. On a
        // personal-scale dataset this is fast; dispatch it if it stops being so.
        $path = (new RunResourceExport($resource))->handle();

        $this->notifier->success(
            'Export ready',
            URL::signedRoute('admin.exports.download', ['path' => $path]),
        );

        return back();
    }

    public function download(Request $request): StreamedResponse
    {
        $path = $request->string('path')->toString();

        // A signed URL proves intent, not innocence: without this, any file on
        // the private disk could be fetched by editing the path.
        if (! str_starts_with($path, 'exports/') || str_contains($path, '..')) {
            throw new AccessDeniedHttpException('That file is not downloadable.');
        }

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
