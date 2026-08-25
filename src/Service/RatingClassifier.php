<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Pure mapping service that turns a numeric company average into a presentation rating band.
 *
 * Keeping thresholds outside controllers/templates makes boundary behavior independently unit-testable
 * and prevents duplicated label/CSS decisions in views.
 */
final class RatingClassifier
{
    /**
     * Maps an average rating to the stable label/CSS pair consumed by the company leaderboard.
     * Threshold comparisons are intentionally inclusive at each upper band boundary.
     *
     * @return array{label: string, cssClass: string}
     */
    public function classify(float $averageRating): array
    {
        return match (true) {
            $averageRating >= 4.5 => ['label' => 'Excellent', 'cssClass' => 'excellent'],
            $averageRating >= 3.5 => ['label' => 'Very good', 'cssClass' => 'very-good'],
            $averageRating >= 2.5 => ['label' => 'Good', 'cssClass' => 'good'],
            $averageRating >= 1.5 => ['label' => 'Mixed', 'cssClass' => 'mixed'],
            default => ['label' => 'Needs improvement', 'cssClass' => 'needs-improvement'],
        };
    }
}
