<?php

declare(strict_types=1);

namespace App\Actions\Exports;

use App\Exports\BookExport;
use App\Exports\PublisherExport;
use App\Exports\ResourceExport;
use App\Exports\WriterExport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Build a resource's CSV on the private disk.
 */
final class ExportResource
{
    /**
     * @var array<string, class-string<ResourceExport>>
     */
    public const array EXPORTS = [
        'books' => BookExport::class,
        'publishers' => PublisherExport::class,
        'writers' => WriterExport::class,
    ];

    /**
     * Streams through the rows so memory stays flat however many there are.
     *
     * @return string the stored path on the private disk
     */
    public function handle(string $resource): string
    {
        $exportClass = self::EXPORTS[$resource] ?? null;

        throw_if($exportClass === null, RuntimeException::class, "No export is defined for [{$resource}].");

        $export = new $exportClass;
        $path = $export->filename();

        $handle = fopen('php://temp/maxmemory:2097152', 'r+');

        throw_if($handle === false, RuntimeException::class, 'Could not open a temporary stream for the export.');

        fputcsv($handle, $export->headings(), escape: '\\');

        $export->query()->lazy(500)->each(function (Model $record) use ($handle, $export): void {
            fputcsv($handle, $export->row($record), escape: '\\');
        });

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('local')->put($path, $contents === false ? '' : $contents);

        return $path;
    }
}
