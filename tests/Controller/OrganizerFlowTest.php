<?php

namespace App\Tests\Controller;

use App\Entity\Organizer;
use App\Entity\Season;
use App\Entity\Tournament;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrganizerFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($this->em);
        $schema->dropDatabase();
        $schema->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    public function testEspaceReserveAuxConnectes(): void
    {
        $this->client->request('GET', '/espace');
        self::assertResponseRedirects('/connexion');
    }

    public function testInscriptionSaisonTournoiEtClassement(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer le compte', [
            'name' => 'Le Club des As',
            'city' => 'Nantes',
            'email' => 'Club@Exemple.test',
            'password' => 'un-mot-de-passe-solide',
        ]);
        self::assertResponseRedirects('/espace');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Le Club des As');

        $this->client->submitForm('Créer', ['name' => 'Saison 1']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.season-list', 'Saison 1');

        $this->client->request('GET', '/tournoi/nouveau');
        $this->client->submitForm('Créer le tournoi', ['name' => 'Tournoi du mardi']);
        self::assertResponseRedirects();

        $organizer = $this->em->getRepository(Organizer::class)->findOneBy(['email' => 'club@exemple.test']);
        self::assertSame('le-club-des-as', $organizer->getSlug());
        $tournament = $this->em->getRepository(Tournament::class)->findOneBy(['name' => 'Tournoi du mardi']);
        self::assertSame($organizer->getId(), $tournament->getOrganizer()->getId());
        self::assertSame('Saison 1', $tournament->getSeason()->getName());

        // On joue le tournoi jusqu'au bout : le classement public se met à jour
        foreach (['Ana', 'Bao', 'Chloé'] as $name) {
            $tournament->addPlayer($name);
        }
        $tournament->eliminate($tournament->getEntries()[2]);
        $tournament->eliminate($tournament->getEntries()[1]);
        $this->em->flush();

        $this->client->request('GET', '/etablissement/le-club-des-as');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.standings__table tbody tr:first-child', 'Ana');
        self::assertSelectorTextContains('.event__winner', 'Ana');
    }

    public function testSaisonDUnAutreEtablissementRefusee(): void
    {
        $other = (new Organizer('Autre salle', 'autre-salle'))->setEmail('autre@exemple.test')->setPassword('x');
        $foreign = new Season($other, 'Saison des autres');
        $me = (new Organizer('Ma salle', 'ma-salle'))->setEmail('moi@exemple.test')->setPassword('x');
        new Season($me, 'Ma saison');
        foreach ([$other, $foreign, $me, ...$me->getSeasons()] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        $this->client->loginUser($me);
        $crawler = $this->client->request('GET', '/tournoi/nouveau');
        $form = $crawler->selectButton('Créer le tournoi')->form(['name' => 'Intrus']);
        $form['season']->disableValidation()->setValue((string) $foreign->getId());
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field__error', 'Saison inconnue');
        self::assertNull($this->em->getRepository(Tournament::class)->findOneBy(['name' => 'Intrus']));
    }

    public function testConnexionAvecMauvaisMotDePasse(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer le compte', ['name' => 'Salle', 'email' => 'salle@exemple.test', 'password' => 'bon-mot-de-passe']);
        $this->client->request('GET', '/deconnexion');

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', ['email' => 'salle@exemple.test', 'password' => 'mauvais']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--error', 'incorrect');
    }
}
