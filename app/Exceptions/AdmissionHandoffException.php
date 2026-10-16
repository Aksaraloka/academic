<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Serah terima mahasiswa baru ditolak karena aturan data (email atau NIK sudah dipakai, tarif belum ada, dan sebagainya).
 * `$code` adalah kode mesin yang dikembalikan ke Admission; `$status` kode HTTP.
 */
class AdmissionHandoffException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
