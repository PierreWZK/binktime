<?php

namespace App\Controller;

use App\Poker\DailyStack;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GameController extends AbstractController
{
    /**
     * Le jeu se joue dans le navigateur (essais, série, partage) ; le serveur fournit seulement le tapis du jour.
     * shortcut: le total est lisible dans la page, la triche reste possible ; à valider côté serveur si un classement des joueurs arrive.
     */
    #[Route('/jeu', name: 'game_daily', methods: ['GET'])]
    public function daily(): Response
    {
        $stack = DailyStack::forDate(new \DateTimeImmutable());

        $response = $this->render('game/daily.html.twig', [
            'stack' => $stack,
            'chips' => DailyStack::CHIPS,
            'attempts' => DailyStack::ATTEMPTS,
            'reveal' => DailyStack::REVEAL_SECONDS,
        ]);
        // Jamais servi depuis un cache au-delà de minuit
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
