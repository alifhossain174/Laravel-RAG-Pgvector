<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Document;
use App\Services\GeminiRateLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        return $this->viewChat($request);
    }

    public function create(Request $request): View
    {
        return $this->viewChat($request, openCreateConversationModal: true);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['required', Rule::in(Conversation::SCOPES)],
            'document_ids' => ['array', 'min:1', Rule::requiredIf(fn () => $request->input('scope') === Conversation::SCOPE_SELECTED)],
            'document_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('documents', 'id')
                    ->where('user_id', $request->user()->id)
                    ->where('status', Document::STATUS_READY),
            ],
        ]);

        $conversation = DB::transaction(function () use ($request, $validated): Conversation {
            $conversation = $request->user()->conversations()->create([
                'title' => $validated['title'],
                'scope' => $validated['scope'],
            ]);

            if ($conversation->scope === Conversation::SCOPE_SELECTED) {
                $conversation->documents()->sync($validated['document_ids'] ?? []);
            }

            return $conversation;
        });

        return redirect()
            ->route('chat.show', $conversation)
            ->with('success', 'Conversation created successfully.');
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        return $this->viewChat($request, $conversation);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return redirect()
            ->route('chat.index')
            ->with('success', 'Conversation deleted successfully.');
    }

    private function viewChat(
        Request $request,
        ?Conversation $activeConversation = null,
        bool $openCreateConversationModal = false
    ): View {
        $search = trim((string) $request->query('search'));

        $conversations = $request->user()
            ->conversations()
            ->withCount(['documents', 'messages'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('title', $this->caseInsensitiveLikeOperator($query), '%'.$search.'%');
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $readyDocuments = $request->user()
            ->documents()
            ->where('status', Document::STATUS_READY)
            ->latest()
            ->get();

        $scopedDocuments = collect();

        if ($activeConversation) {
            $activeConversation->load([
                'documents',
                'messages' => fn ($query) => $query->oldest(),
            ]);

            $scopedDocuments = $activeConversation->usesAllDocuments()
                ? $readyDocuments
                : $activeConversation->documents;
        }

        return view('chat.show', [
            'conversations' => $conversations,
            'conversation' => $activeConversation,
            'documents' => $readyDocuments,
            'scopedDocuments' => $scopedDocuments,
            'search' => $search,
            'openCreateConversationModal' => $openCreateConversationModal,
            'geminiQuota' => app(GeminiRateLimitService::class)->chatSnapshot(),
        ]);
    }

    private function caseInsensitiveLikeOperator($query): string
    {
        return $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
