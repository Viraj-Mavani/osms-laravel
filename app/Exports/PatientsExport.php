<?php

namespace App\Exports;

use App\Models\Patient;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * FG-Export — patient list export honouring the active index search filter.
 * Tenant-scoped by the global scope; a hard row ceiling keeps a wide export
 * bounded in memory (mirrors LedgerExport).
 */
class PatientsExport implements FromCollection, WithHeadings, WithMapping
{
    /** Hard ceiling so a large patient book can't pull an unbounded set into memory. */
    private const MAX_ROWS = 5000;

    public function __construct(
        private string $q = '',
    ) {}

    public function collection()
    {
        // Same filter shape as PatientController::index (tenant scope applies).
        return Patient::query()
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

    public function map($patient): array
    {
        return [
            $patient->name,
            $patient->phone,
            $patient->age,
            $patient->gender,
            $patient->created_at->format('Y-m-d'),
        ];
    }
}
