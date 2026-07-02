<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * FG-Export — customer list export honouring the active index search filter.
 * Tenant-scoped by the global scope; a hard row ceiling keeps a wide export
 * bounded in memory (mirrors LedgerExport).
 */
class CustomersExport implements FromCollection, WithHeadings, WithMapping
{
    /** Hard ceiling so a large customer book can't pull an unbounded set into memory. */
    private const MAX_ROWS = 5000;

    public function __construct(
        private string $q = '',
    ) {}

    public function collection()
    {
        // Same filter shape as CustomerController::index (tenant scope applies).
        return Customer::query()
            ->when($this->q !== '', function ($query) {
                $query->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->q}%")
                        ->orWhere('phone', 'like', "%{$this->q}%");
                });
            })
            ->latest()
            ->limit(self::MAX_ROWS)
            ->get();
    }

    public function headings(): array
    {
        return ['Name', 'Phone', 'Age', 'Gender', 'Added'];
    }

    public function map($customer): array
    {
        return [
            $customer->name,
            $customer->phone,
            $customer->age,
            $customer->gender,
            $customer->created_at->format('Y-m-d'),
        ];
    }
}
