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

final class ReviewController extends AbstractController
{
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

    #[Route('/reviews/{id}', name: 'app_review_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Review $review): Response
    {
        return $this->render('review/show.html.twig', [
            'review' => $review,
        ]);
    }
}
