<?php

namespace App\Tests\Poker;

use App\Entity\Tournament;
use App\Poker\BlindTemplates;
use PHPUnit\Framework\TestCase;

final class BlindTemplatesTest extends TestCase
{
    public function testChaqueModeleDonneUneStructureCroissante(): void
    {
        foreach (array_keys(BlindTemplates::TEMPLATES) as $key) {
            $tournament = new Tournament($key);
            BlindTemplates::apply($tournament, $key);

            $previous = 0;
            foreach ($tournament->getLevels() as $i => $level) {
                self::assertSame($i, $level->getPosition());
                if ($level->isBreak()) {
                    continue;
                }
                self::assertSame(2 * $level->getSmallBlind(), $level->getBigBlind());
                self::assertGreaterThan($previous, $level->getBigBlind());
                $previous = $level->getBigBlind();
            }
            self::assertSame(BlindTemplates::TEMPLATES[$key]['stack'], $tournament->getStartingStack());
        }
    }

    public function testModeleInconnuRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BlindTemplates::apply(new Tournament('x'), 'hyper');
    }
}
