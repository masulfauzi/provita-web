<?php

namespace App\Http\Modules\Transaksi\Exceptions;

use RuntimeException;

class TransaksiDitolakException extends RuntimeException
{
    public static function saldoTidakCukup(string $saldo, string $nominal): self
    {
        return new self("Saldo tidak cukup. Saldo {$saldo}, penarikan {$nominal}.");
    }

    public static function nominalTidakValid(): self
    {
        return new self('Nominal transaksi harus lebih dari nol.');
    }

    public static function rekeningTidakAktif(): self
    {
        return new self('Rekening tidak aktif.');
    }

    public static function sudahDibalik(): self
    {
        return new self('Transaksi ini sudah pernah dibalik.');
    }

    public static function pembalikTidakBisaDibalik(): self
    {
        return new self('Transaksi pembalik tidak bisa dibalik lagi.');
    }

    public static function bukanTransaksiTabungan(): self
    {
        return new self('Penerimaan/pengeluaran kas dibatalkan lewat menu Kas.');
    }
}
