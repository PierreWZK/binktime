<?php

namespace App\Entity;

use App\Poker\Payout;
use App\Repository\TournamentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TournamentRepository::class)]
class Tournament
{
    public const DRAFT = 'draft';
    public const RUNNING = 'running';
    public const PAUSED = 'paused';
    public const FINISHED = 'finished';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Null pour un tournoi « à la maison », piloté par son lien secret. */
    #[ORM\ManyToOne(inversedBy: 'tournaments')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Organizer $organizer = null;

    #[ORM\ManyToOne(inversedBy: 'tournaments')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Season $season = null;

    #[ORM\Column(length: 80)]
    private string $name;

    /** Lien public de l'horloge (lecture seule). */
    #[ORM\Column(length: 16, unique: true)]
    private string $publicToken;

    /** Lien secret de pilotage, pour les tournois sans compte. */
    #[ORM\Column(length: 32, unique: true)]
    private string $adminToken;

    /** Montants en euros entiers, tapis en jetons ; prix 0 = recave ou add-on désactivé. */
    #[ORM\Column]
    private int $buyIn = 20;

    #[ORM\Column]
    private int $startingStack = 20000;

    #[ORM\Column]
    private int $rebuyPrice = 0;

    #[ORM\Column]
    private int $rebuyStack = 0;

    #[ORM\Column]
    private int $addonPrice = 0;

    #[ORM\Column]
    private int $addonStack = 0;

    /** @var list<int> Pourcentages de la cagnotte par place payée. */
    #[ORM\Column(type: Types::JSON)]
    private array $payouts = [50, 30, 20];

    #[ORM\Column(length: 10)]
    private string $status = self::DRAFT;

    /** État de l'horloge, sauvegardé pour reprendre après un rechargement. */
    #[ORM\Column]
    private int $currentLevel = 0;

    /** Fin du niveau en cours quand l'horloge tourne. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $levelEndsAt = null;

    /** Temps restant figé quand l'horloge est arrêtée (null = durée pleine du niveau). */
    #[ORM\Column(nullable: true)]
    private ?int $remainingSeconds = null;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Level> */
    #[ORM\OneToMany(targetEntity: Level::class, mappedBy: 'tournament', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $levels;

    /** @var Collection<int, Entry> */
    #[ORM\OneToMany(targetEntity: Entry::class, mappedBy: 'tournament', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $entries;

    public function __construct(string $name, ?\DateTimeImmutable $startsAt = null)
    {
        $this->name = $name;
        $this->startsAt = $startsAt ?? new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->publicToken = bin2hex(random_bytes(8));
        $this->adminToken = bin2hex(random_bytes(16));
        $this->levels = new ArrayCollection();
        $this->entries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizer(): ?Organizer
    {
        return $this->organizer;
    }

    public function setOrganizer(?Organizer $organizer): static
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $season): static
    {
        $this->season = $season;
        $season?->getTournaments()->add($this);

        return $this;
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

    public function getPublicToken(): string
    {
        return $this->publicToken;
    }

    public function getAdminToken(): string
    {
        return $this->adminToken;
    }

    public function getBuyIn(): int
    {
        return $this->buyIn;
    }

    public function setBuyIn(int $buyIn): static
    {
        $this->buyIn = $buyIn;

        return $this;
    }

    public function getStartingStack(): int
    {
        return $this->startingStack;
    }

    public function setStartingStack(int $startingStack): static
    {
        $this->startingStack = $startingStack;

        return $this;
    }

    public function getRebuyPrice(): int
    {
        return $this->rebuyPrice;
    }

    public function getRebuyStack(): int
    {
        return $this->rebuyStack;
    }

    public function setRebuy(int $price, int $stack): static
    {
        $this->rebuyPrice = $price;
        $this->rebuyStack = $stack;

        return $this;
    }

    public function getAddonPrice(): int
    {
        return $this->addonPrice;
    }

    public function getAddonStack(): int
    {
        return $this->addonStack;
    }

    public function setAddon(int $price, int $stack): static
    {
        $this->addonPrice = $price;
        $this->addonStack = $stack;

        return $this;
    }

    /** @return list<int> */
    public function getPayouts(): array
    {
        return $this->payouts;
    }

    /** @param list<int> $payouts */
    public function setPayouts(array $payouts): static
    {
        Payout::assertValid($payouts);
        $this->payouts = array_values($payouts);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isFinished(): bool
    {
        return self::FINISHED === $this->status;
    }

    public function getCurrentLevel(): int
    {
        return $this->currentLevel;
    }

    public function setCurrentLevel(int $currentLevel): static
    {
        $this->currentLevel = $currentLevel;

        return $this;
    }

    public function getLevelEndsAt(): ?\DateTimeImmutable
    {
        return $this->levelEndsAt;
    }

    public function setLevelEndsAt(?\DateTimeImmutable $levelEndsAt): static
    {
        $this->levelEndsAt = $levelEndsAt;

        return $this;
    }

    public function getRemainingSeconds(): ?int
    {
        return $this->remainingSeconds;
    }

    public function setRemainingSeconds(?int $remainingSeconds): static
    {
        $this->remainingSeconds = $remainingSeconds;

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Level> */
    public function getLevels(): Collection
    {
        return $this->levels;
    }

    public function addLevel(Level $level): static
    {
        $this->levels->add($level);

        return $this;
    }

    /** @return Collection<int, Entry> */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function getRebuyCount(): int
    {
        return array_sum($this->entries->map(fn (Entry $e) => $e->getRebuys())->toArray());
    }

    public function getAddonCount(): int
    {
        return array_sum($this->entries->map(fn (Entry $e) => $e->getAddons())->toArray());
    }

    /** Cagnotte : buy-ins, recaves et add-ons, sans prélèvement. */
    public function getPrizePool(): int
    {
        return $this->entries->count() * $this->buyIn
            + $this->getRebuyCount() * $this->rebuyPrice
            + $this->getAddonCount() * $this->addonPrice;
    }

    public function getTotalChips(): int
    {
        return $this->entries->count() * $this->startingStack
            + $this->getRebuyCount() * $this->rebuyStack
            + $this->getAddonCount() * $this->addonStack;
    }

    public function getPlayersLeft(): int
    {
        return $this->entries->filter(fn (Entry $e) => $e->isAlive())->count();
    }

    public function getAverageStack(): int
    {
        $left = $this->getPlayersLeft();

        return $left > 0 ? intdiv($this->getTotalChips(), $left) : 0;
    }

    /** Nombre de places payées : jamais plus que de joueurs inscrits. */
    public function getPaidPlaces(): int
    {
        return min(count($this->payouts), $this->entries->count());
    }

    /** @return list<int> Gains par place, de la 1re à la dernière place payée. */
    public function getPrizes(): array
    {
        $places = $this->getPaidPlaces();

        return $places > 0 ? Payout::split($this->getPrizePool(), array_slice($this->payouts, 0, $places)) : [];
    }

    public function getPrizeFor(Entry $entry): int
    {
        $position = $entry->getFinishPosition();

        return null === $position ? 0 : ($this->getPrizes()[$position - 1] ?? 0);
    }
}
