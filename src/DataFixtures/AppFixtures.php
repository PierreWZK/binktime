<?php

namespace App\DataFixtures;

use App\Demo\DemoData;
use App\Entity\Entry;
use App\Entity\Tournament;
use App\Poker\BlindTemplates;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** La démo de l'établissement, plus un tournoi à la maison (sans compte). */
class AppFixtures extends Fixture
{
    public function __construct(private readonly DemoData $demo)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Compte de démo pour tester l'espace organisateur en local uniquement (en production : app:demo:reset)
        $this->demo->reset('demo-binktime-17');

        $home = (new Tournament('Soirée chez Max', new \DateTimeImmutable('next saturday 21:00')))->setBuyIn(10);
        BlindTemplates::apply($home, 'turbo');
        foreach (['Max', 'Julie', 'Karim', 'Zoé', 'Paul', 'Lina'] as $name) {
            new Entry($home, $name);
        }
        $manager->persist($home);
        $manager->flush();
    }
}
