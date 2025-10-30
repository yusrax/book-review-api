<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\ReviewLike;
use App\Entity\User;
use App\Service\EntityService;
use App\Trait\AuthenticationTrait;
use App\Trait\ResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;

class ReviewLikeController extends AbstractController
{
    use AuthenticationTrait;
    use ResponseTrait;
    
    private EntityService $entityService;
    
    public function __construct(EntityService $entityService)
    {
        $this->entityService = $entityService;
    }
    
    #[Route('/api/reviews/{id}/toggle-like', name: 'toggle_like_review', methods: ['POST'])]
    public function toggleLike(
        int $id,
        EntityManagerInterface $em,
        Security $security
    ): JsonResponse {
        $authError = $this->checkAuthentication($security);
        if ($authError) {
            return $authError;
        }
        
        $user = $security->getUser();
        $review = $this->entityService->findEntity(Review::class, $id);

        if (!$review) {
            return $this->errorResponse('Review not found', 404);
        }

        $existingLike = $em->getRepository(ReviewLike::class)->findOneBy([
            'user' => $user,
            'review' => $review,
        ]);

        if ($existingLike) {
            // Already liked — remove
            $em->remove($existingLike);
            $message = 'Review unliked';
            $liked = false;
        } else {
            // Not yet liked — add
            $like = new ReviewLike();
            $like->setUser($user);
            $like->setReview($review);

            $em->persist($like);
            $message = 'Review liked';
            $liked = true;
        }

        $em->flush();

        return $this->successResponse($message, [
            'liked' => $liked,
            'totalLikes' => $review->getLikes()->count(),
        ]);
    }
}

