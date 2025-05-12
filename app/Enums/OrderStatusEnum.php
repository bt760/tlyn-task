<?php

namespace App\Enums;

enum OrderStatusEnum: string {
    case OPEN = 'open';
    case PARTIAL = 'partial';
    case FILLED = 'filled';
    case CANCELLED = 'cancelled';
}
