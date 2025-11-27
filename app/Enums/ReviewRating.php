<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Review rating enumeration.
 */
enum ReviewRating: int
{
    case ONE = 1;
    case TWO = 2;
    case THREE = 3;
    case FOUR = 4;
    case FIVE = 5;
    case TEN = 10;

    /**
     * Get all rating values as an array.
     *
     * @return array<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get minimum rating value.
     */
    public static function min(): int
    {
        return self::ONE->value;
    }

    /**
     * Get maximum rating value.
     */
    public static function max(): int
    {
        return self::TEN->value;
    }
}
