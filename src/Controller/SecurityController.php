<?php

namespace App\Controller;

use App\Entity\Organizer;
use App\Repository\OrganizerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('organizer_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'email' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError(),
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le pare-feu.');
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, OrganizerRepository $organizers, UserPasswordHasherInterface $hasher, EntityManagerInterface $em, Security $security): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('organizer_dashboard');
        }
        $values = ['name' => '', 'city' => '', 'email' => ''];
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton de sécurité invalide, rechargez la page.');
            }
            $values = array_map(fn ($v) => trim(preg_replace('/\s+/', ' ', (string) $v)), array_intersect_key($request->request->all(), $values)) + $values;
            $password = (string) $request->request->get('password');

            if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 80) {
                $errors['name'] = 'Le nom de l\'établissement doit faire entre 2 et 80 caractères.';
            }
            if (mb_strlen($values['city']) > 80) {
                $errors['city'] = '80 caractères au plus.';
            }
            if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 180) {
                $errors['email'] = 'Adresse e-mail invalide.';
            } elseif ($organizers->findOneBy(['email' => mb_strtolower($values['email'])])) {
                $errors['email'] = 'Un compte existe déjà avec cette adresse.';
            }
            if (mb_strlen($password) < 10 || mb_strlen($password) > 4096) {
                $errors['password'] = 'Au moins 10 caractères.';
            }

            if (!$errors) {
                $organizer = (new Organizer($values['name'], $this->uniqueSlug($values['name'], $organizers)))
                    ->setCity($values['city'] ?: null)
                    ->setEmail($values['email']);
                $organizer->setPassword($hasher->hashPassword($organizer, $password));
                $em->persist($organizer);
                $em->flush();

                $security->login($organizer, 'form_login', 'main');
                $this->addFlash('success', sprintf('Bienvenue, %s. Créez votre première saison pour lancer le classement.', $organizer->getName()));

                return $this->redirectToRoute('organizer_dashboard');
            }
        }

        return $this->render('security/register.html.twig', ['values' => $values, 'errors' => $errors], new Response(status: $errors ? 422 : 200));
    }

    private function uniqueSlug(string $name, OrganizerRepository $organizers): string
    {
        $base = (string) (new AsciiSlugger('fr'))->slug($name)->lower()->truncate(80, '', false) ?: 'etablissement';
        $slug = $base;
        for ($i = 2; $organizers->findOneBy(['slug' => $slug]); ++$i) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
