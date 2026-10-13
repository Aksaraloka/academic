@php
    $genders = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
    $displayName = $student?->name ?? $teacher?->name ?? $guardian?->name ?? $user->name;
@endphp
<div class="space-y-6">
    <section class="overflow-hidden rounded-card border border-border bg-surface" aria-labelledby="nama-profil">
        <div class="h-24 bg-brand"></div>
        <div class="px-4 pb-5 sm:px-6">
            <div class="-mt-12 flex flex-wrap items-end justify-between gap-3">
                <span class="flex size-24 items-center justify-center rounded-full border-4 border-surface bg-primary/15 text-3xl font-bold text-primary" aria-hidden="true">{{ $user->initials() }}</span>
                <x-button :href="route('profile.edit')" wire:navigate icon="pencil-square">Ubah profil</x-button>
            </div>
            <h1 id="nama-profil" class="mt-3 text-2xl font-bold sm:text-3xl">{{ $displayName }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <x-badge variant="primary">{{ $user->role->label() }}</x-badge>
                @if ($student)
                    <x-badge :variant="$student->status->variant()">{{ $student->status->label() }}</x-badge>
                    <span class="text-sm tabular-nums text-text-muted">NIM {{ $student->nim }}</span>
                    @if ($student->program)<span class="text-sm text-text-muted">· {{ $student->program->label() }}</span>@endif
                @elseif ($teacher)
                    <span class="text-sm text-text-muted">{{ $teacher->employment_status }}@if ($teacher->nip) · NIP {{ $teacher->nip }}@endif</span>
                @endif
            </div>
        </div>
    </section>

    @if ($student)
        <div class="grid gap-4 lg:grid-cols-2">
            <x-card title="Data pribadi">
                <x-info-list class="sm:grid-cols-1" :items="[
                    'Nama lengkap' => $student->name,
                    'NIM' => $student->nim,
                    'NIK' => $student->maskedNik(),
                    'Tempat, tanggal lahir' => trim(($student->birth_place ?? '').($student->birth_place && $student->birth_date ? ', ' : '').($student->birth_date?->translatedFormat('j F Y') ?? '')),
                    'Jenis kelamin' => $genders[$student->gender] ?? null,
                    'Agama' => $student->religion,
                    'Kewarganegaraan' => $student->nationality,
                ]" />
            </x-card>

            <x-card title="Kontak dan alamat">
                <x-info-list class="sm:grid-cols-1" :items="[
                    'Email' => $user->email,
                    'Telepon' => $student->phone ?? $user->phone,
                    'Alamat' => $student->address,
                    'Kota/kabupaten' => $student->city,
                    'Provinsi' => $student->province,
                    'Kode pos' => $student->postal_code,
                ]" />
            </x-card>
        </div>

        <x-card title="Data akademik">
            <x-info-list :items="[
                'Program studi' => $student->program?->label(),
                'Fakultas' => $student->program?->faculty?->name,
                'Angkatan' => $student->batch_year,
                'Kelas' => $group?->name,
                'Dosen wali' => $group?->homeroomTeacher?->name,
                'Semester aktif' => $group?->academicTerm ? $group->academicTerm->name.' '.$group->academicTerm->semester : null,
                'Status mahasiswa' => $student->status->label(),
                'Tanggal masuk' => $student->entry_date?->translatedFormat('j F Y'),
                'Jalur masuk' => $student->admission_path,
                'Asal sekolah' => $student->previous_school,
            ]" />
        </x-card>

        <section aria-labelledby="data-ortu" class="space-y-3">
            <h2 id="data-ortu" class="text-lg font-semibold">Data orang tua / wali</h2>

            @forelse ($parents as $parent)
                <x-card :title="$parent->relation ?? 'Wali'" wire:key="parent-{{ $parent->id }}">
                    <x-info-list :items="[
                        'Nama' => $parent->name,
                        'Pekerjaan' => $parent->occupation,
                        'Pendidikan terakhir' => $parent->education,
                        'Telepon / WhatsApp' => $parent->phone,
                        'Alamat' => $parent->address,
                    ]" />
                </x-card>
            @empty
                <x-card><x-empty-state icon="user-group" title="Data orang tua belum diisi" description="Hubungi BAAK untuk melengkapi data orang tua atau wali." /></x-card>
            @endforelse
        </section>

        <p class="text-xs text-text-muted">Data identitas dan akademik dikelola BAAK. Jika ada yang keliru, ajukan perbaikan lewat menu Masukan. Kamu bisa memperbarui kontak dan kata sandi lewat tombol Ubah profil.</p>
    @elseif ($guardian)
        <x-card title="Data diri">
            <x-info-list :items="[
                'Nama' => $guardian->name,
                'Hubungan' => $guardian->relation,
                'Pekerjaan' => $guardian->occupation,
                'Pendidikan terakhir' => $guardian->education,
                'Email' => $user->email,
                'Telepon / WhatsApp' => $guardian->phone ?? $user->phone,
                'Alamat' => $guardian->address,
            ]" />
        </x-card>

        <x-card title="Anak" :padding="false">
            @forelse ($guardian->students as $child)
                <div class="flex min-h-14 items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0" wire:key="child-{{ $child->id }}">
                    <x-icon name="user-circle" class="text-primary" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $child->name }}</p>
                        <p class="truncate text-xs text-text-muted tabular-nums">NIM {{ $child->nim }}@if ($child->program) · {{ $child->program->label() }}@endif</p>
                    </div>
                    <x-badge :variant="$child->status->variant()">{{ $child->status->label() }}</x-badge>
                </div>
            @empty
                <x-empty-state icon="user-group" title="Belum ada anak yang tertaut" description="Minta BAAK menautkan akunmu ke data mahasiswa." />
            @endforelse
        </x-card>
    @elseif ($teacher)
        <x-card title="Data dosen">
            <x-info-list :items="[
                'Nama' => $teacher->name,
                'NIDN' => $teacher->nidn,
                'NIP' => $teacher->nip,
                'Status kepegawaian' => $teacher->employment_status,
                'Email' => $user->email,
                'Telepon' => $user->phone,
            ]" />
        </x-card>
    @else
        <x-card title="Data akun">
            <x-info-list :items="[
                'Nama' => $user->name,
                'Peran' => $user->role->label(),
                'Email' => $user->email,
                'Telepon' => $user->phone,
            ]" />
        </x-card>
    @endif

    <x-card title="Akun">
        <x-info-list :items="[
            'Institusi' => $user->tenant?->name,
            'Peran' => $user->role->label(),
            'Email masuk' => $user->email,
            'Bergabung' => $user->created_at?->translatedFormat('j F Y'),
        ]" />
    </x-card>
</div>
