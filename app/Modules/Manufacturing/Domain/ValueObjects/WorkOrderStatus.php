<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Domain\ValueObjects;

enum WorkOrderStatus: string
{
    case Draft      = 'draft';
    case Released   = 'released';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft      => 'Draft',
            self::Released   => 'Released',
            self::InProgress => 'In Progress',
            self::Completed  => 'Completed',
            self::Cancelled  => 'Cancelled',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft      => in_array($next, [self::Released, self::Cancelled], true),
            self::Released   => in_array($next, [self::InProgress, self::Cancelled], true),
            self::InProgress => in_array($next, [self::Completed, self::Cancelled], true),
            self::Completed  => false,
            self::Cancelled  => false,
        };
    }
}
