<?php

namespace App\Tests\Entity;

use App\Entity\Tournament;
use App\Poker\BlindTemplates;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    private \DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $this->t0 = new \DateTimeImmutable('2026-10-30 20:30:00');
    }

    private function at(string $offset): \DateTimeImmutable
    {
        return $this->t0->modify($offset);
    }

    private function turbo(): Tournament
    {
        $t = new Tournament('Turbo');
        BlindTemplates::apply($t, 'turbo'); // niveaux de 10 min, pause de 10 min après le IV

        return $t;
    }

    public function testAvantLeDepartLeTempsEstLaDureePleine(): void
    {
        $t = $this->turbo();
        self::assertSame(Tournament::DRAFT, $t->getStatus());
        self::assertSame(600, $t->getClockRemaining($this->t0));
    }

    public function testPauseEtRepriseGardentLeTempsRestant(): void
    {
        $t = $this->turbo();
        $t->toggleClock($this->t0);
        $t->toggleClock($this->at('+3 minutes 20 seconds'));
        self::assertSame(Tournament::PAUSED, $t->getStatus());
        self::assertSame(400, $t->getClockRemaining($this->at('+1 hour')));

        $t->toggleClock($this->at('+1 hour'));
        self::assertSame(390, $t->getClockRemaining($this->at('+1 hour 10 seconds')));
    }

    public function testRepriseAuBonNiveauApresUnRechargement(): void
    {
        $t = $this->turbo();
        $t->toggleClock($this->t0);

        // 35 min plus tard : I, II, III finis (30 min), 5 min dans le IV
        self::assertTrue($t->syncClock($this->at('+35 minutes')));
        self::assertSame(3, $t->getCurrentLevel());
        self::assertSame(300, $t->getClockRemaining($this->at('+35 minutes')));

        // 47 min : le IV est fini, on est dans la pause (position 4) depuis 7 min
        $t->syncClock($this->at('+47 minutes'));
        self::assertTrue($t->getLevelAt($t->getCurrentLevel())->isBreak());
        self::assertSame(180, $t->getClockRemaining($this->at('+47 minutes')));
        self::assertFalse($t->syncClock($this->at('+47 minutes')));
    }

    public function testFinDeStructureArreteLHorloge(): void
    {
        $t = $this->turbo();
        $t->toggleClock($this->t0);
        $t->syncClock($this->at('+2 days'));

        self::assertSame($t->getLevels()->count() - 1, $t->getCurrentLevel());
        self::assertSame(Tournament::PAUSED, $t->getStatus());
        self::assertSame(0, $t->getClockRemaining($this->at('+2 days')));
    }

    public function testChangerDeNiveauRepartDeLaDureePleine(): void
    {
        $t = $this->turbo();
        $t->toggleClock($this->t0);
        $t->moveLevel(1, $this->at('+4 minutes'));
        self::assertSame(1, $t->getCurrentLevel());
        self::assertSame(600, $t->getClockRemaining($this->at('+4 minutes')));

        $t->toggleClock($this->at('+5 minutes'));
        $t->moveLevel(-1, $this->at('+6 minutes'));
        self::assertSame(0, $t->getCurrentLevel());
        self::assertSame(600, $t->getClockRemaining($this->at('+6 minutes')));

        $this->expectException(\DomainException::class);
        $t->moveLevel(-1, $this->at('+6 minutes'));
    }
}
