<?php

namespace Fronty\SyliusIMojePlugin\Utils;

final class PriceFormatter
{
    public static function toDecimals(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
