<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class PokerExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('roman', self::roman(...)),
            new TwigFilter('chips', self::chips(...)),
        ];
    }

    /** Numéro de niveau en chiffres romains, comme sur l'horloge. */
    public static function roman(int $n): string
    {
        $out = '';
        foreach (['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1] as $numeral => $value) {
            while ($n >= $value) {
                $out .= $numeral;
                $n -= $value;
            }
        }

        return $out;
    }

    /** 1000 → « 1 000 », avec une espace insécable fine. */
    public static function chips(int $n): string
    {
        return number_format($n, 0, ',', "\u{202F}");
    }
}
