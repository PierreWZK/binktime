<?php

namespace App\Demo;

use App\Entity\Entry;
use App\Entity\Organizer;
use App\Entity\Season;
use App\Entity\Tournament;
use App\Poker\BlindTemplates;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * L'établissement de démo : une saison de 3 tournois terminés et un tournoi en cours au niveau VII,
 * comme sur la maquette. Les dates suivent le jour courant pour que la démo reste fraîche.
 */
final class DemoData
{
    public const SLUG = 'le-cercle-du-losange';
    public const EMAIL = 'demo@binktime.test';
    private const PLAYERS = ['Camille', 'Hugo', 'Inès', 'Léo', 'Manon', 'Nathan', 'Sarah', 'Théo', 'Yasmine', 'Louis', 'Jade', 'Rayan'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /** Supprime l'ancienne démo (tournois, saisons, inscriptions) puis la recrée. */
    public function reset(string $password): Organizer
    {
        $old = $this->em->getRepository(Organizer::class)->findOneBy(['slug' => self::SLUG]);
        if ($old) {
            foreach ($old->getTournaments() as $tournament) {
                $this->em->remove($tournament);
            }
            $this->em->remove($old);
            $this->em->flush();
        }

        mt_srand(17);
        $organizer = (new Organizer('Le Cercle du Losange', self::SLUG))->setCity('Lyon')->setEmail(self::EMAIL);
        $organizer->setPassword($this->hasher->hashPassword($organizer, $password));
        $season = new Season($organizer, 'Saison d\'automne');
        $this->em->persist($organizer);
        $this->em->persist($season);

        foreach (['-6 weeks', '-4 weeks', '-2 weeks'] as $i => $when) {
            $tournament = $this->tournament($organizer, $season, sprintf('Main Event #%d', $i + 1), new \DateTimeImmutable("$when 20:30"));
            $ranking = self::PLAYERS;
            shuffle($ranking);
            foreach ($ranking as $name) {
                $entry = new Entry($tournament, $name);
                if (1 === mt_rand(1, 4)) {
                    $entry->addRebuy();
                }
            }
            foreach ($tournament->getEntries() as $place => $entry) {
                0 === $place ? $entry->declareWinner() : $entry->eliminate($place + 1);
            }
            $tournament->setStatus(Tournament::FINISHED)->setFinishedAt($tournament->getStartsAt()->modify('+3 hours 10 minutes'));
            $this->em->persist($tournament);
        }

        // Le tournoi du soir : 7 joueurs sur 12, niveau VII
        $live = $this->tournament($organizer, $season, 'Main Event #4', new \DateTimeImmutable('today 20:30'));
        foreach (self::PLAYERS as $i => $name) {
            $entry = new Entry($live, $name);
            if ($i >= 7) {
                $entry->eliminate(19 - $i);
            }
        }
        $live->setStatus(Tournament::RUNNING)
            ->setCurrentLevel(7)
            ->setLevelEndsAt(new \DateTimeImmutable('+12 minutes 21 seconds'));
        $this->em->persist($live);

        $this->em->flush();

        return $organizer;
    }

    private function tournament(Organizer $organizer, Season $season, string $name, \DateTimeImmutable $startsAt): Tournament
    {
        $tournament = (new Tournament($name, $startsAt))
            ->setOrganizer($organizer)
            ->setSeason($season)
            ->setBuyIn(20)
            ->setRebuy(20, 20000)
            ->setAddon(10, 30000);
        BlindTemplates::apply($tournament, 'classique');

        return $tournament;
    }
}
