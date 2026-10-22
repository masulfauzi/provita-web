<?php

use App\Support\Money;

it('menormalkan nominal ke 2 desimal', function () {
    expect(Money::of(1250000))->toBe('1250000.00')
        ->and(Money::of('1250000.5'))->toBe('1250000.50')
        ->and(Money::of(null))->toBe('0.00');
});

it('menjumlahkan tanpa kesalahan pembulatan float', function () {
    // 0.1 + 0.2 dengan float = 0.30000000000000004
    expect(Money::add('0.10', '0.20'))->toBe('0.30')
        ->and(Money::sub('100000', '70000.25'))->toBe('29999.75');
});

it('menghitung persentase dengan pembulatan half up', function () {
    expect(Money::percent('100000', '2.5'))->toBe('2500.00')
        ->and(Money::percent('333', '10'))->toBe('33.30')
        ->and(Money::percent('0.05', '50'))->toBe('0.03');
});

it('membandingkan dan memformat nominal', function () {
    expect(Money::compare('10.00', '9.99'))->toBe(1)
        ->and(Money::isNegative('-0.01'))->toBeTrue()
        ->and(Money::format('1250000'))->toBe('Rp 1.250.000')
        ->and(Money::format('-1500.50'))->toBe('-Rp 1.500,50');
});

it('menolak nominal yang bukan angka', function () {
    Money::of('12abc');
})->throws(InvalidArgumentException::class);
