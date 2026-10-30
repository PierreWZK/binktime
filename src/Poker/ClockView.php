<?php

namespace App\Poker;

use App\Entity\Level;
use App\Entity\Tournament;
use App\Twig\PokerExtension;

/** Ce que l'horloge affiche, en tableau prêt pour le JSON et pour le premier rendu Twig. */
final class ClockView
{
    /** @return array<string, mixed> */
    public static function of(Tournament $t, \DateTimeImmutable $now): array
    {
        $current = $t->getLevelAt($t->getCurrentLevel());
        $next = null;
        $breakIn = null;
        foreach ($t->getLevels() as $level) {
            if ($level->getPosition() <= $t->getCurrentLevel()) {
                continue;
            }
            if ($level->isBreak()) {
                $breakIn ??= $level->getPosition() - $t->getCurrentLevel() - 1;
            } else {
                $next ??= $level;
            }
        }
        $winner = $t->getEntries()->findFirst(fn ($k, $e) => 1 === $e->getFinishPosition());

        return [
            'name' => $t->getName(),
            'status' => $t->getStatus(),
            'remaining' => $t->getClockRemaining($now),
            'duration' => ($current?->getMinutes() ?? 0) * 60,
            'level' => $current ? self::level($t, $current) : null,
            'next' => $next ? self::level($t, $next) : null,
            'breakIn' => $current?->isBreak() ? null : $breakIn,
            'playersLeft' => $t->getPlayersLeft(),
            'entrants' => $t->getEntries()->count(),
            'averageStack' => $t->getAverageStack(),
            'prizePool' => $t->getPrizePool(),
            'paidPlaces' => $t->getPaidPlaces(),
            'rebuys' => $t->getRebuyCount(),
            'addons' => $t->getAddonCount(),
            'winner' => $winner?->getPlayerName(),
        ];
    }

    /** @return array{number: int, roman: string, small: int, big: int, ante: int, minutes: int, isBreak: bool} */
    private static function level(Tournament $t, Level $level): array
    {
        $number = $t->getLevelNumber($level);

        return [
            'number' => $number,
            'roman' => PokerExtension::roman($number),
            'small' => $level->getSmallBlind(),
            'big' => $level->getBigBlind(),
            'ante' => $level->getAnte(),
            'minutes' => $level->getMinutes(),
            'isBreak' => $level->isBreak(),
        ];
    }
}
