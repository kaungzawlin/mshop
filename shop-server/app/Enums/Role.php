<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case Reseller = 'reseller';
    case Rider = 'rider';
    case Ops = 'ops';
    case Finance = 'finance';
    case Responsible = 'responsible';
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
