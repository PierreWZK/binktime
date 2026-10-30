<?php

namespace App\Poker;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * « Compte du tapis » : le tapis du jour, identique pour tout le monde, tiré d'après la date (heure de Paris).
 */
final class DailyStack
{
    /** Valeur → couleur du jeton, comme dans une mallette de club. */
    public const CHIPS = [
        25 => 'ivoire',
        100 => 'rouge',
        500 => 'vert',
        1000 => 'noir',
        5000 => 'or',
    ];

    public const FIRST_DAY = '2026-10-30';
    public const ATTEMPTS = 4;
    public const REVEAL_SECONDS = 6;

    /**
     * @return array{number: int, date: string, piles: list<array{value: int, color: string, count: int}>, total: int}
     */
    public static function forDate(\DateTimeImmutable $when): array
    {
        $day = $when->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');
        $number = (int) (new \DateTimeImmutable(self::FIRST_DAY))->diff(new \DateTimeImmutable($day))->format('%r%a') + 1;
        $random = new Randomizer(new Mt19937(crc32('binktime-tapis-'.$day)));

        // 3 à 5 piles de couleurs différentes, posées dans le désordre comme sur une vraie table
        $values = array_keys(self::CHIPS);
        $chosen = $random->pickArrayKeys($values, $random->getInt(3, 5));
        $piles = [];
        foreach ($random->shuffleArray($chosen) as $key) {
            $value = $values[$key];
            $piles[] = ['value' => $value, 'color' => self::CHIPS[$value], 'count' => 5000 === $value ? $random->getInt(1, 4) : $random->getInt(2, 12)];
        }

        return [
            'number' => $number,
            'date' => $day,
            'piles' => $piles,
            'total' => array_sum(array_map(fn ($p) => $p['value'] * $p['count'], $piles)),
        ];
    }
}
