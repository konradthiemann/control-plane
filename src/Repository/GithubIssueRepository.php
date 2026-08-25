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
    public function findAllOrderedByRepoAndNumber(?string $repo = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->orderBy('i.repo', 'ASC')
            ->addOrderBy('i.number', 'ASC');

        if (null !== $repo) {
            $qb->andWhere('i.repo = :repo')->setParameter('repo', $repo);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<string, int>
     */
    public function countGroupedByRepo(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.repo AS repo', 'COUNT(i.id) AS count')
            ->groupBy('i.repo')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['repo']] = (int) $row['count'];
        }

        return $counts;
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
