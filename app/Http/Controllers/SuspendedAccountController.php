<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SuspendedAccountController extends Controller
{
    public function __invoke(): View
    {
        return view('auth.suspended');
    }
}
