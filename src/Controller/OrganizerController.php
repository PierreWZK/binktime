<?php

namespace App\Controller;

use App\Entity\Organizer;
use App\Entity\Season;
use App\Poker\SeasonStandings;
use App\Repository\OrganizerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class OrganizerController extends AbstractController
{
    #[Route('/espace', name: 'organizer_dashboard', methods: ['GET'])]
    public function dashboard(#[CurrentUser] Organizer $organizer): Response
    {
        return $this->render('organizer/dashboard.html.twig', ['o' => $organizer]);
    }

    #[Route('/espace/saisons', name: 'organizer_season_new', methods: ['POST'])]
    public function newSeason(#[CurrentUser] Organizer $organizer, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('season', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $name = trim(preg_replace('/\s+/', ' ', (string) $request->request->get('name')));
        if ('' === $name || mb_strlen($name) > 80) {
            $this->addFlash('error', 'Donnez un nom à la saison (80 caractères au plus).');
        } else {
            $em->persist(new Season($organizer, $name));
            $em->flush();
            $this->addFlash('success', sprintf('Saison « %s » créée. Les prochains tournois peuvent y être rattachés.', $name));
        }

        return $this->redirectToRoute('organizer_dashboard', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/etablissements', name: 'organizer_index', methods: ['GET'])]
    public function index(OrganizerRepository $organizers): Response
    {
        return $this->render('organizer/index.html.twig', ['organizers' => $organizers->findBy([], ['name' => 'ASC'])]);
    }

    #[Route('/etablissement/{slug}', name: 'organizer_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Organizer $organizer, Request $request): Response
    {
        // ?saison=id pour consulter une saison passée
        $seasonId = $request->query->getInt('saison');
        $season = $organizer->getSeasons()->findFirst(fn ($k, Season $s) => $s->getId() === $seasonId) ?? $organizer->getCurrentSeason();

        return $this->render('organizer/show.html.twig', [
            'o' => $organizer,
            'season' => $season,
            'standings' => $season ? SeasonStandings::compute($season) : [],
        ]);
    }
}
