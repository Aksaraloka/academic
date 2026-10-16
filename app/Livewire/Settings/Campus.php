<?php

namespace App\Livewire\Settings;

use App\Services\AuditLogger;
use App\Services\NimGenerator;
use Closure;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pengaturan kampus')]
class Campus extends Component
{
    public string $name = '';

    public string $helpdesk_whatsapp = '';

    public string $attendance_mode = 'per_hour';

    public string $min_attendance_percent = '';

    public string $absent_threshold = '';

    public string $meetings_per_course = '';

    public string $checkin_opens = '';

    public string $checkin_closes = '';

    public string $geofence_lat = '';

    public string $geofence_lng = '';

    public string $geofence_radius = '';

    public string $graduation_min_credits = '';

    public string $graduation_min_gpa = '';

    public bool $krs_requires_payment = true;

    public string $thesis_min_credits_propose = '';

    public string $thesis_min_credits_defense = '';

    public string $thesis_min_guidance = '';

    public bool $thesis_required_for_graduation = true;

    public string $graduation_max_d_percent = '';

    public string $predicate_very_satisfactory_gpa = '';

    public string $predicate_cum_laude_gpa = '';

    public string $predicate_cum_laude_max_years = '';

    public string $letter_signatory_name = '';

    public string $letter_signatory_title = '';

    public string $internship_min_credits = '';

    public string $conversion_max_credits = '';

    public string $va_prefix = '';

    public string $nim_pattern = '';

    public string $payment_bank_name = '';

    public string $payment_account_number = '';

    public string $payment_account_name = '';

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        $this->name = $tenant->name;
        $this->helpdesk_whatsapp = (string) $tenant->setting('helpdesk_whatsapp');
        $this->krs_requires_payment = (bool) $tenant->setting('krs_requires_payment');
        $this->thesis_required_for_graduation = (bool) $tenant->setting('thesis_required_for_graduation');

        foreach (['thesis_min_credits_propose', 'thesis_min_credits_defense', 'thesis_min_guidance', 'graduation_max_d_percent', 'predicate_very_satisfactory_gpa', 'predicate_cum_laude_gpa', 'predicate_cum_laude_max_years'] as $key) {
            $this->{$key} = (string) $tenant->setting($key);
        }

        foreach (['letter_signatory_name', 'letter_signatory_title', 'internship_min_credits', 'conversion_max_credits', 'va_prefix', 'nim_pattern', 'payment_bank_name', 'payment_account_number', 'payment_account_name'] as $key) {
            $this->{$key} = (string) $tenant->setting($key);
        }

        foreach (['attendance_mode', 'min_attendance_percent', 'absent_threshold', 'meetings_per_course', 'checkin_opens', 'checkin_closes', 'geofence_lat', 'geofence_lng', 'geofence_radius', 'graduation_min_credits', 'graduation_min_gpa'] as $key) {
            $this->{$key} = (string) $tenant->setting($key);
        }
    }

    public function save(AuditLogger $audit): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'helpdesk_whatsapp' => ['nullable', 'regex:/^[0-9+\s\-]{8,20}$/'],
            'attendance_mode' => ['required', 'in:per_hour,daily,both'],
            'min_attendance_percent' => ['required', 'integer', 'between:0,100'],
            'absent_threshold' => ['required', 'integer', 'between:1,50'],
            'meetings_per_course' => ['required', 'integer', 'between:1,40'],
            'checkin_opens' => ['required', 'date_format:H:i'],
            'checkin_closes' => ['required', 'date_format:H:i', 'after:checkin_opens'],
            'geofence_lat' => ['required', 'numeric', 'between:-90,90'],
            'geofence_lng' => ['required', 'numeric', 'between:-180,180'],
            'geofence_radius' => ['required', 'integer', 'between:10,5000'],
            'graduation_min_credits' => ['required', 'integer', 'between:1,400'],
            'graduation_min_gpa' => ['required', 'numeric', 'between:0,4'],
            'krs_requires_payment' => ['boolean'],
            'thesis_min_credits_propose' => ['required', 'integer', 'between:0,300'],
            'thesis_min_credits_defense' => ['required', 'integer', 'between:0,300'],
            'thesis_min_guidance' => ['required', 'integer', 'between:0,40'],
            'thesis_required_for_graduation' => ['boolean'],
            'graduation_max_d_percent' => ['required', 'numeric', 'between:0,100'],
            'predicate_very_satisfactory_gpa' => ['required', 'numeric', 'between:0,4'],
            'predicate_cum_laude_gpa' => ['required', 'numeric', 'between:0,4', 'gte:predicate_very_satisfactory_gpa'],
            'predicate_cum_laude_max_years' => ['required', 'numeric', 'between:1,15'],
            'letter_signatory_name' => ['nullable', 'string', 'max:120'],
            'letter_signatory_title' => ['required', 'string', 'max:120'],
            'internship_min_credits' => ['required', 'integer', 'between:0,300'],
            'conversion_max_credits' => ['required', 'integer', 'between:1,60'],
            'va_prefix' => ['nullable', 'digits_between:2,8'],
            'nim_pattern' => ['required', 'string', 'max:40', fn (string $attribute, mixed $value, Closure $fail) => ($error = NimGenerator::patternError((string) $value)) === null ?: $fail($error)],
            'payment_bank_name' => ['nullable', 'string', 'max:60'],
            'payment_account_number' => ['nullable', 'string', 'max:40'],
            'payment_account_name' => ['nullable', 'string', 'max:100'],
        ], [], [
            'name' => 'nama kampus', 'helpdesk_whatsapp' => 'nomor WhatsApp helpdesk', 'attendance_mode' => 'mode presensi',
            'min_attendance_percent' => 'minimal kehadiran', 'absent_threshold' => 'batas alpa', 'meetings_per_course' => 'jumlah pertemuan',
            'checkin_opens' => 'jam check-in dibuka', 'checkin_closes' => 'jam check-in ditutup', 'geofence_lat' => 'lintang',
            'geofence_lng' => 'bujur', 'geofence_radius' => 'radius', 'graduation_min_credits' => 'SKS minimal wisuda', 'graduation_min_gpa' => 'IPK minimal wisuda', 'va_prefix' => 'awalan virtual account', 'nim_pattern' => 'pola NIM',
            'letter_signatory_name' => 'nama penandatangan surat', 'letter_signatory_title' => 'jabatan penandatangan surat',
            'internship_min_credits' => 'SKS minimal magang', 'conversion_max_credits' => 'batas SKS konversi',
        ]);

        $tenant = auth()->user()->tenant;
        $before = ['name' => $tenant->name] + ($tenant->settings ?? []);

        $settings = [
            'helpdesk_whatsapp' => $data['helpdesk_whatsapp'] ?: null,
            'attendance_mode' => $data['attendance_mode'],
            'min_attendance_percent' => (int) $data['min_attendance_percent'],
            'absent_threshold' => (int) $data['absent_threshold'],
            'meetings_per_course' => (int) $data['meetings_per_course'],
            'checkin_opens' => $data['checkin_opens'],
            'checkin_closes' => $data['checkin_closes'],
            'geofence_lat' => (float) $data['geofence_lat'],
            'geofence_lng' => (float) $data['geofence_lng'],
            'geofence_radius' => (int) $data['geofence_radius'],
            'graduation_min_credits' => (int) $data['graduation_min_credits'],
            'graduation_min_gpa' => (float) $data['graduation_min_gpa'],
            'krs_requires_payment' => (bool) $data['krs_requires_payment'],
            'thesis_min_credits_propose' => (int) $data['thesis_min_credits_propose'],
            'thesis_min_credits_defense' => (int) $data['thesis_min_credits_defense'],
            'thesis_min_guidance' => (int) $data['thesis_min_guidance'],
            'thesis_required_for_graduation' => (bool) $data['thesis_required_for_graduation'],
            'graduation_max_d_percent' => (float) $data['graduation_max_d_percent'],
            'predicate_very_satisfactory_gpa' => (float) $data['predicate_very_satisfactory_gpa'],
            'predicate_cum_laude_gpa' => (float) $data['predicate_cum_laude_gpa'],
            'predicate_cum_laude_max_years' => (float) $data['predicate_cum_laude_max_years'],
            'letter_signatory_name' => $data['letter_signatory_name'] ?: null,
            'letter_signatory_title' => $data['letter_signatory_title'],
            'internship_min_credits' => (int) $data['internship_min_credits'],
            'conversion_max_credits' => (int) $data['conversion_max_credits'],
            'va_prefix' => $data['va_prefix'] ?: '8808',
            'nim_pattern' => trim($data['nim_pattern']),
            'payment_bank_name' => $data['payment_bank_name'] ?: null,
            'payment_account_number' => $data['payment_account_number'] ?: null,
            'payment_account_name' => $data['payment_account_name'] ?: null,
        ];

        $tenant->update(['name' => $data['name'], 'settings' => array_merge($tenant->settings ?? [], $settings)]);
        $audit->log('campus.settings_updated', $tenant, $before, ['name' => $data['name']] + $settings);

        $this->dispatch('toast', message: 'Pengaturan kampus disimpan.', type: 'success');
    }

    public function render()
    {
        return view('livewire.settings.campus');
    }
}
