<?php

namespace App\Filament\Widgets;

use App\Models\BusinessEvent;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class SearchNoResultWidget extends Widget
{
    protected static ?int $sort = 9;
    protected int | string | array $columnSpan = 'full';
    protected string $view = 'filament.widgets.search-no-result';

    public function getRows(): array
    {
        return DB::table('business_events')
            ->where('event_type', BusinessEvent::SEARCH_NO_RESULT)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(ANY_VALUE(payload), '$.query')) as search_query, COUNT(*) as total")
            ->groupByRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.query'))")
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->toArray();
    }
}
