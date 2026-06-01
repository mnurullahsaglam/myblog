<?php

namespace App\Filament\Resources\WakaTimeSummaries\Schemas;

use Filament\Schemas\Schema;

class WakaTimeSummaryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        // Header widgets (stats + breakdown doughnuts) and the entries relation manager
        // carry the detail on the view page, so the infolist itself stays empty.
        return $schema->components([]);
    }
}
