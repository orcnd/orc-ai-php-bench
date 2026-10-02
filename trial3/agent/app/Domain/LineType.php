<?php
declare(strict_types=1);
namespace App\Domain;

final class LineType
{
    public const MERCH = 'merch';
    public const CREDIT = 'credit';
    public const SHIPPING = 'shipping';
    public const ALL = [self::MERCH, self::CREDIT, self::SHIPPING];
}
