<?php

namespace App\Filament\Exports;

use App\Models\Writer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use OpenSpout\Common\Entity\Style\Style;

class WriterExporter extends Exporter
{
    protected static ?string $model = Writer::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label('İsim'),
            ExportColumn::make('bio')
                ->label('Biyografi'),
            ExportColumn::make('birth_date')
                ->label('Doğum Tarihi'),
            ExportColumn::make('death_date')
                ->label('Ölüm Tarihi'),
            ExportColumn::make('birth_place')
                ->label('Doğum Yeri'),
            ExportColumn::make('death_place')
                ->label('Ölüm Yeri'),
            ExportColumn::make('is_finished')
                ->label('Durum')
                ->formatStateUsing(fn(Writer $record) => $record ? 'Tamamlandı' : 'Tamamlanmadı'),
            ExportColumn::make('books_count')->counts('books')
                ->label('Kitap Sayısı'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Yazarların dışarı aktarılması tamamlandı. ' . number_format($export->successful_rows) . ' yazar dışarı aktarıldı.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' yazar dışarı aktarılamadı.';
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
