<?php

namespace App\Twig\Components;

use App\Entity\GithubIssue;
use App\Entity\TriageStatus;
use App\Repository\GithubIssueRepository;
use App\Service\GithubIssueSyncer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class IssueBoard
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?string $repoFilter = null;

    public function __construct(
        private readonly GithubIssueSyncer $syncer,
        private readonly GithubIssueRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<GithubIssue>
     */
    public function getIssues(): array
    {
        return $this->repository->findAllOrderedByRepoAndNumber($this->repoFilter);
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->repository->findLastSyncedAt();
    }

    #[LiveAction]
    public function sync(): void
    {
        $this->syncer->sync();
    }

    #[LiveAction]
    public function setStatus(#[LiveArg] int $issueId, #[LiveArg] string $status): void
    {
        $issue = $this->repository->find($issueId);
        if (null === $issue) {
            return;
        }

        $issue->setTriageStatus(TriageStatus::from($status));
        $this->entityManager->flush();
    }
}
