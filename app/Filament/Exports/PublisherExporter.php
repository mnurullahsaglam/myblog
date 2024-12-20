<?php

namespace App\Filament\Exports;

use App\Models\Publisher;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use OpenSpout\Common\Entity\Style\Style;

class PublisherExporter extends Exporter
{
    protected static ?string $model = Publisher::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label('İsim'),
            ExportColumn::make('books_count')->counts('books')
                ->label('Kitap Sayısı'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Yayınevlerinin dışarı aktarılması tamamlandı. ' . number_format($export->successful_rows) . ' yazar dışarı aktarıldı.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' yayınevi dışarı aktarılamadı.';
        }

        return $body;
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontUnderline();
    }
}
