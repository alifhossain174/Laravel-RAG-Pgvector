<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use App\Services\LimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    private const FILTERS = [
        'admin' => 'Admin users',
        'normal' => 'Normal users',
        'suspended' => 'Suspended',
        'active' => 'Active',
        'verified' => 'Verified',
        'unverified' => 'Unverified',
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'filter' => ['nullable', 'string', 'in:admin,normal,suspended,active,verified,unverified'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $selectedFilter = $validated['filter'] ?? null;
        $query = User::query()
            ->withCount(['documents', 'conversations'])
            ->latest();

        if ($search !== '') {
            $operator = $this->searchOperator();
            $like = '%'.$search.'%';

            $query->where(function ($query) use ($operator, $like): void {
                $query
                    ->where('name', $operator, $like)
                    ->orWhere('email', $operator, $like);
            });
        }

        match ($selectedFilter) {
            'admin' => $query->where('is_admin', true),
            'normal' => $query->where('is_admin', false),
            'suspended' => $query->where('is_suspended', true),
            'active' => $query->where('is_suspended', false),
            'verified' => $query->whereNotNull('email_verified_at'),
            'unverified' => $query->whereNull('email_verified_at'),
            default => null,
        };

        return view('admin.users.index', [
            'users' => $query->paginate(15)->withQueryString(),
            'filters' => self::FILTERS,
            'search' => $search,
            'selectedFilter' => $selectedFilter,
        ]);
    }

    public function show(User $user, LimitService $limits): View
    {
        $user->load(['limit'])->loadCount(['documents', 'conversations']);

        $statusCounts = Document::query()
            ->where('user_id', $user->id)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalMessages = Message::query()
            ->whereHas('conversation', fn ($query) => $query->where('user_id', $user->id))
            ->count();

        return view('admin.users.show', [
            'user' => $user,
            'limitValues' => $limits->limitsFor($user),
            'limitDefaults' => $limits->defaults(),
            'profileMetrics' => [
                ['label' => 'Documents', 'value' => $user->documents_count, 'helper' => 'Uploaded records'],
                ['label' => 'Conversations', 'value' => $user->conversations_count, 'helper' => 'Chat workspaces'],
                ['label' => 'Messages', 'value' => $totalMessages, 'helper' => 'Conversation messages'],
                ['label' => 'Storage Used', 'value' => $this->formatBytes((int) $user->documents()->sum('file_size')), 'helper' => 'Approximate uploaded file size'],
            ],
            'documentSummary' => collect(Document::STATUSES)->map(fn (string $status): array => [
                'label' => str($status)->replace('_', ' ')->title()->toString(),
                'value' => (int) ($statusCounts[$status] ?? 0),
            ]),
            'latestDocuments' => $user->documents()
                ->latest()
                ->limit(5)
                ->get(['id', 'ulid', 'user_id', 'title', 'original_filename', 'mime_type', 'file_size', 'status', 'created_at']),
            'latestConversations' => $user->conversations()
                ->withCount('messages')
                ->latest()
                ->limit(5)
                ->get(['id', 'ulid', 'user_id', 'title', 'scope', 'created_at']),
        ]);
    }

    public function promote(User $user): RedirectResponse
    {
        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return back()->with('success', "{$user->name} is now an admin.");
    }

    public function demote(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot remove your own admin role.');
        }

        $user->forceFill([
            'is_admin' => false,
        ])->save();

        return back()->with('success', "{$user->name} is now a normal user.");
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot suspend your own admin account.');
        }

        $user->forceFill([
            'is_suspended' => true,
        ])->save();

        return back()->with('success', "{$user->name} has been suspended.");
    }

    public function activate(User $user): RedirectResponse
    {
        $user->forceFill([
            'is_suspended' => false,
        ])->save();

        return back()->with('success', "{$user->name} has been activated.");
    }

    private function searchOperator(): string
    {
        return User::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $index);

        return number_format($value, $index === 0 ? 0 : 1).' '.$units[$index];
    }
}
