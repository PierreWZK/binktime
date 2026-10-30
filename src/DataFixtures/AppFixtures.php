<?php

namespace App\DataFixtures;

use App\Entity\Entry;
use App\Entity\Organizer;
use App\Entity\Season;
use App\Entity\Tournament;
use App\Poker\BlindTemplates;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Un établissement avec une saison (3 tournois terminés, 1 en cours) et un tournoi à la maison. */
class AppFixtures extends Fixture
{
    private const PLAYERS = ['Camille', 'Hugo', 'Inès', 'Léo', 'Manon', 'Nathan', 'Sarah', 'Théo', 'Yasmine', 'Louis', 'Jade', 'Rayan'];

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(17);

        // Compte de démo, pour tester l'espace organisateur en local
        $organizer = (new Organizer('Le Cercle du Losange', 'le-cercle-du-losange'))->setCity('Lyon')->setEmail('demo@binktime.test');
        $organizer->setPassword($this->hasher->hashPassword($organizer, 'demo-binktime-17'));
        $season = new Season($organizer, 'Saison d\'automne 2026');
        $manager->persist($organizer);
        $manager->persist($season);

        foreach (['2026-09-18', '2026-10-02', '2026-10-16'] as $i => $date) {
            $tournament = $this->tournament($organizer, $season, sprintf('Main Event #%d', $i + 1), $date);
            $ranking = self::PLAYERS;
            shuffle($ranking);
            foreach ($ranking as $name) {
                $entry = new Entry($tournament, $name);
                if (mt_rand(1, 4) === 1) {
                    $entry->addRebuy();
                }
            }
            foreach ($tournament->getEntries() as $place => $entry) {
                0 === $place ? $entry->declareWinner() : $entry->eliminate($place + 1);
            }
            $tournament->setStatus(Tournament::FINISHED)->setFinishedAt(new \DateTimeImmutable($date.' 23:40'));
            $manager->persist($tournament);
        }

        // Le tournoi du soir, au niveau VII comme sur la maquette : 7 joueurs sur 12
        $live = $this->tournament($organizer, $season, 'Main Event #4', 'today 20:30');
        foreach (self::PLAYERS as $i => $name) {
            $entry = new Entry($live, $name);
            if ($i >= 7) {
                $entry->eliminate(19 - $i);
            }
        }
        $live->setStatus(Tournament::RUNNING)
            ->setCurrentLevel(7)
            ->setLevelEndsAt(new \DateTimeImmutable('+12 minutes 21 seconds'));
        $manager->persist($live);

        $home = (new Tournament('Soirée chez Max', new \DateTimeImmutable('next saturday 21:00')))->setBuyIn(10);
        BlindTemplates::apply($home, 'turbo');
        foreach (['Max', 'Julie', 'Karim', 'Zoé', 'Paul', 'Lina'] as $name) {
            new Entry($home, $name);
        }
        $manager->persist($home);

        $manager->flush();
    }

    private function tournament(Organizer $organizer, Season $season, string $name, string $date): Tournament
    {
        $tournament = (new Tournament($name, new \DateTimeImmutable($date.(str_contains($date, ':') ? '' : ' 20:30'))))
            ->setOrganizer($organizer)
            ->setSeason($season)
            ->setBuyIn(20)
            ->setRebuy(20, 20000)
            ->setAddon(10, 30000);
        BlindTemplates::apply($tournament, 'classique');

        return $tournament;
    }
}
