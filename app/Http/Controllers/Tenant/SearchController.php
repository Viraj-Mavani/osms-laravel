<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['patients' => [], 'inventory' => [], 'orders' => []]);
        }

        $isPhoneLike = preg_match('/^[+0-9 ()\-]{4,}$/', $q) && preg_match('/\d{4,}/', $q);

        $patients = Patient::query()
            ->when($isPhoneLike,
                fn ($query) => $query->where('phone', 'like', "%{$q}%"),
                fn ($query) => $query->where(fn ($s) =>
                    $s->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")),
            )
            ->limit(8)
            ->get(['id', 'name', 'phone']);

        $inventory = Inventory::query()
            ->where(fn ($s) => $s
                ->where('brand', 'like', "%{$q}%")
                ->orWhere('model_name', 'like', "%{$q}%")
                ->orWhere('sku', 'like', "%{$q}%")
                ->orWhere('barcode', 'like', "%{$q}%"))
            ->limit(8)
            ->get(['id', 'sku', 'barcode', 'brand', 'model_name', 'stock_qty']);

        $orders = Order::with('patient:id,name')
            ->whereHas('patient', fn ($s) => $s->where('name', 'like', "%{$q}%"))
            ->latest()
            ->limit(6)
            ->get(['id', 'patient_id', 'total_amount', 'balance_due', 'status', 'created_at']);

        return response()->json([
            'patients' => $patients->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'phone' => $p->phone,
                'url' => route('tenant.patients.show', $p),
            ]),
            'inventory' => $inventory->map(fn ($i) => [
                'id' => $i->id, 'sku' => $i->sku, 'brand' => $i->brand,
                'model_name' => $i->model_name, 'stock_qty' => $i->stock_qty,
                'url' => route('tenant.inventory.edit', $i),
            ]),
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id, 'status' => $o->status,
                'total_amount' => (float) $o->total_amount, 'balance_due' => (float) $o->balance_due,
                'patient_name' => $o->patient?->name,
                'url' => route('tenant.orders.show', $o),
            ]),
        ]);
    }
}
