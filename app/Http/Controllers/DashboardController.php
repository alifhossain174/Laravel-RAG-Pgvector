<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Message;
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
        $totalQuestions = Message::query()
            ->where('role', Message::ROLE_USER)
            ->whereHas('conversation', fn ($query) => $query->where('user_id', $request->user()->id))
            ->count();

        return view('dashboard', [
            'totalDocuments' => $totalDocuments,
            'readyDocuments' => $readyDocuments,
            'pendingDocuments' => $pendingDocuments,
            'totalQuestions' => $totalQuestions,
            'recentDocuments' => $recentDocuments,
        ]);
    }
}
