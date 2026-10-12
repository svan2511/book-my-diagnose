<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Ready = 'ready';
    case Delivered = 'delivered';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
