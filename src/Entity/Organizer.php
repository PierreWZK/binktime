<?php

namespace App\Entity;

use App\Repository\OrganizerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Un établissement qui organise des tournois réguliers ; sa page et son classement sont publics. */
#[ORM\Entity(repositoryClass: OrganizerRepository::class)]
class Organizer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(length: 90, unique: true)]
    private string $slug;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $city = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Season> */
    #[ORM\OneToMany(targetEntity: Season::class, mappedBy: 'organizer', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $seasons;

    /** @var Collection<int, Tournament> */
    #[ORM\OneToMany(targetEntity: Tournament::class, mappedBy: 'organizer')]
    #[ORM\OrderBy(['startsAt' => 'DESC'])]
    private Collection $tournaments;

    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->createdAt = new \DateTimeImmutable();
        $this->seasons = new ArrayCollection();
        $this->tournaments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Season> */
    public function getSeasons(): Collection
    {
        return $this->seasons;
    }

    /** @return Collection<int, Tournament> */
    public function getTournaments(): Collection
    {
        return $this->tournaments;
    }
}
