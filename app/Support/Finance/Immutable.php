<?php

namespace App\Support\Finance;

/**
 * Model keuangan yang sekali tersimpan tidak boleh diubah maupun dihapus.
 * Koreksi dilakukan dengan membuat transaksi pembalik.
 */
trait Immutable
{
    protected static function bootImmutable(): void
    {
        static::updating(fn ($model) => throw ImmutableRecordException::forModel($model, 'update'));
        static::deleting(fn ($model) => throw ImmutableRecordException::forModel($model, 'hapus'));
    }
}
