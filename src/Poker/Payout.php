<?php

namespace App\Poker;

/** Répartition de la cagnotte entre les places payées. */
final class Payout
{
    /**
     * Partage la cagnotte au prorata des pourcentages, arrondi à l'euro inférieur ;
     * le reste de l'arrondi va au vainqueur, pour que la somme des gains vaille toujours la cagnotte.
     *
     * @param list<int> $percents ex. [50, 30, 20] ; renormalisés s'ils ne font pas 100 (moins de joueurs que de places)
     *
     * @return list<int>
     */
    public static function split(int $pool, array $percents): array
    {
        if ($pool < 0) {
            throw new \InvalidArgumentException('La cagnotte ne peut pas être négative.');
        }
        self::assertPositive($percents);

        $total = array_sum($percents);
        $prizes = array_map(fn (int $p) => intdiv($pool * $p, $total), array_values($percents));
        $prizes[0] += $pool - array_sum($prizes);

        return $prizes;
    }

    /** @param list<int> $percents */
    public static function assertValid(array $percents): void
    {
        self::assertPositive($percents);
        if (100 !== array_sum($percents)) {
            throw new \InvalidArgumentException('La répartition doit faire 100 %.');
        }
        $sorted = $percents;
        rsort($sorted);
        if ($sorted !== array_values($percents)) {
            throw new \InvalidArgumentException('Une place ne peut pas gagner plus que la précédente.');
        }
    }

    /** @param list<int> $percents */
    private static function assertPositive(array $percents): void
    {
        if ([] === $percents) {
            throw new \InvalidArgumentException('Il faut au moins une place payée.');
        }
        foreach ($percents as $p) {
            if (!\is_int($p) || $p <= 0) {
                throw new \InvalidArgumentException('Chaque place payée doit recevoir un pourcentage positif.');
            }
        }
    }
}
