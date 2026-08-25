<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
final class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * @return list<Review>
     */
    public function findForHomepage(?string $companySearch = null, ?int $rating = null): array
    {
        $queryBuilder = $this->createQueryBuilder('review')
            ->orderBy('review.createdAt', 'DESC')
            ->addOrderBy('review.id', 'DESC');

        $this->applyCompanySearch($queryBuilder, $companySearch);

        if (null !== $rating) {
            $queryBuilder
                ->andWhere('review.rating = :rating')
                ->setParameter('rating', $rating);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return list<array{
     *     companyName: string,
     *     reviewCount: int,
     *     averageRating: float,
     *     lastReviewAt: \DateTimeImmutable
     * }>
     */
    public function getCompanyStatistics(?string $companySearch = null): array
    {
        $queryBuilder = $this->createQueryBuilder('review')
            ->select(
                'review.companyName AS companyName',
                'COUNT(review.id) AS reviewCount',
                'AVG(review.rating) AS averageRating',
                'MAX(review.createdAt) AS lastReviewAt',
            )
            ->groupBy('review.companyName')
            ->orderBy('averageRating', 'DESC')
            ->addOrderBy('reviewCount', 'DESC')
            ->addOrderBy('review.companyName', 'ASC');

        $this->applyCompanySearch($queryBuilder, $companySearch);

        /** @var list<array{companyName: string, reviewCount: string|int, averageRating: string|float, lastReviewAt: \DateTimeImmutable}> $rows */
        $rows = $queryBuilder->getQuery()->getArrayResult();

        return array_map(
            static fn (array $row): array => [
                'companyName' => $row['companyName'],
                'reviewCount' => (int) $row['reviewCount'],
                'averageRating' => round((float) $row['averageRating'], 2),
                'lastReviewAt' => $row['lastReviewAt'],
            ],
            $rows,
        );
    }

    /**
     * @return array{reviewCount: int, averageRating: float|null}
     */
    public function getOverallStatistics(?string $companySearch = null): array
    {
        $queryBuilder = $this->createQueryBuilder('review')
            ->select('COUNT(review.id) AS reviewCount', 'AVG(review.rating) AS averageRating');

        $this->applyCompanySearch($queryBuilder, $companySearch);

        /** @var array{reviewCount: string|int, averageRating: string|float|null} $row */
        $row = $queryBuilder->getQuery()->getSingleResult();

        return [
            'reviewCount' => (int) $row['reviewCount'],
            'averageRating' => null === $row['averageRating'] ? null : round((float) $row['averageRating'], 2),
        ];
    }

    private function applyCompanySearch(
        \Doctrine\ORM\QueryBuilder $queryBuilder,
        ?string $companySearch,
    ): void {
        $companySearch = trim((string) $companySearch);

        if ('' === $companySearch) {
            return;
        }

        $queryBuilder
            ->andWhere('LOWER(review.companyName) LIKE LOWER(:companySearch)')
            ->setParameter('companySearch', '%'.$companySearch.'%');
    }
}
