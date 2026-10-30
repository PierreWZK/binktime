<?php

namespace App\Tests\Entity;

use App\Entity\Entry;
use App\Entity\Tournament;
use PHPUnit\Framework\TestCase;

final class TournamentTest extends TestCase
{
    public function testEliminationsDansLOrdreJusquAuVainqueur(): void
    {
        $t = $this->tournament(['Ana', 'Bao', 'Chloé', 'Dan']);
        [$ana, $bao, $chloe, $dan] = $t->getEntries()->toArray();

        $t->eliminate($dan);
        $t->eliminate($bao);
        self::assertSame([4, 3], [$dan->getFinishPosition(), $bao->getFinishPosition()]);
        self::assertFalse($t->isFinished());

        $t->eliminate($chloe);
        self::assertSame(2, $chloe->getFinishPosition());
        self::assertSame(1, $ana->getFinishPosition());
        self::assertTrue($t->isFinished());
        self::assertSame([$ana, $chloe, $bao, $dan], $t->getStandings());
    }

    public function testAnnulerLaDerniereEliminationRouvreLeTournoi(): void
    {
        $t = $this->tournament(['Ana', 'Bao']);
        [$ana, $bao] = $t->getEntries()->toArray();
        $t->eliminate($bao);
        self::assertTrue($t->isFinished());

        self::assertSame($bao, $t->undoLastElimination());
        self::assertFalse($t->isFinished());
        self::assertTrue($ana->isAlive());
        self::assertTrue($bao->isAlive());
        self::assertSame(2, $t->getPlayersLeft());
    }

    public function testInscriptionTardiveDecaleLesPlaces(): void
    {
        $t = $this->tournament(['Ana', 'Bao', 'Chloé']);
        [, , $chloe] = $t->getEntries()->toArray();
        $t->eliminate($chloe);
        self::assertSame(3, $chloe->getFinishPosition());

        $t->addPlayer('Dan');
        self::assertSame(4, $chloe->getFinishPosition());
        self::assertSame(3, $t->getPlayersLeft());

        $dan = $t->getEntries()->last();
        $t->removePlayer($dan);
        self::assertSame(3, $chloe->getFinishPosition());
    }

    public function testDoublonEtNomVideRefuses(): void
    {
        $t = $this->tournament(['Ana']);
        foreach (['  ana ', '', str_repeat('x', 41)] as $name) {
            try {
                $t->addPlayer($name);
                self::fail("« $name » aurait dû être refusé");
            } catch (\DomainException) {
            }
        }
        self::assertCount(1, $t->getEntries());
    }

    public function testRecaveEtAddOn(): void
    {
        $t = $this->tournament(['Ana', 'Bao', 'Chloé'])->setRebuy(20, 20000);
        [$ana, $bao] = $t->getEntries()->toArray();

        $t->rebuy($ana);
        self::assertSame(1, $ana->getRebuys());

        $this->expectException(\DomainException::class);
        $t->addon($ana); // pas d'add-on configuré
    }

    public function testPasDeRecaveApresElimination(): void
    {
        $t = $this->tournament(['Ana', 'Bao', 'Chloé'])->setRebuy(20, 20000);
        $bao = $t->getEntries()[1];
        $t->eliminate($bao);

        $this->expectException(\DomainException::class);
        $t->rebuy($bao);
    }

    public function testRienNeBougeApresLaFin(): void
    {
        $t = $this->tournament(['Ana', 'Bao']);
        $t->eliminate($t->getEntries()[1]);

        $this->expectException(\DomainException::class);
        $t->addPlayer('Chloé');
    }

    public function testLeGagnantTouchePlusQueLesAutres(): void
    {
        $t = $this->tournament(['Ana', 'Bao', 'Chloé', 'Dan'])->setBuyIn(20);
        [$ana, $bao, $chloe, $dan] = $t->getEntries()->toArray();
        foreach ([$dan, $chloe, $bao] as $e) {
            $t->eliminate($e);
        }

        self::assertSame(40, $t->getPrizeFor($ana));
        self::assertSame(24, $t->getPrizeFor($bao));
        self::assertSame(16, $t->getPrizeFor($chloe));
        self::assertSame(0, $t->getPrizeFor($dan));
    }

    /** @param list<string> $names */
    private function tournament(array $names): Tournament
    {
        $t = new Tournament('Test');
        foreach ($names as $name) {
            $t->addPlayer($name);
        }

        return $t;
    }
}
