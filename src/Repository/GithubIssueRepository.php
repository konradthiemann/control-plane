<?php

namespace App\Repository;

use App\Entity\GithubIssue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GithubIssue>
 */
class GithubIssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GithubIssue::class);
    }

    /**
     * @return list<GithubIssue>
     */
    public function findAllOrderedByRepoAndNumber(): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.repo', 'ASC')
            ->addOrderBy('i.number', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLastSyncedAt(): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('i')
            ->select('MAX(i.syncedAt)')
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $result ? new \DateTimeImmutable($result) : null;
    }
}
