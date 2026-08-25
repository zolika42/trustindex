<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Review;
use App\Form\ReviewType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP orchestration boundary for review listing, creation and detail rendering.
 *
 * Query semantics stay in ReviewRepository and validation stays on Review/ReviewType; this controller
 * only translates HTTP input into those collaborators and chooses the appropriate response semantics.
 */
final class ReviewController extends AbstractController
{
    /**
     * Renders the newest-first public review feed plus search/rating filters and matching overview stats.
     * Invalid rating query values intentionally degrade to no rating filter rather than an HTTP error.
     */
    #[Route('/', name: 'app_review_index', methods: ['GET'])]
    public function index(Request $request, ReviewRepository $reviews): Response
    {
        $search = trim($request->query->getString('q'));
        $rating = $request->query->getInt('rating');
        $rating = \in_array($rating, [1, 2, 3, 4, 5], true) ? $rating : null;

        return $this->render('review/index.html.twig', [
            'reviews' => $reviews->findForHomepage($search, $rating),
            'overview' => $reviews->getOverallStatistics($search),
            'search' => $search,
            'rating' => $rating,
        ]);
    }

    /**
     * Handles the complete review form lifecycle: map/validate, persist valid input, flash and redirect.
     * Submitted invalid forms render with HTTP 422 so validation failures remain explicit non-500 responses.
     */
    #[Route('/reviews/new', name: 'app_review_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $review = new Review();
        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($review);
            $entityManager->flush();

            $this->addFlash('success', 'Köszönjük a véleményed!');

            return $this->redirectToRoute('app_review_index');
        }

        $response = $form->isSubmitted()
            ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY)
            : null;

        return $this->render('review/new.html.twig', [
            'form' => $form,
        ], $response);
    }

    /**
     * Renders one Doctrine-resolved review; Symfony handles numeric requirements and missing-entity 404s.
     */
    #[Route('/reviews/{id}', name: 'app_review_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Review $review): Response
    {
        return $this->render('review/show.html.twig', [
            'review' => $review,
        ]);
    }
}
