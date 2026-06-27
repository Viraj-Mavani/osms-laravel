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

        // Ledger — all orders in range (default limit to 50; allow "show all" via query param).
        $showAllLedger = $request->boolean('ledger_all');
        $ledger = Order::with('patient:id,name')
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->when(! $showAllLedger, fn ($q) => $q->limit(50))
            ->get();

        // Pending dues — all outstanding balances (default limit to 50; allow "show all" via query param).
        $showAllDues = $request->boolean('dues_all');
        $dues = Order::with('patient:id,name,phone')
            ->where('balance_due', '>', 0)
            ->orderByDesc('balance_due')
            ->when(! $showAllDues, fn ($q) => $q->limit(50))
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
