<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('tenant.settings.edit', compact('tenant'));
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:2048'], // 2MB
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $logoUrl = $tenant->logo_url;

        // Replace logo when a new file is supplied (mirrors OnboardingController's guard).
        if ($request->hasFile('logo')) {
            try {
                $path = $request->file('logo')->store('logos', 'public');
                $logoUrl = Storage::url($path);
            } catch (\Throwable $e) {
                return back()->withInput()
                    ->with('error', 'We could not upload your logo. Please try again.');
            }
        } elseif ($request->boolean('remove_logo')) {
            $logoUrl = null;
        }

        $tenant->update([
            'store_name' => $validated['store_name'],
            'tax_id' => $validated['tax_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'logo_url' => $logoUrl,
        ]);

        return redirect()->route('tenant.settings.edit')
            ->with('status', 'Store settings updated.');
    }
}
