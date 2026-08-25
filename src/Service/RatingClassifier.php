<?php

declare(strict_types=1);

namespace App\Service;

final class RatingClassifier
{
    /**
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
