<?php

namespace App\Entity;

use App\Repository\SeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeasonRepository::class)]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'seasons')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organizer $organizer;

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Tournament> */
    #[ORM\OneToMany(targetEntity: Tournament::class, mappedBy: 'season')]
    #[ORM\OrderBy(['startsAt' => 'ASC'])]
    private Collection $tournaments;

    public function __construct(Organizer $organizer, string $name)
    {
        $this->organizer = $organizer;
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
        $this->tournaments = new ArrayCollection();
        $organizer->getSeasons()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizer(): Organizer
    {
        return $this->organizer;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Tournament> */
    public function getTournaments(): Collection
    {
        return $this->tournaments;
    }
}
