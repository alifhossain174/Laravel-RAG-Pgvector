<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $baseQuery = $request->user()->documents();

        $totalDocuments = (clone $baseQuery)->count();
        $readyDocuments = (clone $baseQuery)->where('status', Document::STATUS_READY)->count();
        $pendingDocuments = (clone $baseQuery)
            ->whereIn('status', [
                Document::STATUS_UPLOADED,
                Document::STATUS_PROCESSING,
                Document::STATUS_TEXT_EXTRACTED,
                Document::STATUS_CHUNKED,
                Document::STATUS_EMBEDDED,
            ])
            ->count();
        $recentDocuments = (clone $baseQuery)->latest()->limit(5)->get();

        return view('dashboard', [
            'totalDocuments' => $totalDocuments,
            'readyDocuments' => $readyDocuments,
            'pendingDocuments' => $pendingDocuments,
            'recentDocuments' => $recentDocuments,
        ]);
    }
}
