<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Un joueur inscrit à un tournoi, avec ses recaves, add-ons et sa place finale. */
#[ORM\Entity]
class Entry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\Column(length: 40)]
    private string $playerName;

    #[ORM\Column]
    private int $rebuys = 0;

    #[ORM\Column]
    private int $addons = 0;

    /** Place finale : fixée à l'élimination (le premier éliminé finit dernier), 1 pour le vainqueur. */
    #[ORM\Column(nullable: true)]
    private ?int $finishPosition = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $eliminatedAt = null;

    public function __construct(Tournament $tournament, string $playerName)
    {
        $this->tournament = $tournament;
        $this->playerName = trim($playerName);
        $tournament->getEntries()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    /** Clé qui relie un même joueur d'un tournoi à l'autre dans la saison. */
    public function getPlayerKey(): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', $this->playerName));
    }

    public function getRebuys(): int
    {
        return $this->rebuys;
    }

    public function addRebuy(): static
    {
        ++$this->rebuys;

        return $this;
    }

    public function getAddons(): int
    {
        return $this->addons;
    }

    public function addAddon(): static
    {
        ++$this->addons;

        return $this;
    }

    public function getFinishPosition(): ?int
    {
        return $this->finishPosition;
    }

    public function isAlive(): bool
    {
        return null === $this->eliminatedAt && 1 !== $this->finishPosition;
    }

    public function getEliminatedAt(): ?\DateTimeImmutable
    {
        return $this->eliminatedAt;
    }

    public function eliminate(int $position): static
    {
        $this->finishPosition = $position;
        $this->eliminatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function declareWinner(): static
    {
        $this->finishPosition = 1;

        return $this;
    }

    /** Annule une élimination faite par erreur. */
    public function revive(): static
    {
        $this->finishPosition = null;
        $this->eliminatedAt = null;

        return $this;
    }
}
