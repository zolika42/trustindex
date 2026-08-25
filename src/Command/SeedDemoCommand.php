<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed-demo',
    description: 'Adds a small deterministic data set for local/demo use.',
)]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewRepository $reviews,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->reviews->count() > 0) {
            $output->writeln('<comment>Database already contains reviews; skipping demo seed.</comment>');

            return Command::SUCCESS;
        }

        $rows = [
            ['Trustindex', 5, 'Fast setup, clear reporting and a surprisingly smooth review workflow.', 'alex@example.com'],
            ['Trustindex', 4, 'The dashboard is easy to understand and the widgets look polished.', 'maria@example.com'],
            ['Northwind Coffee', 5, 'Friendly service, great coffee and consistently quick delivery.', 'sam@example.com'],
            ['Northwind Coffee', 5, 'Exactly the kind of small business experience I come back for.', 'nora@example.com'],
            ['Acme Hosting', 3, 'Reliable overall, although support response times could be improved.', 'dev@example.com'],
            ['Acme Hosting', 4, 'Good value and the migration was handled professionally.', 'ops@example.com'],
            ['Contoso Studio', 2, 'Nice portfolio, but communication during the project was inconsistent.', 'kim@example.com'],
        ];

        foreach ($rows as [$company, $rating, $text, $email]) {
            $review = (new Review())
                ->setCompanyName($company)
                ->setRating($rating)
                ->setReviewText($text)
                ->setAuthorEmail($email);

            $this->entityManager->persist($review);
        }

        $this->entityManager->flush();

        $output->writeln(sprintf('<info>Seeded %d demo reviews.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
