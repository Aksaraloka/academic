<div class="space-y-6">
    <x-page-header title="Bantuan survey" subtitle="Pertanyaan yang sering diajukan seputar pengisian kuesioner." />

    <x-card :padding="false">
        <div class="divide-y divide-border">
            @foreach ($faqs as [$question, $answer])
                <details class="group" wire:key="faq-{{ $loop->index }}">
                    <summary class="flex min-h-14 list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold hover:bg-surface-muted sm:px-5 [&::-webkit-details-marker]:hidden">
                        {{ $question }}
                        <x-icon name="chevron-down" class="size-4 shrink-0 text-text-muted transition group-open:rotate-180" />
                    </summary>
                    <p class="px-4 pb-4 text-sm leading-relaxed text-text-muted sm:px-5">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </x-card>

    <x-card title="Masih butuh bantuan?">
        <p class="text-sm text-text-muted">Tim BAAK siap membantu. Sampaikan kendalamu lewat menu Masukan dan lampirkan tangkapan layar bila perlu.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <x-button :href="route('feedback.create')" wire:navigate icon="chat-bubble-left-right">Kirim masukan</x-button>
            <x-button :href="route('survey.index')" wire:navigate variant="secondary" icon="clipboard-document-list">Lihat kuesioner</x-button>
        </div>
    </x-card>
</div>
