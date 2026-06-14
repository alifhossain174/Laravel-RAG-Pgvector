<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Illuminate\View\View;

class AdminSystemHealthController extends Controller
{
    public function index(SystemHealthService $health): View
    {
        return view('admin.system-health.index', $health->report());
    }
}
