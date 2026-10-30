<?php

namespace App\Controller;

use App\Entity\Tournament;
use App\Poker\BlindTemplates;
use App\Poker\Payout;
use App\Poker\SeasonStandings;
use App\Repository\SeasonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(SeasonRepository $seasons): Response
    {
        // Classement de la saison la plus récente d'un établissement, s'il y en a une
        $season = $seasons->findOneBy([], ['createdAt' => 'DESC']);

        // Extrait du modèle Classique, du niveau V au niveau X, pause comprise
        $example = new Tournament('Exemple');
        BlindTemplates::apply($example, 'classique');

        return $this->render('home/index.html.twig', [
            'season' => $season,
            'standings' => $season ? array_slice(SeasonStandings::compute($season), 0, 5) : [],
            'prizes' => Payout::split(12 * 20, [50, 30, 20]),
            'levels' => array_slice($example->getLevels()->toArray(), 5, 7),
        ]);
    }
}
