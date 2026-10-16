<?php

namespace App\Jobs;

use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\IntegrationApps;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Mengunduh pas foto mahasiswa baru dari URL bertanda tangan milik Admission (berlaku 72 jam) setelah serah terima
 * berhberhasil, lalu menyimpannya di disk privat dan mengisi `students.photo_path`. Foto yang tidak sah (mime, ukuran, hash,
 * URL ditolak) gagal tanpa ulangan; gangguan jaringan atau HTTP 429/5xx diulang dengan backoff. Kegagalan hanya dicatat
 * di audit dan tidak pernah membatalkan serah terima; admin bisa mengunggah foto secara manual.
 */
class FetchAdmissionPhoto implements ShouldQueue
{
    use Queueable;

    public const MAX_BYTES = 2 * 1024 * 1024;

    /** @var array<string, string> mime yang diterima => ekstensi berkas */
    public const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    public int $tries = 5;

    public function __construct(
        public int $studentId,
        public string $url,
        public string $sha256,
        public string $mime,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(AuditLogger $audit): void
    {
        $student = Student::query()->withoutGlobalScopes()->find($this->studentId);

        if ($student === null || $student->photo_path !== null) {
            return;
        }

        $permanent = $this->rejection();

        if ($permanent !== null) {
            $this->recordFailure($audit, $student, $permanent);

            return;
        }

        try {
            $response = Http::timeout(15)->withOptions(['allow_redirects' => false])->get($this->url);
        } catch (ConnectionException $e) {
            $this->transientFailure($audit, $student, 'Tidak dapat menghubungi Admission: '.$e->getMessage(), $e);

            return;
        }

        if ($response->status() === 429 || $response->serverError()) {
            $this->transientFailure($audit, $student, 'Admission menjawab HTTP '.$response->status(), new \RuntimeException('HTTP '.$response->status()));

            return;
        }

        if (! $response->successful()) {
            $this->recordFailure($audit, $student, 'URL foto ditolak Admission (HTTP '.$response->status().'); kemungkinan tanda tangan kedaluwarsa.');

            return;
        }

        $body = $response->body();
        $problem = $this->bodyProblem($body);

        if ($problem !== null) {
            $this->recordFailure($audit, $student, $problem);

            return;
        }

        $path = "students/photos/{$student->tenant_id}/{$student->id}.".self::EXTENSIONS[$this->mime];
        Storage::disk('local')->put($path, $body);
        $student->update(['photo_path' => $path]);

        $audit->log('admission.photo_saved', $student, [], ['photo_path' => $path, 'bytes' => strlen($body)], null, $student->tenant_id);
    }

    /**
     * Dipanggil antrean setelah percobaan terakhir gagal.
     */
    public function failed(Throwable $exception): void
    {
        $student = Student::query()->withoutGlobalScopes()->find($this->studentId);

        if ($student !== null && $student->photo_path === null) {
            $this->recordFailure(app(AuditLogger::class), $student, 'Pengunduhan foto menyerah setelah beberapa percobaan: '.$exception->getMessage());
        }
    }

    /**
     * Alasan penolakan sebelum mengunduh, atau null bila boleh dilanjutkan.
     */
    private function rejection(): ?string
    {
        if (! isset(self::EXTENSIONS[$this->mime])) {
            return 'Jenis foto tidak diterima (hanya JPEG atau PNG).';
        }

        $admission = parse_url(app(IntegrationApps::class)->get(IntegrationApps::ADMISSION)->url());
        $target = parse_url($this->url);

        if (! is_array($admission) || ! is_array($target) || ! isset($admission['host'], $target['host'])
            || strcasecmp($admission['host'], $target['host']) !== 0
            || ($admission['port'] ?? null) !== ($target['port'] ?? null)) {
            return 'URL foto bukan milik Admission yang terhubung.';
        }

        return null;
    }

    private function bodyProblem(string $body): ?string
    {
        if (strlen($body) > self::MAX_BYTES) {
            return 'Foto melebihi 2 MB.';
        }

        if (! hash_equals(strtolower($this->sha256), hash('sha256', $body))) {
            return 'Hash foto tidak cocok dengan yang dikirim Admission.';
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);

        if ($detected !== $this->mime) {
            return "Isi foto ({$detected}) tidak sesuai jenis yang dinyatakan ({$this->mime}).";
        }

        return null;
    }

    /**
     * Gangguan sementara: lempar agar antrean mengulang dengan backoff. Pada driver `sync` tidak ada ulangan,
     * jadi langsung dicatat agar serah terima tidak ikut gagal.
     */
    private function transientFailure(AuditLogger $audit, Student $student, string $message, Throwable $cause): void
    {
        if ($this->job === null || $this->job instanceof SyncJob) {
            $this->recordFailure($audit, $student, $message);

            return;
        }

        throw $cause;
    }

    private function recordFailure(AuditLogger $audit, Student $student, string $reason): void
    {
        $audit->log('admission.photo_failed', $student, [], ['reason' => $reason], null, $student->tenant_id);
    }
}
