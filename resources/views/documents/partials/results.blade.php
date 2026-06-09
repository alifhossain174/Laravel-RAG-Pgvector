<section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($documents as $document)
        @include('partials.document-card', ['document' => $document])
    @empty
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center md:col-span-2 xl:col-span-3">
            <p class="font-semibold text-slate-950">No documents found</p>
            <p class="mt-2 text-sm text-slate-500">Upload a PDF or adjust the current filter.</p>
        </div>
    @endforelse
</section>

@if ($documents->hasPages())
    <div class="mt-6" data-live-pagination>
        {{ $documents->links() }}
    </div>
@endif
