<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\BookExport;
use App\Exports\PublisherExport;
use App\Exports\ResourceExport;
use App\Exports\WriterExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RunResourceExport implements ShouldQueue
{
    use Queueable;

    /**
     * @var array<string, class-string<ResourceExport>>
     */
    public const EXPORTS = [
        'books' => BookExport::class,
        'publishers' => PublisherExport::class,
        'writers' => WriterExport::class,
    ];

    public function __construct(public readonly string $resource) {}

    /**
     * Streams through the rows so memory stays flat however many there are.
     *
     * @return string the stored path on the private disk
     */
    public function handle(): string
    {
        $exportClass = self::EXPORTS[$this->resource] ?? null;

        if ($exportClass === null) {
            throw new RuntimeException("No export is defined for [{$this->resource}].");
        }

        $export = new $exportClass;
        $path = $export->filename();

        $handle = fopen('php://temp/maxmemory:2097152', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Could not open a temporary stream for the export.');
        }

        fputcsv($handle, $export->headings());

        $export->query()->lazy(500)->each(function (Model $record) use ($handle, $export): void {
            fputcsv($handle, $export->row($record));
        });

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('local')->put($path, $contents === false ? '' : $contents);

        return $path;
    }
}
