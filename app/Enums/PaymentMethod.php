<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'transfer';
    case VirtualAccount = 'va';
    case EWallet = 'ewallet';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Transfer bank',
            self::VirtualAccount => 'Virtual account',
            self::EWallet => 'Dompet digital',
            self::Cash => 'Tunai di kasir',
        };
    }

    /**
     * Metode yang boleh dipilih mahasiswa saat mengonfirmasi pembayaran sendiri.
     *
     * @return array<int, self>
     */
    public static function forStudents(): array
    {
        return [self::BankTransfer, self::VirtualAccount, self::EWallet];
    }
}
