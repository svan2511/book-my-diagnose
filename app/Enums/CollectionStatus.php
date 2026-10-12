<?php

namespace App\Enums;

enum CollectionStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case OnTheWay = 'on_the_way';
    case Collected = 'collected';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function allowedTransitions(self $from): array
    {
        return match ($from) {
            self::Pending => [self::Assigned, self::Cancelled],
            self::Assigned => [self::OnTheWay, self::Cancelled],
            self::OnTheWay => [self::Collected, self::Failed],
            self::Failed => [self::Assigned, self::Cancelled],
            self::Collected, self::Cancelled => [],
        };
    }
}
