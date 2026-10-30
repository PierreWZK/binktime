<?php

namespace App\Controller;

use App\Entity\Tournament;
use App\Poker\ClockView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * L'horloge : un lien public en lecture seule (la télé du salon) et une télécommande
 * derrière le lien secret (le téléphone de l'organisateur). Le temps est décompté
 * dans le navigateur ; le serveur garde l'état de référence et le resynchronise.
 */
final class ClockController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/horloge/{token}', name: 'clock_public', methods: ['GET'])]
    public function public(#[MapEntity(mapping: ['token' => 'publicToken'])] Tournament $tournament): Response
    {
        return $this->page($tournament, false);
    }

    #[Route('/pilotage/{token}/horloge', name: 'clock_remote', methods: ['GET'])]
    public function remote(#[MapEntity(mapping: ['token' => 'adminToken'])] Tournament $tournament): Response
    {
        return $this->page($tournament, true);
    }

    #[Route('/horloge/{token}/etat', name: 'clock_state', methods: ['GET'])]
    public function state(#[MapEntity(mapping: ['token' => 'publicToken'])] Tournament $tournament): JsonResponse
    {
        return $this->json($this->view($tournament));
    }

    #[Route('/pilotage/{token}/horloge/{action}', name: 'clock_action', requirements: ['action' => 'toggle|next|prev'], methods: ['POST'])]
    public function action(#[MapEntity(mapping: ['token' => 'adminToken'])] Tournament $tournament, string $action): JsonResponse
    {
        $now = new \DateTimeImmutable();
        try {
            match ($action) {
                'toggle' => $tournament->toggleClock($now),
                'next' => $tournament->moveLevel(1, $now),
                'prev' => $tournament->moveLevel(-1, $now),
            };
            $this->em->flush();
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()] + $this->view($tournament), Response::HTTP_CONFLICT);
        }

        return $this->json($this->view($tournament));
    }

    private function page(Tournament $tournament, bool $remote): Response
    {
        return $this->render('clock/show.html.twig', [
            't' => $tournament,
            'remote' => $remote,
            'state' => $this->view($tournament),
        ]);
    }

    /** @return array<string, mixed> */
    private function view(Tournament $tournament): array
    {
        $now = new \DateTimeImmutable();
        if ($tournament->syncClock($now)) {
            $this->em->flush();
        }

        return ClockView::of($tournament, $now);
    }
}
