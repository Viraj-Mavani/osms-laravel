<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEyeRecordRequest;
use App\Models\EyeRecord;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EyeRecordController extends Controller
{
    public function create(Patient $patient): View
    {
        return view('tenant.eye-records.create', compact('patient'));
    }

    public function store(StoreEyeRecordRequest $request, Patient $patient): RedirectResponse
    {
        $data = $request->validated();
        $data['patient_id'] = $patient->id;
        $data['recorded_by'] = $request->user()->id;

        EyeRecord::create($data);

        return redirect()
            ->route('tenant.patients.show', $patient)
            ->with('status', 'Eye record saved.');
    }

    public function edit(EyeRecord $record): View
    {
        $patient = $record->patient;

        return view('tenant.eye-records.edit', compact('patient', 'record'));
    }

    public function update(StoreEyeRecordRequest $request, EyeRecord $record): RedirectResponse
    {
        $record->update($request->validated());

        return redirect()
            ->route('tenant.patients.show', $record->patient)
            ->with('status', 'Eye record updated.');
    }

    public function destroy(EyeRecord $record): RedirectResponse
    {
        $patient = $record->patient;
        $record->delete();

        return redirect()
            ->route('tenant.patients.show', $patient)
            ->with('status', 'Eye record deleted.');
    }
}
