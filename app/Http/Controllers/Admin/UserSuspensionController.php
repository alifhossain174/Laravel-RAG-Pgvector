<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserSuspensionController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot suspend your own admin account.');
        }

        $user->forceFill([
            'is_suspended' => true,
        ])->save();

        return back()->with('success', 'User account suspended.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->forceFill([
            'is_suspended' => false,
        ])->save();

        return back()->with('success', 'User account restored.');
    }
}
