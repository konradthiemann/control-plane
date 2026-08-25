<?php

namespace App\Service;

use App\Entity\GithubIssue;
use App\Repository\GithubIssueRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Upserts open GitHub issues into the local table. Local triage status is
 * never overwritten by a re-sync — only GitHub-owned fields are refreshed.
 */
class GithubIssueSyncer
{
    public function __construct(
        private readonly GithubIssuesClient $client,
        private readonly GithubIssueRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function sync(): int
    {
        $issues = $this->client->fetchOpenIssues();
        $now = new \DateTimeImmutable();

        foreach ($issues as $data) {
            $issue = $this->repository->findOneBy(['repo' => $data['repo'], 'number' => $data['number']])
                ?? new GithubIssue($data['repo'], $data['number']);

            $issue->setTitle($data['title']);
            $issue->setUrl($data['url']);
            $issue->setGithubCreatedAt(new \DateTimeImmutable($data['createdAt']));
            $issue->setGithubUpdatedAt(new \DateTimeImmutable($data['updatedAt']));
            $issue->setSyncedAt($now);

            $this->entityManager->persist($issue);
        }

        $this->entityManager->flush();

        return count($issues);
    }
}
