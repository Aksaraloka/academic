@foreach ($survey->questions as $question)
    @php $key = 'answers.'.$question->id; @endphp
    <x-card wire:key="q-{{ $question->id }}">
        <fieldset class="space-y-3">
            <legend class="text-base font-semibold">
                <span class="tabular-nums text-text-muted">{{ $loop->iteration }}.</span> {{ $question->text }}
                @if ($question->is_required)<span class="text-danger" aria-hidden="true"> *</span><span class="sr-only"> (wajib)</span>@endif
            </legend>

            @switch($question->type)
                @case(\App\Enums\SurveyQuestionType::Rating)
                    <div class="grid grid-cols-5 gap-2">
                        @foreach ([1 => 'Sangat kurang', 2 => 'Kurang', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat baik'] as $value => $label)
                            <label class="flex min-h-14 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-lg border px-1 text-center transition has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary {{ (string) ($answers[$question->id] ?? '') === (string) $value ? 'border-primary bg-primary/15 text-primary' : 'border-border hover:bg-surface-muted' }}">
                                <input type="radio" class="sr-only" wire:model.live="answers.{{ $question->id }}" value="{{ $value }}">
                                <span class="text-lg font-bold tabular-nums">{{ $value }}</span>
                                <span class="hidden text-[10px] leading-tight sm:block">{{ $label }}</span>
                                <span class="sr-only sm:hidden">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="flex justify-between text-xs text-text-muted sm:hidden"><span>1 = Sangat kurang</span><span>5 = Sangat baik</span></p>
                    @break

                @case(\App\Enums\SurveyQuestionType::Single)
                    @foreach ($question->options as $option)
                        <label class="flex min-h-11 items-center gap-3 text-sm">
                            <input type="radio" class="size-5 accent-primary" wire:model="answers.{{ $question->id }}" value="{{ $option }}"> {{ $option }}
                        </label>
                    @endforeach
                    @break

                @case(\App\Enums\SurveyQuestionType::Multiple)
                    @foreach ($question->options as $option)
                        <label class="flex min-h-11 items-center gap-3 text-sm">
                            <input type="checkbox" class="size-5 accent-primary" wire:model="answers.{{ $question->id }}" value="{{ $option }}"> {{ $option }}
                        </label>
                    @endforeach
                    @break

                @default
                    <label for="a-{{ $question->id }}" class="sr-only">Jawaban</label>
                    <textarea id="a-{{ $question->id }}" wire:model="answers.{{ $question->id }}" rows="3" maxlength="2000" class="input" @error($key) aria-invalid="true" @enderror></textarea>
            @endswitch

            @error($key)<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
        </fieldset>
    </x-card>
@endforeach
