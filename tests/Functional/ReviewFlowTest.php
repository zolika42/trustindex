<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Review;
use App\Tests\DatabaseResetTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReviewFlowTest extends WebTestCase
{
    use DatabaseResetTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase($this->entityManager);
    }

    public function testUserCanSubmitAndReadAReview(): void
    {
        $crawler = $this->client->request('GET', '/reviews/new');

        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Submit review')->form([
            'review[companyName]' => 'Example Company',
            'review[rating]' => '5',
            'review[reviewText]' => 'A clear, useful review created through the real Symfony Form flow.',
            'review[authorEmail]' => 'Reviewer@Example.com',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/');
        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('.flash', 'Köszönjük a véleményed!');
        self::assertSelectorTextContains('.company-name', 'Example Company');
        self::assertSelectorTextNotContains('body', 'Reviewer@Example.com');

        $link = $crawler->filter('a')->reduce(
            static fn ($node): bool => str_contains($node->text(), 'Read full review'),
        )->link();

        $this->client->click($link);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Example Company');
        self::assertSelectorTextContains('.review-text', 'real Symfony Form flow');
    }

    public function testInvalidSubmissionShowsValidationErrorsAndDoesNotPersist(): void
    {
        $crawler = $this->client->request('GET', '/reviews/new');
        $form = $crawler->selectButton('Submit review')->form([
            'review[companyName]' => '',
            'review[rating]' => '5',
            'review[reviewText]' => '',
            'review[authorEmail]' => 'not-an-email',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.form-error, .invalid-feedback, ul li');

        $count = $this->entityManager->getRepository(Review::class)->count();
        self::assertSame(0, $count);
    }

    public function testPublicSearchAndCompaniesPageExposeExpectedDataOnly(): void
    {
        $this->persistReview('Trustindex', 5, 'Great product.');
        $this->persistReview('Trustindex', 4, 'Solid product.');
        $this->persistReview('Other Corp', 1, 'Not related.');
        $this->entityManager->flush();

        $this->client->request('GET', '/?q=trust&rating=5');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Great product.');
        self::assertSelectorTextNotContains('body', 'Solid product.');
        self::assertSelectorTextNotContains('body', 'Other Corp');

        $this->client->request('GET', '/companies?q=trust');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'Trustindex');
        self::assertSelectorTextContains('tbody', '4.50');
        self::assertSelectorTextContains('tbody', 'Excellent');
        self::assertSelectorTextNotContains('tbody', 'Other Corp');
    }

    private function persistReview(string $company, int $rating, string $text): void
    {
        $review = (new Review())
            ->setCompanyName($company)
            ->setRating($rating)
            ->setReviewText($text)
            ->setAuthorEmail('private@example.com');

        $this->entityManager->persist($review);
    }
}
