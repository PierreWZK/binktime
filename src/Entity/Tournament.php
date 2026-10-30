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
        $organizer?->getTournaments()->add($this);

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

    public function getLevelAt(int $index): ?Level
    {
        return $this->levels->toArray()[$index] ?? null;
    }

    /**
     * Rattrape le temps écoulé depuis la dernière sauvegarde : les niveaux terminés s'enchaînent,
     * si bien qu'après un rechargement l'horloge reprend au bon niveau. Renvoie vrai si l'état a changé.
     */
    public function syncClock(\DateTimeImmutable $now): bool
    {
        $changed = false;
        while (self::RUNNING === $this->status && null !== $this->levelEndsAt && $now >= $this->levelEndsAt) {
            $next = $this->getLevelAt($this->currentLevel + 1);
            if (null === $next) {
                // fin de la structure : on reste sur le dernier niveau, horloge à zéro
                $this->status = self::PAUSED;
                $this->remainingSeconds = 0;
                $this->levelEndsAt = null;

                return true;
            }
            ++$this->currentLevel;
            $this->levelEndsAt = $this->levelEndsAt->modify(sprintf('+%d minutes', $next->getMinutes()));
            $changed = true;
        }

        return $changed;
    }

    public function getClockRemaining(\DateTimeImmutable $now): int
    {
        if (self::RUNNING === $this->status && null !== $this->levelEndsAt) {
            return max(0, $this->levelEndsAt->getTimestamp() - $now->getTimestamp());
        }

        return $this->remainingSeconds ?? ($this->getLevelAt($this->currentLevel)?->getMinutes() ?? 0) * 60;
    }

    /** Lance ou arrête l'horloge. */
    public function toggleClock(\DateTimeImmutable $now): void
    {
        $this->assertNotFinished();
        $this->syncClock($now);
        if (self::RUNNING === $this->status) {
            $this->remainingSeconds = $this->getClockRemaining($now);
            $this->levelEndsAt = null;
            $this->status = self::PAUSED;
        } else {
            $this->levelEndsAt = $now->modify(sprintf('+%d seconds', $this->getClockRemaining($now)));
            $this->remainingSeconds = null;
            $this->status = self::RUNNING;
        }
    }

    /** Passe au niveau suivant (+1) ou revient au précédent (-1), avec la durée pleine du niveau. */
    public function moveLevel(int $delta, \DateTimeImmutable $now): void
    {
        $this->assertNotFinished();
        $this->syncClock($now);
        $target = $this->getLevelAt($this->currentLevel + $delta) ?? throw new \DomainException($delta > 0 ? 'C\'est le dernier niveau.' : 'C\'est le premier niveau.');
        $this->currentLevel += $delta;
        if (self::RUNNING === $this->status) {
            $this->levelEndsAt = $now->modify(sprintf('+%d minutes', $target->getMinutes()));
        } else {
            $this->remainingSeconds = null;
        }
    }

    /** Numéro affiché du niveau (I, II…), les pauses ne comptent pas. */
    public function getLevelNumber(Level $level): int
    {
        return $this->levels->filter(fn (Level $l) => !$l->isBreak() && $l->getPosition() <= $level->getPosition())->count();
    }

    /** @return Collection<int, Entry> */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    /** Inscription, y compris tardive ; un même nom ne peut pas s'inscrire deux fois. */
    public function addPlayer(string $name): Entry
    {
        $this->assertNotFinished();
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if ('' === $name || mb_strlen($name) > 40) {
            throw new \DomainException('Le nom du joueur doit faire entre 1 et 40 caractères.');
        }
        $entry = new Entry($this, $name);
        foreach ($this->entries as $other) {
            if ($other !== $entry && $other->getPlayerKey() === $entry->getPlayerKey()) {
                $this->entries->removeElement($entry);
                throw new \DomainException(sprintf('%s est déjà inscrit.', $name));
            }
        }
        $this->renumber();

        return $entry;
    }

    /** Retire un joueur encore en jeu (inscrit par erreur). */
    public function removePlayer(Entry $entry): void
    {
        $this->assertNotFinished();
        if (!$entry->isAlive()) {
            throw new \DomainException('On ne retire pas un joueur déjà éliminé : annulez d\'abord son élimination.');
        }
        $this->entries->removeElement($entry);
        $this->renumber();
    }

    /** Le joueur sort à la dernière place encore occupée ; quand il n'en reste qu'un, il gagne et le tournoi se termine. */
    public function eliminate(Entry $entry): void
    {
        $this->assertNotFinished();
        if (!$entry->isAlive()) {
            throw new \DomainException(sprintf('%s est déjà éliminé.', $entry->getPlayerName()));
        }
        if ($this->getPlayersLeft() < 2) {
            throw new \DomainException('Il faut au moins deux joueurs en jeu pour éliminer quelqu\'un.');
        }
        $entry->eliminate($this->getPlayersLeft());

        if (1 === $this->getPlayersLeft()) {
            $this->entries->filter(fn (Entry $e) => $e->isAlive())->first()->declareWinner();
            $this->status = self::FINISHED;
            $this->finishedAt = new \DateTimeImmutable();
            $this->levelEndsAt = null;
        }
    }

    /** Annule la dernière élimination (et la fin du tournoi si elle l'avait déclenchée). */
    public function undoLastElimination(): ?Entry
    {
        $last = $this->getEliminated()[0] ?? null;
        if (null === $last) {
            return null;
        }
        if ($this->isFinished()) {
            foreach ($this->entries as $entry) {
                if (1 === $entry->getFinishPosition()) {
                    $entry->revive();
                }
            }
            // l'horloge reprend arrêtée, ou reste « à venir » si elle n'avait jamais tourné
            $this->status = 0 === $this->currentLevel && null === $this->remainingSeconds ? self::DRAFT : self::PAUSED;
            $this->finishedAt = null;
        }
        $last->revive();

        return $last;
    }

    /** @return list<Entry> Éliminés, du dernier sorti au premier. */
    public function getEliminated(): array
    {
        $out = $this->entries->filter(fn (Entry $e) => null !== $e->getEliminatedAt())->toArray();
        usort($out, fn (Entry $a, Entry $b) => $a->getFinishPosition() <=> $b->getFinishPosition());

        return $out;
    }

    /** @return list<Entry> Le classement : en jeu d'abord, puis vainqueur et éliminés par place. */
    public function getStandings(): array
    {
        $out = $this->entries->toArray();
        usort($out, fn (Entry $a, Entry $b) => [$a->getFinishPosition() ?? 0, $a->getId()] <=> [$b->getFinishPosition() ?? 0, $b->getId()]);

        return $out;
    }

    /** Recale les places des éliminés après une inscription tardive ou un retrait, en gardant leur ordre de sortie. */
    private function renumber(): void
    {
        $position = $this->entries->count();
        $eliminated = $this->getEliminated();
        foreach (array_reverse($eliminated) as $entry) {
            $entry->moveTo($position--);
        }
    }

    public function rebuy(Entry $entry): void
    {
        $this->assertCanBuy($entry, $this->rebuyPrice, 'Pas de recave dans ce tournoi.');
        $entry->addRebuy();
    }

    public function addon(Entry $entry): void
    {
        $this->assertCanBuy($entry, $this->addonPrice, 'Pas d\'add-on dans ce tournoi.');
        $entry->addAddon();
    }

    private function assertCanBuy(Entry $entry, int $price, string $disabled): void
    {
        $this->assertNotFinished();
        if ($price <= 0) {
            throw new \DomainException($disabled);
        }
        if (!$entry->isAlive()) {
            throw new \DomainException(sprintf('%s est éliminé : annulez d\'abord son élimination.', $entry->getPlayerName()));
        }
    }

    private function assertNotFinished(): void
    {
        if ($this->isFinished()) {
            throw new \DomainException('Le tournoi est terminé.');
        }
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
