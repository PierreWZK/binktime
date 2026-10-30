<?php

namespace App\Tests\Poker;

use App\Entity\Entry;
use App\Entity\Organizer;
use App\Entity\Season;
use App\Entity\Tournament;
use App\Poker\SeasonStandings;
use PHPUnit\Framework\TestCase;

final class SeasonStandingsTest extends TestCase
{
    public function testPointsParPlace(): void
    {
        // 12 joueurs, 3 payés : 24, 22, 20 dans l'argent, puis 9 … 1
        self::assertSame(24, SeasonStandings::points(1, 12, 3));
        self::assertSame(20, SeasonStandings::points(3, 12, 3));
        self::assertSame(9, SeasonStandings::points(4, 12, 3));
        self::assertSame(1, SeasonStandings::points(12, 12, 3));
    }

    public function testPlaceImpossibleRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SeasonStandings::points(5, 4, 3);
    }

    public function testClassementCumuleEtIgnoreLesTournoisEnCours(): void
    {
        $season = new Season(new Organizer('Le Cercle', 'le-cercle'), 'Saison 1');

        // Ana gagne le 1er tournoi, Bao le 2e ; le 3e n'est pas terminé
        $this->finishedTournament($season, ['Ana', 'Bao', 'Chloé', 'Dan']);
        $this->finishedTournament($season, ['bao ', 'Ana', 'Dan', 'Chloé']);
        $running = (new Tournament('En cours'))->setSeason($season);
        (new Entry($running, 'Dan'))->declareWinner();

        $standings = SeasonStandings::compute($season);

        // 4 joueurs, 3 payés : 1er 8, 2e 6, 3e 4, 4e 1
        self::assertSame(['Ana', 'Bao', 'Chloé', 'Dan'], array_column($standings, 'name'));
        self::assertSame([14, 14, 5, 5], array_column($standings, 'points'));
        self::assertSame(2, $standings[0]['tournaments']);
        // égalité de points : Ana et Bao ont 1 victoire, départagés par la meilleure place puis l'ordre stable
        self::assertSame(1, $standings[1]['wins']);
        // 80 € de cagnotte : 40 / 24 / 16
        self::assertSame(40 + 24, $standings[0]['winnings']);
    }

    /** @param list<string> $ranking du vainqueur au premier éliminé */
    private function finishedTournament(Season $season, array $ranking): void
    {
        $tournament = (new Tournament('Tournoi'))->setSeason($season)->setBuyIn(20);
        foreach ($ranking as $i => $name) {
            $entry = new Entry($tournament, $name);
            0 === $i ? $entry->declareWinner() : $entry->eliminate($i + 1);
        }
        $tournament->setStatus(Tournament::FINISHED);
    }
}
