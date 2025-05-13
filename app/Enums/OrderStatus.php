<?php

namespace App\Enums;

enum OrderStatus: string {
    case OPEN = 'open';
    case PARTIAL = 'partial';
    case FILLED = 'filled';
    case CANCELLED = 'cancelled';

    public function canCancel(): bool
    {
        return match ($this) {
            self::OPEN, self::PARTIAL => true,
            default => false,
        };
    }
}
