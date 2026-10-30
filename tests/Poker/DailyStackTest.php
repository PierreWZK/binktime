<?php

namespace App\Tests\Poker;

use App\Poker\DailyStack;
use PHPUnit\Framework\TestCase;

final class DailyStackTest extends TestCase
{
    public function testMemeTapisPourToutLeMondeLeMemeJour(): void
    {
        $morning = DailyStack::forDate(new \DateTimeImmutable('2026-11-02 08:00', new \DateTimeZone('Europe/Paris')));
        $evening = DailyStack::forDate(new \DateTimeImmutable('2026-11-02 23:59', new \DateTimeZone('Europe/Paris')));

        self::assertSame($morning, $evening);
        self::assertSame(4, $morning['number']);
    }

    public function testLeJourSuitLHeureDeParis(): void
    {
        // 23 h 30 à New York le 1er novembre = 5 h 30 le 2 à Paris
        $ny = DailyStack::forDate(new \DateTimeImmutable('2026-11-01 23:30', new \DateTimeZone('America/New_York')));

        self::assertSame('2026-11-02', $ny['date']);
    }

    public function testUnTapisValideChaqueJourEtQuiChange(): void
    {
        $totals = [];
        $day = new \DateTimeImmutable('2026-10-30 12:00', new \DateTimeZone('Europe/Paris'));
        for ($i = 0; $i < 120; ++$i) {
            $stack = DailyStack::forDate($day->modify("+$i days"));
            self::assertSame($i + 1, $stack['number']);
            self::assertGreaterThanOrEqual(3, \count($stack['piles']));
            self::assertLessThanOrEqual(5, \count($stack['piles']));

            $values = array_column($stack['piles'], 'value');
            self::assertSame($values, array_unique($values), 'une couleur par pile');
            $sum = 0;
            foreach ($stack['piles'] as $pile) {
                self::assertSame(DailyStack::CHIPS[$pile['value']], $pile['color']);
                self::assertGreaterThanOrEqual(1, $pile['count']);
                self::assertLessThanOrEqual(12, $pile['count']);
                $sum += $pile['value'] * $pile['count'];
            }
            self::assertSame($sum, $stack['total']);
            $totals[] = $stack['total'];
        }

        self::assertGreaterThan(100, \count(array_unique($totals)), 'les tapis varient d\'un jour à l\'autre');
    }
}
