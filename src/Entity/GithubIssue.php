<?php

namespace App\Entity;

use App\Repository\GithubIssueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GithubIssueRepository::class)]
#[ORM\Table(name: 'github_issue')]
#[ORM\UniqueConstraint(name: 'UNIQ_REPO_NUMBER', fields: ['repo', 'number'])]
class GithubIssue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $repo;

    #[ORM\Column]
    private int $number;

    #[ORM\Column(length: 500)]
    private string $title;

    #[ORM\Column(length: 500)]
    private string $url;

    #[ORM\Column]
    private \DateTimeImmutable $githubCreatedAt;

    #[ORM\Column]
    private \DateTimeImmutable $githubUpdatedAt;

    #[ORM\Column(enumType: TriageStatus::class)]
    private TriageStatus $triageStatus = TriageStatus::New;

    #[ORM\Column]
    private \DateTimeImmutable $syncedAt;

    public function __construct(string $repo, int $number)
    {
        $this->repo = $repo;
        $this->number = $number;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRepo(): string
    {
        return $this->repo;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getGithubCreatedAt(): \DateTimeImmutable
    {
        return $this->githubCreatedAt;
    }

    public function setGithubCreatedAt(\DateTimeImmutable $githubCreatedAt): static
    {
        $this->githubCreatedAt = $githubCreatedAt;

        return $this;
    }

    public function getGithubUpdatedAt(): \DateTimeImmutable
    {
        return $this->githubUpdatedAt;
    }

    public function setGithubUpdatedAt(\DateTimeImmutable $githubUpdatedAt): static
    {
        $this->githubUpdatedAt = $githubUpdatedAt;

        return $this;
    }

    public function getTriageStatus(): TriageStatus
    {
        return $this->triageStatus;
    }

    public function setTriageStatus(TriageStatus $triageStatus): static
    {
        $this->triageStatus = $triageStatus;

        return $this;
    }

    public function getSyncedAt(): \DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(\DateTimeImmutable $syncedAt): static
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }
}
