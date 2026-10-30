<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Un niveau de la structure, ou une pause quand $isBreak est vrai (blindes à 0). */
#[ORM\Entity]
class Level
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'levels')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    /** Ordre dans la structure, de 0 au dernier niveau, pauses comprises. */
    #[ORM\Column]
    private int $position;

    #[ORM\Column]
    private int $smallBlind = 0;

    #[ORM\Column]
    private int $bigBlind = 0;

    #[ORM\Column]
    private int $ante = 0;

    #[ORM\Column]
    private int $minutes;

    #[ORM\Column]
    private bool $isBreak = false;

    public function __construct(Tournament $tournament, int $position, int $minutes)
    {
        $this->tournament = $tournament;
        $this->position = $position;
        $this->minutes = $minutes;
    }

    public static function blinds(Tournament $tournament, int $position, int $minutes, int $small, int $big, int $ante = 0): self
    {
        $level = new self($tournament, $position, $minutes);
        $level->smallBlind = $small;
        $level->bigBlind = $big;
        $level->ante = $ante;

        return $level;
    }

    public static function pause(Tournament $tournament, int $position, int $minutes): self
    {
        $level = new self($tournament, $position, $minutes);
        $level->isBreak = true;

        return $level;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getSmallBlind(): int
    {
        return $this->smallBlind;
    }

    public function getBigBlind(): int
    {
        return $this->bigBlind;
    }

    public function getAnte(): int
    {
        return $this->ante;
    }

    public function getMinutes(): int
    {
        return $this->minutes;
    }

    public function isBreak(): bool
    {
        return $this->isBreak;
    }
}
