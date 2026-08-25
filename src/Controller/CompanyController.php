<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ReviewRepository;
use App\Service\RatingClassifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyController extends AbstractController
{
    #[Route('/companies', name: 'app_company_index', methods: ['GET'])]
    public function index(
        Request $request,
        ReviewRepository $reviews,
        RatingClassifier $ratingClassifier,
    ): Response {
        $search = trim($request->query->getString('q'));
        $statistics = $reviews->getCompanyStatistics($search);

        foreach ($statistics as &$company) {
            $company['ratingBand'] = $ratingClassifier->classify($company['averageRating']);
        }
        unset($company);

        return $this->render('company/index.html.twig', [
            'companies' => $statistics,
            'search' => $search,
        ]);
    }
}
