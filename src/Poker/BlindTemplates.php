<?php

namespace App\Poker;

use App\Entity\Level;
use App\Entity\Tournament;

/** Les modèles de structure fournis : mêmes blindes, rythme et tapis différents. */
final class BlindTemplates
{
    public const TEMPLATES = [
        'turbo' => ['label' => 'Turbo', 'minutes' => 10, 'stack' => 10000, 'pitch' => 'Un tournoi bouclé en deux heures.'],
        'classique' => ['label' => 'Classique', 'minutes' => 20, 'stack' => 20000, 'pitch' => 'Le rythme d\'une soirée entre amis.'],
        'deep' => ['label' => 'Deep stack', 'minutes' => 30, 'stack' => 30000, 'pitch' => 'Des tapis profonds, du temps pour jouer.'],
    ];

    private const BREAK_MINUTES = 10;

    /** Petite blinde, grosse blinde, ante ; null = pause. */
    private const STRUCTURE = [
        [25, 50, 0], [50, 100, 0], [75, 150, 0], [100, 200, 25],
        null,
        [200, 400, 50], [300, 600, 75], [400, 800, 100], [500, 1000, 100], [600, 1200, 150],
        null,
        [800, 1600, 200], [1000, 2000, 250], [1500, 3000, 400], [2000, 4000, 500], [3000, 6000, 750],
        null,
        [4000, 8000, 1000], [5000, 10000, 1000], [6000, 12000, 1500], [8000, 16000, 2000], [10000, 20000, 2500],
    ];

    /** Remplit le tournoi avec la structure et le tapis du modèle. */
    public static function apply(Tournament $tournament, string $key): void
    {
        $template = self::TEMPLATES[$key] ?? throw new \InvalidArgumentException(sprintf('Modèle de structure inconnu : %s', $key));

        foreach ($tournament->getLevels() as $level) {
            $tournament->getLevels()->removeElement($level);
        }
        foreach (self::STRUCTURE as $position => $blinds) {
            $tournament->addLevel(null === $blinds
                ? Level::pause($tournament, $position, self::BREAK_MINUTES)
                : Level::blinds($tournament, $position, $template['minutes'], ...$blinds));
        }
        $tournament->setStartingStack($template['stack']);
    }
}
