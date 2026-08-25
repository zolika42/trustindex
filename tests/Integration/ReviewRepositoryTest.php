<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use App\Tests\DatabaseResetTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReviewRepositoryTest extends KernelTestCase
{
    use DatabaseResetTrait;

    private EntityManagerInterface $entityManager;
    private ReviewRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(ReviewRepository::class);
        $this->resetDatabase($this->entityManager);
    }

    public function testCompanyStatisticsCalculateAveragesAndSortDeterministically(): void
    {
        $this->persistReview('Acme', 5);
        $this->persistReview('Acme', 3);
        $this->persistReview('Beta', 5);
        $this->persistReview('Charlie', 4);
        $this->persistReview('Charlie', 4);
        $this->entityManager->flush();

        $statistics = $this->repository->getCompanyStatistics();

        self::assertSame(['Beta', 'Charlie', 'Acme'], array_column($statistics, 'companyName'));
        self::assertSame(5.0, $statistics[0]['averageRating']);
        self::assertSame(4.0, $statistics[1]['averageRating']);
        self::assertSame(2, $statistics[1]['reviewCount']);
        self::assertSame(4.0, $statistics[2]['averageRating']);
        self::assertSame(2, $statistics[2]['reviewCount']);
    }

    public function testHomepageQuerySupportsCompanySearchAndRatingFilterTogether(): void
    {
        $this->persistReview('Trustindex', 5);
        $this->persistReview('Trustindex', 3);
        $this->persistReview('Other Corp', 5);
        $this->entityManager->flush();

        $reviews = $this->repository->findForHomepage('trust', 5);

        self::assertCount(1, $reviews);
        self::assertSame('Trustindex', $reviews[0]->getCompanyName());
        self::assertSame(5, $reviews[0]->getRating());
    }

    private function persistReview(string $company, int $rating): void
    {
        $review = (new Review())
            ->setCompanyName($company)
            ->setRating($rating)
            ->setReviewText('Meaningful repository test review.')
            ->setAuthorEmail('test@example.com');

        $this->entityManager->persist($review);
    }
}
