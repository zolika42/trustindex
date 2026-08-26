<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Persisted customer review and validation boundary for public review submission.
 *
 * Form-bound setters deliberately accept nullable values because Symfony may map an empty field to
 * null before validation. They normalize that transient input to stable scalar values; Validator
 * constraints then reject invalid state before Doctrine is allowed to persist it.
 */
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'review')]
#[ORM\Index(columns: ['company_name'], name: 'idx_review_company_name')]
#[ORM\Index(columns: ['rating'], name: 'idx_review_rating')]
#[ORM\HasLifecycleCallbacks]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $companyName = '';

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\Range(min: 1, max: 5)]
    private int $rating = 5;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $reviewText = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    private string $authorEmail = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * Initializes creation and update timestamps together so a new entity is internally consistent
     * before Doctrine persistence occurs.
     */
    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * Returns the Doctrine identity, or null while the review has not yet been inserted.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns the normalized company label used both for display and aggregation identity.
     */
    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    /**
     * Normalizes form input by converting null to an empty string and trimming surrounding space.
     * Empty normalized values remain invalid and are rejected by the NotBlank constraint.
     */
    public function setCompanyName(?string $companyName): self
    {
        $this->companyName = trim($companyName ?? '');

        return $this;
    }

    /**
     * Returns the persisted integer star rating.
     */
    public function getRating(): int
    {
        return $this->rating;
    }

    /**
     * Converts a missing form value to the deliberately invalid sentinel 0 so normal validation can
     * report the required/range error instead of PHP raising a type error during form mapping.
     */
    public function setRating(?int $rating): self
    {
        $this->rating = $rating ?? 0;

        return $this;
    }

    /**
     * Returns the normalized review body exactly as it may be rendered publicly.
     */
    public function getReviewText(): string
    {
        return $this->reviewText;
    }

    /**
     * Trims submitted review text while preserving its internal wording and line content.
     */
    public function setReviewText(?string $reviewText): self
    {
        $this->reviewText = trim($reviewText ?? '');

        return $this;
    }

    /**
     * Returns the private reviewer contact address used for validation/contact purposes only.
     * Public templates must not render this value.
     */
    public function getAuthorEmail(): string
    {
        return $this->authorEmail;
    }

    /**
     * Trims and lowercases reviewer email so persisted contact data has a predictable representation.
     * Validation still owns syntactic email correctness and the 255-character storage boundary.
     */
    public function setAuthorEmail(?string $authorEmail): self
    {
        $this->authorEmail = strtolower(trim($authorEmail ?? ''));

        return $this;
    }

    /**
     * Returns the immutable timestamp captured when the entity was constructed.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Returns the immutable timestamp of the most recent Doctrine-managed update.
     */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Refreshes the update timestamp immediately before Doctrine writes an existing review change.
     */
    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
