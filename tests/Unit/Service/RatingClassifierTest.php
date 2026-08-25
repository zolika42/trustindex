<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\RatingClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RatingClassifierTest extends TestCase
{
    /**
     * @return iterable<string, array{float, string, string}>
     */
    public static function ratingBoundaries(): iterable
    {
        yield 'excellent boundary' => [4.5, 'Excellent', 'excellent'];
        yield 'very good upper band' => [4.49, 'Very good', 'very-good'];
        yield 'very good boundary' => [3.5, 'Very good', 'very-good'];
        yield 'good boundary' => [2.5, 'Good', 'good'];
        yield 'mixed boundary' => [1.5, 'Mixed', 'mixed'];
        yield 'lowest valid score' => [1.0, 'Needs improvement', 'needs-improvement'];
    }

    #[DataProvider('ratingBoundaries')]
    public function testItClassifiesImportantRatingBoundaries(
        float $rating,
        string $expectedLabel,
        string $expectedCssClass,
    ): void {
        $result = (new RatingClassifier())->classify($rating);

        self::assertSame($expectedLabel, $result['label']);
        self::assertSame($expectedCssClass, $result['cssClass']);
    }
}
