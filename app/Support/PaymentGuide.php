<?php

namespace App\Support;

/**
 * Panduan pembayaran contoh: virtual account dan transfer dari bank-bank umum di Indonesia.
 * Nama menu bisa berbeda mengikuti versi aplikasi tiap bank, jadi halaman panduan menyertakan catatan itu.
 */
class PaymentGuide
{
    /**
     * Bank yang tersedia: kode bank untuk transfer antarbank, nama aplikasi mobile, dan nama internet banking.
     *
     * @return array<string, array{name: string, code: string, mobile: string, web: string, atm: string, va_menu: string}>
     */
    public static function banks(): array
    {
        return [
            'bca' => ['name' => 'BCA', 'code' => '014', 'mobile' => 'm-BCA atau BCA mobile', 'web' => 'KlikBCA', 'atm' => 'ATM BCA', 'va_menu' => 'm-Transfer > BCA Virtual Account'],
            'mandiri' => ['name' => 'Bank Mandiri', 'code' => '008', 'mobile' => 'Livin\' by Mandiri', 'web' => 'Mandiri Online', 'atm' => 'ATM Mandiri', 'va_menu' => 'Bayar > Multipayment'],
            'bni' => ['name' => 'BNI', 'code' => '009', 'mobile' => 'BNI Mobile Banking', 'web' => 'BNI Internet Banking', 'atm' => 'ATM BNI', 'va_menu' => 'Transfer > Virtual Account Billing'],
            'bri' => ['name' => 'BRI', 'code' => '002', 'mobile' => 'BRImo', 'web' => 'Internet Banking BRI', 'atm' => 'ATM BRI', 'va_menu' => 'Pembayaran > BRIVA'],
            'bsi' => ['name' => 'Bank Syariah Indonesia (BSI)', 'code' => '451', 'mobile' => 'BSI Mobile', 'web' => 'BSI Net', 'atm' => 'ATM BSI', 'va_menu' => 'Pembayaran > Virtual Account'],
            'cimb' => ['name' => 'CIMB Niaga', 'code' => '022', 'mobile' => 'OCTO Mobile', 'web' => 'OCTO Clicks', 'atm' => 'ATM CIMB Niaga', 'va_menu' => 'Transfer > Virtual Account'],
            'permata' => ['name' => 'PermataBank', 'code' => '013', 'mobile' => 'PermataMobile X', 'web' => 'PermataNet', 'atm' => 'ATM Permata', 'va_menu' => 'Bayar > Bayar Tagihan > Virtual Account'],
            'btn' => ['name' => 'BTN', 'code' => '200', 'mobile' => 'BTN Mobile', 'web' => 'BTN Internet Banking', 'atm' => 'ATM BTN', 'va_menu' => 'Pembayaran > Virtual Account'],
        ];
    }

    /**
     * Langkah pembayaran virtual account per saluran bank.
     *
     * @return array<string, array<int, string>> saluran => langkah
     */
    public static function virtualAccountSteps(string $bank, string $vaNumber): array
    {
        $info = self::banks()[$bank];

        return [
            $info['atm'] => [
                'Masukkan kartu ATM dan PIN.',
                'Pilih menu transfer atau pembayaran, lalu pilih '.$info['va_menu'].' (nama menu bisa sedikit berbeda).',
                "Masukkan nomor virtual account {$vaNumber}.",
                'Periksa nama pemilik dan nominal tagihan, lalu konfirmasi.',
                'Simpan struk sebagai bukti pembayaran.',
            ],
            $info['mobile'] => [
                "Buka aplikasi {$info['mobile']} dan masuk dengan PIN atau biometrik.",
                "Pilih menu {$info['va_menu']}.",
                "Masukkan nomor virtual account {$vaNumber}, atau pilih dari daftar tersimpan.",
                'Periksa nama dan nominal tagihan yang tampil, lalu lanjutkan.',
                'Masukkan PIN atau kode otorisasi, lalu simpan bukti transaksi.',
            ],
            $info['web'] => [
                "Masuk ke {$info['web']} dengan akunmu.",
                "Pilih menu pembayaran atau transfer ke virtual account ({$info['va_menu']}).",
                "Masukkan nomor virtual account {$vaNumber} dan nominal sesuai tagihan.",
                'Selesaikan verifikasi (token atau kode OTP) untuk mengonfirmasi.',
                'Unduh atau tangkap layar bukti transaksi.',
            ],
        ];
    }

    /**
     * Langkah transfer ke rekening kampus dari bank tertentu (sesama bank atau antarbank).
     *
     * @return array<string, array<int, string>>
     */
    public static function transferSteps(string $bank, ?string $targetBank, ?string $accountNumber, ?string $accountName): array
    {
        $info = self::banks()[$bank];
        $target = $targetBank ?: 'bank tujuan kampus';
        $account = $accountNumber ?: '(nomor rekening kampus)';
        $holder = $accountName ?: 'nama pemilik rekening kampus';
        $sameBank = $targetBank !== null && str_contains(mb_strtolower($targetBank), mb_strtolower(explode(' ', $info['name'])[0]));

        $core = [
            $sameBank
                ? "Pilih transfer sesama {$info['name']} (rekening tujuan juga di {$target})."
                : "Pilih transfer antarbank, lalu pilih bank tujuan {$target} (BI-FAST dianjurkan karena dana masuk seketika; kode bank bisa dilihat di daftar bank pada aplikasi).",
            "Masukkan nomor rekening tujuan {$account} atas nama {$holder}.",
            'Isi nominal persis sesuai tagihan di Aksaraloka Pay, tanpa pembulatan.',
            'Isi berita atau keterangan dengan NIM dan nomor tagihan agar mudah diverifikasi.',
            'Konfirmasi dengan PIN atau kode OTP, lalu simpan bukti transfer.',
        ];

        return [
            $info['mobile'] => array_merge(["Buka aplikasi {$info['mobile']}, pilih menu Transfer."], $core),
            $info['atm'] => array_merge(['Masukkan kartu ATM dan PIN, pilih menu Transfer.'], $core),
            $info['web'] => array_merge(["Masuk ke {$info['web']}, pilih menu Transfer."], $core),
        ];
    }

    /**
     * Langkah setelah membayar: konfirmasi di Aksaraloka Pay.
     *
     * @return array<int, string>
     */
    public static function afterPaying(): array
    {
        return [
            'Pembayaran lewat virtual account biasanya tercatat otomatis dan tagihan berubah menjadi Lunas.',
            'Untuk transfer biasa, buka Aksaraloka Pay, pilih tagihan, lalu tekan Konfirmasi pembayaran dan unggah bukti transfer.',
            'Petugas keuangan memverifikasi, lalu statusnya menjadi Lunas dan KRS bisa diisi.',
            'Jika dana sudah terpotong tetapi status belum berubah setelah 1 x 24 jam kerja, hubungi helpdesk dengan menyertakan bukti pembayaran.',
        ];
    }
}
