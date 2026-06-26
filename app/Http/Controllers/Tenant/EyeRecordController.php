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
}
