<?php

namespace App\Support\Finance;

use LogicException;

class ImmutableRecordException extends LogicException
{
    public static function forModel(object $model, string $action): self
    {
        return new self(sprintf(
            '%s tidak boleh di-%s. Koreksi dilakukan dengan transaksi pembalik.',
            class_basename($model),
            $action,
        ));
    }
}
