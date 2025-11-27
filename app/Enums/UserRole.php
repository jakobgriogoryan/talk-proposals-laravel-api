<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * User role enumeration.
 *
 * @method static self ADMIN()
 * @method static self REVIEWER()
 * @method static self SPEAKER()
 */
enum UserRole: string
{
    case ADMIN = 'admin';
    case REVIEWER = 'reviewer';
    case SPEAKER = 'speaker';

    /**
     * Get all role values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get roles available for registration.
     *
     * @return array<string>
     */
    public static function registrationRoles(): array
    {
        return [
            self::REVIEWER->value,
            self::SPEAKER->value,
        ];
    }
}
