<?php

namespace App\Exports;

use App\Models\Inventory;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * FG-Export — inventory list export honouring the active index filters
 * (search / type / stock). Tenant-scoped by the global scope; a hard row
 * ceiling keeps a wide export bounded in memory (mirrors LedgerExport).
 */
class InventoryExport implements FromCollection, WithHeadings, WithMapping
{
    /** Hard ceiling so a large catalogue can't pull an unbounded set into memory. */
    private const MAX_ROWS = 5000;

    public function __construct(
        private string $q = '',
        private string $type = '',
        private string $stock = '',
    ) {}

    public function collection()
    {
        // Same filter shape as InventoryController::index (tenant scope applies).
        return Inventory::query()
            ->when($this->q !== '', function ($query) {
                $query->where(function ($sub) {
                    $sub->where('brand', 'like', "%{$this->q}%")
                        ->orWhere('model_name', 'like', "%{$this->q}%")
                        ->orWhere('sku', 'like', "%{$this->q}%")
                        ->orWhere('barcode', 'like', "%{$this->q}%");
                });
            })
            ->when($this->type !== '', fn ($query) => $query->where('item_type', $this->type))
            ->when($this->stock === 'low', fn ($query) => $query->whereColumn('stock_qty', '<=', 'min_alert_qty'))
            ->when($this->stock === 'out', fn ($query) => $query->where('stock_qty', 0))
            ->orderBy('brand')
            ->limit(self::MAX_ROWS)
            ->get();
    }

    public function headings(): array
    {
        return ['SKU', 'Barcode', 'Type', 'Brand', 'Model', 'Cost', 'Selling', 'Stock', 'Min alert'];
    }

    public function map($item): array
    {
        return [
            $item->sku,
            $item->barcode,
            $item->type_label,
            $item->brand,
            $item->model_name,
            (float) $item->cost_price,
            (float) $item->selling_price,
            (int) $item->stock_qty,
            (int) $item->min_alert_qty,
        ];
    }
}
