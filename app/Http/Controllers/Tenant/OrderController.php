<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\EyeRecord;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderController extends Controller
{
    /** Kanban board grouped by status. */
    public function index(): View
    {
        $orders = Order::with('patient:id,name,phone')
            ->withCount('items')
            ->latest()
            ->get()
            ->groupBy('status');

        return view('tenant.orders.index', compact('orders'));
    }

    public function create(Request $request): View
    {
        $patients = Patient::orderBy('name')->get(['id', 'name', 'phone']);
        $inventory = Inventory::where('stock_qty', '>', 0)
            ->orderBy('brand')
            ->get(['id', 'sku', 'barcode', 'brand', 'model_name', 'selling_price', 'stock_qty']);

        $selectedPatientId = $request->query('patient');

        return view('tenant.orders.create', compact('patients', 'inventory', 'selectedPatientId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'eye_record_id' => ['nullable', 'exists:eye_records,id'],
            'advance_paid' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_id' => ['required', 'exists:inventory,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        // Ensure the patient belongs to this tenant (global scope enforces it).
        Patient::findOrFail($validated['patient_id']);

        // If a prescription is attached, it must belong to this tenant *and* this
        // patient — the `exists` rule above is unscoped, so re-check it here.
        if (! empty($validated['eye_record_id'])) {
            EyeRecord::where('patient_id', $validated['patient_id'])
                ->findOrFail($validated['eye_record_id']);
        }

        $order = DB::transaction(function () use ($validated) {
            // Total quantity requested per item (collapses duplicate lines so the
            // stock guard can't be bypassed by splitting one item across two rows).
            $wanted = [];
            foreach ($validated['items'] as $line) {
                $id = $line['inventory_id'];
                $wanted[$id] = ($wanted[$id] ?? 0) + (int) $line['quantity'];
            }

            // Load + lock each item once. The tenant scope still applies, so a
            // cross-tenant inventory_id simply won't be found (404 below).
            $inventories = Inventory::lockForUpdate()
                ->findMany(array_keys($wanted))
                ->keyBy('id');

            // Guard against overselling before we mutate anything.
            foreach ($wanted as $id => $qty) {
                $inv = $inventories->get($id);

                if (! $inv) {
                    abort(404);
                }

                if ($qty > $inv->stock_qty) {
                    throw ValidationException::withMessages([
                        'items' => "Only {$inv->stock_qty} × {$inv->brand} {$inv->model_name} in stock (requested {$qty}).",
                    ]);
                }
            }

            // Build line items with the price resolved server-side (never trust the client).
            $total = 0;
            $lines = [];
            foreach ($validated['items'] as $line) {
                $inv = $inventories->get($line['inventory_id']);
                $qty = (int) $line['quantity'];
                $unit = (float) $inv->selling_price;
                $total += $unit * $qty;
                $lines[] = ['inventory_id' => $inv->id, 'quantity' => $qty, 'unit_price' => $unit];
            }

            $advance = min((float) ($validated['advance_paid'] ?? 0), $total);

            $order = Order::create([
                'patient_id' => $validated['patient_id'],
                'eye_record_id' => $validated['eye_record_id'] ?? null,
                'status' => 'pending',
                'total_amount' => $total,
                'advance_paid' => $advance,
            ]);

            $order->items()->createMany($lines);

            // Draw down stock now that the order is committed.
            foreach ($wanted as $id => $qty) {
                $inventories->get($id)->decrement('stock_qty', $qty);
            }

            return $order;
        });

        return redirect()->route('tenant.orders.show', $order)->with('status', 'Order created.');
    }

    public function show(Order $order): View
    {
        $order->load([
            'patient',
            'eyeRecord',
            'items.inventory:id,sku,brand,model_name',
        ]);
        $tenant = $order->tenant;

        return view('tenant.orders.show', compact('order', 'tenant'));
    }

    /** Advance an order to the next workflow status. */
    public function updateStatus(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,ready_for_pickup,delivered'],
        ]);

        $order->update(['status' => $validated['status']]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'status' => $order->status]);
        }

        return back()->with('status', 'Order updated.');
    }

    /** Printable / downloadable PDF receipt (DomPDF). */
    public function pdf(Order $order)
    {
        $order->load(['patient', 'eyeRecord', 'items.inventory:id,sku,brand,model_name']);
        $tenant = $order->tenant;

        $pdf = Pdf::loadView('tenant.orders.receipt-pdf', compact('order', 'tenant'))
            ->setPaper('a4');

        return $pdf->stream('receipt-' . substr($order->id, 0, 8) . '.pdf');
    }

    /** JSON list of a patient's eye records for the order builder. */
    public function eyeRecords(Patient $patient): JsonResponse
    {
        $records = $patient->eyeRecords()->get(['id', 'created_at'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => 'Rx · ' . $r->created_at->format('d M Y'),
            ]);

        return response()->json($records);
    }
}
