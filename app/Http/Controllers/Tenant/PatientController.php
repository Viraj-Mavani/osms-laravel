<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $patients = Patient::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('tenant.patients.index', compact('patients', 'search'));
    }

    public function create(): View
    {
        return view('tenant.patients.create');
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        $patient = Patient::create($request->validated());

        return redirect()
            ->route('tenant.patients.show', $patient)
            ->with('status', 'Patient added.');
    }

    public function show(Patient $patient): View
    {
        $patient->load([
            'eyeRecords',
            'orders',
        ]);

        // Merge eye records + orders into one timeline, newest first.
        $timeline = $patient->eyeRecords->map(fn ($r) => [
            'kind' => 'rx',
            'at' => $r->created_at,
            'data' => $r,
        ])->concat($patient->orders->map(fn ($o) => [
            'kind' => 'order',
            'at' => $o->created_at,
            'data' => $o,
        ]))->sortByDesc('at')->values();

        return view('tenant.patients.show', compact('patient', 'timeline'));
    }
}
