<?php

namespace App\Exports;

use App\Models\Order;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LedgerExport implements FromCollection, WithHeadings, WithMapping
{
    /** Hard ceiling so a wide date range can't pull an unbounded set into memory. */
    private const MAX_ROWS = 5000;

    public function __construct(
        private Carbon $from,
        private Carbon $to,
    ) {}

    public function collection()
    {
        // Tenant global scope keeps this export scoped to the current store.
        return Order::with('customer:id,name')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->latest()
            ->limit(self::MAX_ROWS)
            ->get();
    }

    public function headings(): array
    {
        return ['Date', 'Receipt #', 'Customer', 'Status', 'Total', 'Advance', 'Balance'];
    }

    public function map($order): array
    {
        return [
            $order->created_at->format('Y-m-d'),
            strtoupper(substr($order->id, 0, 8)),
            $order->customer?->name ?? '—',
            $order->status_label,
            (float) $order->total_amount,
            (float) $order->advance_paid,
            (float) $order->balance_due,
        ];
    }
}
