<?php

namespace App\Filament\Widgets;

use App\Models\BusinessEvent;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class CheckoutFailedWidget extends Widget
{
    protected static ?int $sort = 10;
    protected int | string | array $columnSpan = 'full';
    protected string $view = 'filament.widgets.checkout-failed';

    public function getRows(): array
    {
        return DB::table('business_events')
            ->where('event_type', BusinessEvent::CHECKOUT_FAILED)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(ANY_VALUE(payload), '$.reason')) as fail_reason, COUNT(*) as total")
            ->groupByRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.reason'))")
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->toArray();
    }
}
