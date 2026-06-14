<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function index(SettingsService $settings): View
    {
        return view('admin.settings.index', [
            'groups' => $settings->groupedSettings(),
        ]);
    }

    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $validated = $request->validate($settings->validationRules());

        $settings->update($validated['settings'] ?? []);

        return back()->with('success', 'Settings updated.');
    }
}
