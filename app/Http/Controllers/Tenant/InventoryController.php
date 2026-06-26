<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryRequest;
use App\Models\Inventory;
use App\Services\SkuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $stock = (string) $request->query('stock', '');

        $items = Inventory::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('brand', 'like', "%{$q}%")
                        ->orWhere('model_name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('item_type', $type))
            ->when($stock === 'low', fn ($query) => $query->whereColumn('stock_qty', '<=', 'min_alert_qty'))
            ->when($stock === 'out', fn ($query) => $query->where('stock_qty', 0))
            ->orderBy('brand')
            ->limit(200)
            ->get();

        return view('tenant.inventory.index', compact('items', 'q', 'type', 'stock'));
    }

    public function create(): View
    {
        return view('tenant.inventory.create');
    }

    public function store(InventoryRequest $request, SkuService $sku): RedirectResponse
    {
        $data = $request->validated();
        $data['sku'] = $sku->generateSku($data['item_type'], $data['brand'] ?? null);
        $data['barcode'] = $sku->generateBarcode();

        Inventory::create($data);

        return redirect()->route('tenant.inventory.index')->with('status', 'Item added to inventory.');
    }

    public function edit(Inventory $inventory): View
    {
        return view('tenant.inventory.edit', ['item' => $inventory]);
    }

    public function update(InventoryRequest $request, Inventory $inventory): RedirectResponse
    {
        // SKU + barcode are immutable after creation (matches the original).
        $inventory->update($request->validated());

        return redirect()->route('tenant.inventory.index')->with('status', 'Item updated.');
    }

    /**
     * AJAX barcode/SKU lookup (replaces the Supabase client query in BarcodeScanModal).
     */
    public function scan(Request $request): JsonResponse
    {
        $code = trim((string) $request->query('q', ''));

        if ($code === '') {
            return response()->json(['found' => false]);
        }

        $item = Inventory::where('barcode', $code)
            ->orWhere('sku', $code)
            ->first(['id', 'sku', 'brand', 'model_name', 'stock_qty', 'selling_price']);

        if (! $item) {
            return response()->json(['found' => false, 'code' => $code]);
        }

        return response()->json([
            'found' => true,
            'item' => [
                'id' => $item->id,
                'sku' => $item->sku,
                'brand' => $item->brand,
                'model_name' => $item->model_name,
                'stock_qty' => $item->stock_qty,
                'selling_price' => (float) $item->selling_price,
                'edit_url' => route('tenant.inventory.edit', $item->id),
            ],
        ]);
    }
}
