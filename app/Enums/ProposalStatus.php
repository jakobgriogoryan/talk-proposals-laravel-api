<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Proposal status enumeration.
 *
 * @method static self PENDING()
 * @method static self APPROVED()
 * @method static self REJECTED()
 */
enum ProposalStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Get all status values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
