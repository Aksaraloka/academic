<div class="space-y-6">
    <x-page-header title="Kurikulum" subtitle="Mata kuliah wajib dan pilihan per semester, beserta prasyarat, untuk tiap prodi dan angkatan." />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field label="Program studi" name="programId">
            <select id="programId" wire:model.live="programId" class="input">
                <option value="">Pilih program studi</option>
                @foreach ($programs as $program)<option value="{{ $program->id }}">{{ $program->label() }}</option>@endforeach
            </select>
        </x-field>
        @if ($programId !== '')
            <x-field label="Kurikulum" name="curriculumId">
                <select id="curriculumId" wire:model.live="curriculumId" class="input">
                    <option value="">Pilih kurikulum</option>
                    @foreach ($curricula as $option)<option value="{{ $option->id }}">{{ $option->name }} · {{ $option->batchLabel() }}{{ $option->is_active ? '' : ' (nonaktif)' }}</option>@endforeach
                </select>
            </x-field>
        @endif
    </div>

    @if ($programId !== '')
        <form wire:submit="createCurriculum">
            <x-card title="Kurikulum baru">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="Nama" name="name" required><input id="name" type="text" wire:model="name" class="input" placeholder="Kurikulum 2026" @error('name') aria-invalid="true" @enderror></x-field>
                    <x-field label="Angkatan mulai" name="batch_from" required><input id="batch_from" type="number" wire:model="batch_from" class="input" inputmode="numeric" placeholder="2026"></x-field>
                    <x-field label="Angkatan akhir" name="batch_to" hint="Kosongkan bila masih berlaku."><input id="batch_to" type="number" wire:model="batch_to" class="input" inputmode="numeric"></x-field>
                </div>
                <div class="mt-4"><x-button type="submit" icon="plus" wire:loading.attr="disabled" wire:target="createCurriculum">Buat kurikulum</x-button></div>
            </x-card>
        </form>
    @endif

    @if ($curriculum)
        <x-card>
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-base font-semibold">{{ $curriculum->name }}</p>
                    <p class="text-sm text-text-muted">{{ $curriculum->batchLabel() }} · {{ $totals['credits'] }} SKS ({{ $totals['required'] }} wajib)</p>
                </div>
                <x-badge :variant="$curriculum->is_active ? 'success' : 'neutral'">{{ $curriculum->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                <x-button size="sm" variant="secondary" wire:click="toggleActive">{{ $curriculum->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</x-button>
            </div>
        </x-card>

        <div class="grid gap-4 lg:grid-cols-2">
            <form wire:submit="addSubject">
                <x-card title="Tambah mata kuliah yang sudah ada">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-3">
                            <x-field label="Mata kuliah" name="subjectId" required>
                                <select id="subjectId" wire:model="subjectId" class="input">
                                    <option value="">Pilih mata kuliah</option>
                                    @foreach ($availableSubjects as $subject)<option value="{{ $subject->id }}">{{ $subject->code }} · {{ $subject->name }} ({{ $subject->credits }} SKS)</option>@endforeach
                                </select>
                            </x-field>
                        </div>
                        <x-field label="Semester" name="semester" required><input id="semester" type="number" min="1" max="14" wire:model="semester" class="input" inputmode="numeric"></x-field>
                        <x-field label="Jenis" name="type" required>
                            <select id="type" wire:model="type" class="input">@foreach ($types as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
                        </x-field>
                    </div>
                    <div class="mt-4"><x-button type="submit" icon="plus">Tambahkan</x-button></div>
                </x-card>
            </form>

            <form wire:submit="createSubject">
                <x-card title="Buat mata kuliah baru">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="Kode" name="newCode" required><input id="newCode" type="text" wire:model="newCode" class="input" @error('newCode') aria-invalid="true" @enderror></x-field>
                        <div class="sm:col-span-2"><x-field label="Nama" name="newName" required><input id="newName" type="text" wire:model="newName" class="input" @error('newName') aria-invalid="true" @enderror></x-field></div>
                        <x-field label="SKS" name="newCredits" required><input id="newCredits" type="number" min="1" max="12" wire:model="newCredits" class="input" inputmode="numeric"></x-field>
                    </div>
                    <p class="mt-2 text-xs text-text-muted">Memakai semester dan jenis yang dipilih di kartu sebelah.</p>
                    <div class="mt-4"><x-button type="submit" icon="plus">Buat dan tambahkan</x-button></div>
                </x-card>
            </form>
        </div>

        <form wire:submit="addPrerequisite">
            <x-card title="Prasyarat">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Mata kuliah" name="prerequisiteFor" required>
                        <select id="prerequisiteFor" wire:model="prerequisiteFor" class="input">
                            <option value="">Pilih</option>
                            @foreach ($items as $item)<option value="{{ $item->subject_id }}">Smt {{ $item->semester }} · {{ $item->subject->name }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Harus lulus lebih dulu" name="prerequisiteId" required>
                        <select id="prerequisiteId" wire:model="prerequisiteId" class="input">
                            <option value="">Pilih</option>
                            @foreach ($items as $item)<option value="{{ $item->subject_id }}">Smt {{ $item->semester }} · {{ $item->subject->name }}</option>@endforeach
                        </select>
                    </x-field>
                </div>
                <div class="mt-4"><x-button type="submit" icon="plus">Tambah prasyarat</x-button></div>
            </x-card>
        </form>

        @forelse ($bySemester as $semesterNumber => $rows)
            <x-card :title="'Semester '.$semesterNumber.' · '.$rows->sum(fn ($row) => $row->subject->credits).' SKS'" :padding="false" wire:key="sem-{{ $semesterNumber }}">
                @foreach ($rows as $row)
                    <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="item-{{ $row->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $row->subject->code }} · {{ $row->subject->name }}</p>
                            <p class="flex flex-wrap items-center gap-1 text-xs text-text-muted">
                                {{ $row->subject->credits }} SKS
                                @foreach ($row->subject->prerequisites as $prerequisite)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-surface-muted px-2 py-0.5">Prasyarat: {{ $prerequisite->name }}
                                        <button type="button" wire:click="removePrerequisite({{ $row->subject_id }}, {{ $prerequisite->id }})" class="text-danger" aria-label="Hapus prasyarat {{ $prerequisite->name }}">×</button>
                                    </span>
                                @endforeach
                            </p>
                        </div>
                        <x-badge :variant="$row->type->variant()">{{ $row->type->label() }}</x-badge>
                        <div class="flex gap-2">
                            <x-button size="sm" variant="secondary" wire:click="setType({{ $row->id }}, '{{ $row->type === \App\Enums\SubjectType::Required ? 'elective' : 'required' }}')">Jadikan {{ $row->type === \App\Enums\SubjectType::Required ? 'pilihan' : 'wajib' }}</x-button>
                            <x-button size="sm" variant="secondary" wire:click="removeItem({{ $row->id }})" wire:confirm="Keluarkan {{ $row->subject->name }} dari kurikulum?">Keluarkan</x-button>
                        </div>
                    </div>
                @endforeach
            </x-card>
        @empty
            <x-card><x-empty-state icon="academic-cap" title="Kurikulum masih kosong" description="Tambahkan mata kuliah di atas." /></x-card>
        @endforelse
    @endif
</div>
