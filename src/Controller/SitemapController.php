<?php

namespace App\Controller;

use App\Repository\OrganizerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitemapController extends AbstractController
{
    /** Pages publiques seulement : les adresses à jeton (pilotage, horloge) n'y figurent jamais. */
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'], format: 'xml')]
    public function index(OrganizerRepository $organizers): Response
    {
        $urls = array_map(
            fn (string $route) => $this->generateUrl($route, [], UrlGeneratorInterface::ABSOLUTE_URL),
            ['home', 'game_daily', 'organizer_index', 'tournament_new', 'app_register'],
        );
        foreach ($organizers->findAll() as $organizer) {
            $urls[] = $this->generateUrl('organizer_show', ['slug' => $organizer->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $response = $this->render('sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
