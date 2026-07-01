<?php

namespace App\Http\Controllers\Tenant;

use App\Exports\PatientsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function edit(Patient $patient): View
    {
        return view('tenant.patients.edit', compact('patient'));
    }

    public function update(StorePatientRequest $request, Patient $patient): RedirectResponse
    {
        $patient->update($request->validated());

        return redirect()
            ->route('tenant.patients.show', $patient)
            ->with('status', 'Patient updated.');
    }

    /** FG-Export — download the (filtered) patient list as an XLSX file. */
    public function export(Request $request): BinaryFileResponse
    {
        $export = new PatientsExport(trim((string) $request->query('q', '')));

        return Excel::download($export, 'patients-' . now()->format('Ymd-His') . '.xlsx');
    }

    /** FG-Delete — archived (soft-deleted) patients, restorable for 30 days. */
    public function trash(): View
    {
        $patients = Patient::onlyTrashed()
            ->latest('deleted_at')
            ->paginate(50);

        return view('tenant.patients.trash', compact('patients'));
    }

    /**
     * FG-Delete — archive a patient (soft delete). Blocked while the patient has
     * order history, so a receipt can never be orphaned; archive junk/test rows.
     */
    public function destroy(Patient $patient): RedirectResponse
    {
        if ($patient->orders()->exists()) {
            return back()->with('error', 'This patient has order history and cannot be archived.');
        }

        $patient->delete();

        return redirect()
            ->route('tenant.patients.index')
            ->with('status', 'Patient archived. You can restore it within 30 days.');
    }

    /** FG-Delete — restore an archived patient. */
    public function restore(Patient $patient): RedirectResponse
    {
        $patient->restore();

        return redirect()
            ->route('tenant.patients.show', $patient)
            ->with('status', 'Patient restored.');
    }

    /** FG-Delete — permanently delete an archived patient (irreversible). */
    public function forceDelete(Patient $patient): RedirectResponse
    {
        $patient->forceDelete();

        return redirect()
            ->route('tenant.patients.trash')
            ->with('status', 'Patient permanently deleted.');
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
