<?php

namespace App\Command;

use App\Demo\DemoData;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:demo:reset', 'Recrée l\'établissement de démo (saison, tournois, tournoi en cours)')]
final class DemoResetCommand
{
    public function __construct(private readonly DemoData $demo)
    {
    }

    public function __invoke(OutputInterface $output): int
    {
        // Mot de passe aléatoire jamais affiché : en production, personne ne peut se connecter au compte de démo
        $organizer = $this->demo->reset(bin2hex(random_bytes(24)));
        $output->writeln(sprintf('Démo recréée : %s (%d tournois).', $organizer->getName(), $organizer->getTournaments()->count()));

        return Command::SUCCESS;
    }
}
