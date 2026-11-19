<?php

namespace Tests\Unit;

use App\Support\PhonePrefix;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhonePrefixTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            'Lebanon (3-digit code)' => ['96170123456', 'LB'],
            'UAE with plus and spaces' => ['+971 50 123 4567', 'AE'],
            'Egypt (2-digit code)' => ['201001234567', 'EG'],
            'Leading 00' => ['00966501234567', 'SA'],
            'NANP' => ['12025550123', 'US'],
            'Kazakhstan beats Russia' => ['77011234567', 'KZ'],
            'Russia' => ['79161234567', 'RU'],
            'Too short' => ['12345', null],
            'Empty' => ['', null],
            'Unassigned code' => ['99912345678', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_country_from_calling_code(string $number, ?string $country): void
    {
        $this->assertSame($country, PhonePrefix::country($number));
    }
}
