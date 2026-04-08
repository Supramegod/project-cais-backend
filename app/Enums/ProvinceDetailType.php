<?php
// app/Enums/ProvinceDetailType.php

namespace App\Enums;

enum ProvinceDetailType: string
{
    case CITIES = 'cities';
    case UMP    = 'ump';
    case UMSP   = 'umsp';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}