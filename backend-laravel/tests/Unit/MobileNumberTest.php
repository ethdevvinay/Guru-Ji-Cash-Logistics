<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MobileNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MobileNumberTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function numbers(): array
    {
        return [
            'plain ten digits' => ['9812000004', '+919812000004'],
            'with +91 and spaces' => ['+91 98120 00004', '+919812000004'],
            'with trunk zero' => ['098120 00004', '+919812000004'],
            'with dashes' => ['91-98120-00004', '+919812000004'],
            'already normalised' => ['+919812000004', '+919812000004'],
            'landline-like start' => ['5812000004', null],
            'too short' => ['98120', null],
            'letters' => ['call me', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_indian_mobile_numbers_are_normalised(string $input, ?string $expected): void
    {
        $this->assertSame($expected, MobileNumber::normalize($input));
    }

    public function test_masking_keeps_only_the_last_four_digits(): void
    {
        $this->assertSame('+91******0004', MobileNumber::mask('+919812000004'));
    }
}
