<?php

namespace App\Poker;

use App\Entity\Season;
use App\Entity\Tournament;

/**
 * Classement de saison, recalculé à la lecture à partir des tournois terminés :
 * il est donc à jour dès qu'un tournoi se termine.
 */
final class SeasonStandings
{
    /** Points = joueurs − place + 1, doublés pour les places payées. */
    public static function points(int $place, int $entrants, int $paidPlaces): int
    {
        if ($place < 1 || $place > $entrants) {
            throw new \InvalidArgumentException(sprintf('Place %d impossible pour %d joueurs.', $place, $entrants));
        }
        $points = $entrants - $place + 1;

        return $place <= $paidPlaces ? 2 * $points : $points;
    }

    /**
     * Triés par points, puis victoires, puis meilleure place.
     *
     * @return list<array{name: string, points: int, tournaments: int, wins: int, best: int, winnings: int}>
     */
    public static function compute(Season $season): array
    {
        $rows = [];
        foreach ($season->getTournaments() as $tournament) {
            if (!$tournament->isFinished()) {
                continue;
            }
            self::addTournament($rows, $tournament);
        }

        usort($rows, fn (array $a, array $b) => [$b['points'], $b['wins'], $a['best']] <=> [$a['points'], $a['wins'], $b['best']]);

        return array_values($rows);
    }

    /** @param array<string, array{name: string, points: int, tournaments: int, wins: int, best: int, winnings: int}> $rows */
    private static function addTournament(array &$rows, Tournament $tournament): void
    {
        $entrants = $tournament->getEntries()->count();
        $paid = $tournament->getPaidPlaces();

        foreach ($tournament->getEntries() as $entry) {
            $place = $entry->getFinishPosition();
            if (null === $place) {
                continue;
            }
            $key = $entry->getPlayerKey();
            $rows[$key] ??= ['name' => $entry->getPlayerName(), 'points' => 0, 'tournaments' => 0, 'wins' => 0, 'best' => PHP_INT_MAX, 'winnings' => 0];
            $rows[$key]['points'] += self::points($place, $entrants, $paid);
            ++$rows[$key]['tournaments'];
            $rows[$key]['wins'] += 1 === $place ? 1 : 0;
            $rows[$key]['best'] = min($rows[$key]['best'], $place);
            $rows[$key]['winnings'] += $tournament->getPrizeFor($entry);
        }
    }
}
