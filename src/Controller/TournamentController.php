<?php

namespace App\Controller;

use App\Entity\Entry;
use App\Entity\Organizer;
use App\Entity\Season;
use App\Entity\Tournament;
use App\Poker\BlindTemplates;
use App\Poker\Payout;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tournois sans compte. Le jeton secret de l'adresse fait office de clé :
 * qui a le lien pilote le tournoi, d'où l'absence de jeton CSRF sur ces formulaires.
 */
final class TournamentController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/tournoi/nouveau', name: 'tournament_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // Connecté : le tournoi appartient à l'établissement et peut compter pour une saison
        $organizer = $this->getUser();
        $organizer = $organizer instanceof Organizer ? $organizer : null;

        $values = ['name' => '', 'startsAt' => (new \DateTimeImmutable('today 20:30'))->format('Y-m-d\TH:i'), 'template' => 'classique',
            'buyIn' => 20, 'rebuyPrice' => 0, 'addonPrice' => 0, 'payouts' => '50 / 30 / 20',
            'season' => (string) $organizer?->getCurrentSeason()?->getId()];
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tournament', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton de sécurité invalide, rechargez la page.');
            }
            foreach (array_keys($values) as $field) {
                $values[$field] = trim((string) $request->request->get($field, ''));
            }
            [$tournament, $errors] = $this->build($values);

            // Seules les saisons de l'établissement connecté sont acceptées
            $season = null;
            if ($organizer && '' !== $values['season']) {
                $season = $organizer->getSeasons()->findFirst(fn ($k, Season $s) => (string) $s->getId() === $values['season']);
                if (null === $season) {
                    $errors['season'] = 'Saison inconnue.';
                }
            }

            if (!$errors) {
                $tournament->setOrganizer($organizer)->setSeason($season);
                $this->em->persist($tournament);
                $this->em->flush();
                $this->addFlash('success', $organizer
                    ? 'Tournoi créé. Vous le retrouvez dans votre espace.'
                    : 'Tournoi créé. Gardez bien ce lien : c\'est lui qui permet de piloter le tournoi.');

                return $this->redirectToRoute('tournament_admin', ['token' => $tournament->getAdminToken()]);
            }
        }

        return $this->render('tournament/new.html.twig', [
            'values' => $values,
            'errors' => $errors,
            'templates' => BlindTemplates::TEMPLATES,
            'organizer' => $organizer,
        ], new Response(status: $errors ? 422 : 200));
    }

    #[Route('/pilotage/{token}', name: 'tournament_admin', methods: ['GET'])]
    public function admin(#[MapEntity(mapping: ['token' => 'adminToken'])] Tournament $tournament): Response
    {
        return $this->render('tournament/admin.html.twig', ['t' => $tournament]);
    }

    #[Route('/pilotage/{token}/joueurs', name: 'tournament_add_player', methods: ['POST'])]
    public function addPlayer(#[MapEntity(mapping: ['token' => 'adminToken'])] Tournament $tournament, Request $request): Response
    {
        // Plusieurs noms d'un coup, un par ligne ou séparés par des virgules
        $names = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $request->request->get('names'))));

        return $this->act($tournament, function () use ($tournament, $names) {
            if (!$names) {
                throw new \DomainException('Saisissez au moins un nom.');
            }
            foreach ($names as $name) {
                $tournament->addPlayer($name);
            }

            return 1 === \count($names) ? sprintf('%s est inscrit.', reset($names)) : sprintf('%d joueurs inscrits.', \count($names));
        });
    }

    #[Route('/pilotage/{token}/joueurs/{id}/{action}', name: 'tournament_player', requirements: ['id' => '\d+', 'action' => 'rebuy|addon|eliminate|remove'], methods: ['POST'])]
    public function player(#[MapEntity(mapping: ['token' => 'adminToken'])] Tournament $tournament, int $id, string $action): Response
    {
        $entry = $tournament->getEntries()->findFirst(fn ($k, Entry $e) => $e->getId() === $id) ?? throw $this->createNotFoundException();

        return $this->act($tournament, function () use ($tournament, $entry, $action) {
            $name = $entry->getPlayerName();
            switch ($action) {
                case 'rebuy':
                    $tournament->rebuy($entry);

                    return sprintf('Recave pour %s.', $name);
                case 'addon':
                    $tournament->addon($entry);

                    return sprintf('Add-on pour %s.', $name);
                case 'remove':
                    $tournament->removePlayer($entry);

                    return sprintf('%s est retiré du tournoi.', $name);
                default:
                    $tournament->eliminate($entry);

                    return $tournament->isFinished()
                        ? sprintf('%s sort %de. Le tournoi est terminé !', $name, $entry->getFinishPosition())
                        : sprintf('%s sort %de.', $name, $entry->getFinishPosition());
            }
        });
    }

    #[Route('/pilotage/{token}/annuler', name: 'tournament_undo', methods: ['POST'])]
    public function undo(#[MapEntity(mapping: ['token' => 'adminToken'])] Tournament $tournament): Response
    {
        return $this->act($tournament, function () use ($tournament) {
            $entry = $tournament->undoLastElimination() ?? throw new \DomainException('Aucune élimination à annuler.');

            return sprintf('%s revient en jeu.', $entry->getPlayerName());
        });
    }

    #[Route('/pilotage/{token}/gains', name: 'tournament_payouts', methods: ['POST'])]
    public function payouts(#[MapEntity(mapping: ['token' => 'adminToken'])] Tournament $tournament, Request $request): Response
    {
        return $this->act($tournament, function () use ($tournament, $request) {
            $tournament->setPayouts(self::parsePayouts((string) $request->request->get('payouts')));

            return 'Répartition des gains mise à jour.';
        });
    }

    /** Applique une action métier, enregistre, et renvoie sur la page de pilotage avec le message. */
    private function act(Tournament $tournament, callable $action): Response
    {
        try {
            $this->addFlash('success', $action());
            $this->em->flush();
        } catch (\DomainException|\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_admin', ['token' => $tournament->getAdminToken()], Response::HTTP_SEE_OTHER);
    }

    /**
     * @param array<string, string> $v
     *
     * @return array{Tournament, array<string, string>}
     */
    private function build(array $v): array
    {
        $errors = [];
        $int = function (string $field, int $max) use ($v, &$errors): int {
            if (!preg_match('/^\d+$/', $v[$field] ?? '') || (int) $v[$field] > $max) {
                $errors[$field] = sprintf('Un nombre entier entre 0 et %s.', number_format($max, 0, ',', ' '));

                return 0;
            }

            return (int) $v[$field];
        };

        $name = preg_replace('/\s+/', ' ', $v['name']);
        if ('' === $name || mb_strlen($name) > 80) {
            $errors['name'] = 'Donnez un nom au tournoi (80 caractères au plus).';
        }
        $startsAt = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $v['startsAt']) ?: null;
        if (null === $startsAt) {
            $errors['startsAt'] = 'Choisissez une date et une heure.';
        }
        if (!isset(BlindTemplates::TEMPLATES[$v['template']])) {
            $errors['template'] = 'Choisissez une structure.';
        }
        $buyIn = $int('buyIn', 10000);
        $rebuy = $int('rebuyPrice', 10000);
        $addon = $int('addonPrice', 10000);
        try {
            $payouts = self::parsePayouts($v['payouts']);
        } catch (\InvalidArgumentException $e) {
            $errors['payouts'] = $e->getMessage();
        }

        $tournament = new Tournament($name, $startsAt);
        if (!$errors) {
            BlindTemplates::apply($tournament, $v['template']);
            $stack = $tournament->getStartingStack();
            // Recave = un tapis de départ, add-on = un tapis et demi : les usages des tournois entre amis
            $tournament->setBuyIn($buyIn)->setRebuy($rebuy, $rebuy ? $stack : 0)->setAddon($addon, $addon ? intdiv($stack * 3, 2) : 0)->setPayouts($payouts);
        }

        return [$tournament, $errors];
    }

    /** « 50 / 30 / 20 » → [50, 30, 20], validé (total 100, ordre décroissant). */
    private static function parsePayouts(string $raw): array
    {
        if (!preg_match('/^\s*\d+(\s*[\/,;\s]\s*\d+)*\s*%?\s*$/', $raw)) {
            throw new \InvalidArgumentException('Écrivez les pourcentages séparés par des « / », par exemple 50 / 30 / 20.');
        }
        $percents = array_map('intval', preg_split('/[^\d]+/', $raw, -1, PREG_SPLIT_NO_EMPTY));
        Payout::assertValid($percents);

        return $percents;
    }
}
