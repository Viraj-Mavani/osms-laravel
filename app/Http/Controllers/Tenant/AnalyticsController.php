<?php

namespace App\Http\Controllers\Tenant;

use App\Exports\LedgerExport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class AnalyticsController extends Controller
{
    /** Hard ceiling for "show all" lists and exports so wide ranges stay bounded. */
    private const MAX_ROWS = 5000;

    /** Resolve the [from, to] range from the request (default: last 30 days). */
    private function range(Request $request): array
    {
        $to = $this->parseDate($request->query('to')) ?? now();
        $from = $this->parseDate($request->query('from')) ?? now()->subDays(30);

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function parseDate(?string $s): ?Carbon
    {
        if (! $s) {
            return null;
        }
        try {
            return Carbon::parse($s);
        } catch (\Exception) {
            return null;
        }
    }

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        // Delivered orders in range → revenue, COGS, profit, top brands.
        $delivered = Order::with('items.inventory:id,cost_price,brand')
            ->where('status', 'delivered')
            ->whereBetween('updated_at', [$from, $to])
            ->get();

        $revenue = (float) $delivered->sum('total_amount');

        $cogs = (float) $delivered->sum(function (Order $o) {
            return $o->items->sum(fn ($i) => (float) ($i->inventory->cost_price ?? 0) * $i->quantity);
        });

        $profit = $revenue - $cogs;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
        $ordersCount = $delivered->count();

        // Top brands by revenue
        $brandMap = [];
        foreach ($delivered as $o) {
            foreach ($o->items as $it) {
                $brand = trim((string) ($it->inventory->brand ?? '')) ?: '—';
                $brandMap[$brand] ??= ['quantity' => 0, 'revenue' => 0.0];
                $brandMap[$brand]['quantity'] += $it->quantity;
                $brandMap[$brand]['revenue'] += (float) $it->unit_price * $it->quantity;
            }
        }
        $topBrands = collect($brandMap)
            ->map(fn ($v, $brand) => ['brand' => $brand] + $v)
            ->sortByDesc('revenue')->take(10)->values();

        // Ledger — orders in range (default 50; "show all" raises the cap but never removes it).
        $showAllLedger = $request->boolean('ledger_all');
        $ledger = Order::with('patient:id,name')
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit($showAllLedger ? self::MAX_ROWS : 50)
            ->get();

        // Pending dues — outstanding balances (default 50; "show all" raises the cap but never removes it).
        $showAllDues = $request->boolean('dues_all');
        $dues = Order::with('patient:id,name,phone')
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->orderByDesc('balance_due')
            ->limit($showAllDues ? self::MAX_ROWS : 50)
            ->get();

        $stats = compact('revenue', 'cogs', 'profit', 'margin', 'ordersCount');
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        return view('tenant.analytics.index', compact(
            'stats', 'topBrands', 'ledger', 'dues', 'fromStr', 'toStr', 'showAllLedger', 'showAllDues',
        ));
    }

    /** Export the ledger for the current range to Excel. */
    public function exportLedger(Request $request)
    {
        [$from, $to] = $this->range($request);

        return Excel::download(
            new LedgerExport($from, $to),
            'ledger-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx',
        );
    }
}
