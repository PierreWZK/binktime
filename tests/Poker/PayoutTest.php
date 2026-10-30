<?php

namespace App\Tests\Poker;

use App\Entity\Entry;
use App\Entity\Tournament;
use App\Poker\Payout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PayoutTest extends TestCase
{
    public function testMaquetteDouzeJoueursAVingtEuros(): void
    {
        self::assertSame([120, 72, 48], Payout::split(240, [50, 30, 20]));
    }

    public function testLeResteDeLArrondiVaAuVainqueur(): void
    {
        // 50 % de 105 = 52,5 ; 30 % = 31,5 ; 20 % = 21
        self::assertSame([53, 31, 21], Payout::split(105, [50, 30, 20]));
    }

    /** @return iterable<array{int, list<int>}> */
    public static function cagnottes(): iterable
    {
        $repartitions = [[50, 30, 20], [100], [60, 40], [40, 25, 15, 12, 8], [34, 33, 33], [7, 3]];
        foreach ([0, 1, 7, 99, 240, 1001, 12345] as $pool) {
            foreach ($repartitions as $percents) {
                yield "$pool € en ".implode('/', $percents) => [$pool, $percents];
            }
        }
    }

    /** @param list<int> $percents */
    #[DataProvider('cagnottes')]
    public function testLaSommeDesGainsVautLaCagnotte(int $pool, array $percents): void
    {
        $prizes = Payout::split($pool, $percents);

        self::assertSame($pool, array_sum($prizes));
        self::assertCount(\count($percents), $prizes);
        foreach ($prizes as $prize) {
            self::assertGreaterThanOrEqual(0, $prize);
        }
    }

    public function testMoinsDeJoueursQueDePlacesPayees(): void
    {
        $tournament = (new Tournament('Deux joueurs'))->setBuyIn(20);
        new Entry($tournament, 'Ana');
        new Entry($tournament, 'Bao');

        // 50/30 renormalisés sur 40 € : 25 et 15
        self::assertSame(2, $tournament->getPaidPlaces());
        self::assertSame([25, 15], $tournament->getPrizes());
    }

    public function testRecavesEtAddOnsDansLaCagnotte(): void
    {
        $tournament = (new Tournament('Soirée'))->setBuyIn(20)->setRebuy(20, 20000)->setAddon(10, 30000);
        foreach (['Ana', 'Bao', 'Chloé', 'Dan'] as $name) {
            new Entry($tournament, $name);
        }
        $tournament->getEntries()[0]->addRebuy()->addRebuy();
        $tournament->getEntries()[1]->addAddon();

        self::assertSame(4 * 20 + 2 * 20 + 10, $tournament->getPrizePool());
        self::assertSame($tournament->getPrizePool(), array_sum($tournament->getPrizes()));
    }

    /** @return iterable<array{list<int>}> */
    public static function repartitionsInvalides(): iterable
    {
        yield 'vide' => [[]];
        yield 'pas 100 %' => [[50, 30]];
        yield 'négatif' => [[110, -10]];
        yield 'zéro' => [[100, 0]];
        yield 'ordre inversé' => [[20, 30, 50]];
    }

    /** @param list<int> $percents */
    #[DataProvider('repartitionsInvalides')]
    public function testRepartitionInvalideRefusee(array $percents): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Tournament('Test'))->setPayouts($percents);
    }
}
