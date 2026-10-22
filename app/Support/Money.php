<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Aritmetika uang berbasis string desimal (bcmath), 2 angka di belakang koma.
 * Jangan pernah memakai float untuk nominal: selalu lewat kelas ini.
 */
final class Money
{
    public const SCALE = 2;

    /**
     * Menormalkan input (int, string "1250000", "1250000.5") menjadi "1250000.50".
     */
    public static function of(int|string|null $value): string
    {
        $value = trim((string) ($value ?? '0'));

        if ($value === '') {
            $value = '0';
        }

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Nominal tidak valid: [{$value}].");
        }

        return bcadd($value, '0', self::SCALE);
    }

    public static function add(int|string ...$values): string
    {
        return array_reduce($values, fn (string $carry, $value) => bcadd($carry, self::of($value), self::SCALE), '0.00');
    }

    public static function sub(int|string $a, int|string $b): string
    {
        return bcsub(self::of($a), self::of($b), self::SCALE);
    }

    /**
     * Persentase dari nominal, dibulatkan ke 2 desimal (half up).
     */
    public static function percent(int|string $amount, int|string $percent): string
    {
        $raw = bcdiv(bcmul(self::of($amount), (string) $percent, self::SCALE + 4), '100', self::SCALE + 4);

        return self::round($raw);
    }

    public static function compare(int|string $a, int|string $b): int
    {
        return bccomp(self::of($a), self::of($b), self::SCALE);
    }

    public static function isNegative(int|string $value): bool
    {
        return self::compare($value, 0) < 0;
    }

    public static function isPositive(int|string $value): bool
    {
        return self::compare($value, 0) > 0;
    }

    public static function negate(int|string $value): string
    {
        return bcmul(self::of($value), '-1', self::SCALE);
    }

    /**
     * Format tampilan Indonesia: "Rp 1.250.000" (desimal ditampilkan bila ada).
     */
    public static function format(int|string|null $value): string
    {
        $value = self::of($value);
        $negative = str_starts_with($value, '-');
        [$integer, $fraction] = explode('.', ltrim($value, '-'));

        $formatted = 'Rp '.number_format((int) $integer, 0, ',', '.');

        if ($fraction !== '00') {
            $formatted .= ','.$fraction;
        }

        return ($negative ? '-' : '').$formatted;
    }

    private static function round(string $value): string
    {
        $offset = '0.'.str_repeat('0', self::SCALE).'5';

        return str_starts_with($value, '-')
            ? bcsub($value, $offset, self::SCALE)
            : bcadd($value, $offset, self::SCALE);
    }
}
