<?php

namespace App\Support\Finance;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan transaksi keuangan di dalam DB::transaction() dengan baris
 * rekening dikunci (SELECT ... FOR UPDATE). Dua teller yang memproses
 * rekening yang sama akan diproses bergantian, sehingga cek saldo di dalam
 * callback selalu memakai saldo terbaru.
 */
class FinancialTransaction
{
    /**
     * Jumlah percobaan ulang bila terjadi deadlock.
     */
    public const ATTEMPTS = 3;

    /**
     * @template TModel of Model
     * @template TResult
     *
     * @param  Builder<TModel>  $query  query baris yang dikunci, mis. Rekening::whereKey($id)
     * @param  Closure(TModel): TResult  $callback
     * @return TResult
     */
    public static function lock(Builder $query, Closure $callback): mixed
    {
        return DB::transaction(
            fn () => $callback($query->lockForUpdate()->firstOrFail()),
            self::ATTEMPTS,
        );
    }
}
