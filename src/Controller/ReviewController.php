<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Review;
use App\Entity\ReviewLike;
use App\Entity\User;
use App\Form\ReviewType;
use App\Repository\BookRepository;
use App\Repository\ReviewRepository;
use App\Service\EntityService;
use App\Service\GoogleBooksService;
use App\Trait\AuthenticationTrait;
use App\Trait\ResponseTrait;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use App\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\Response;


final class ReviewController extends AbstractApiController
{
    use AuthenticationTrait;
    use ResponseTrait;
    
    protected EntityService $entityService;
    private ReviewRepository $reviewRepository;
    private BookRepository $bookRepository;
    private FormFactoryInterface $formFactory;
    private GoogleBooksService $googleBooksService;
    
    public function __construct(
        ReviewRepository $reviewRepository,
        BookRepository $bookRepository,
        FormFactoryInterface $formFactory,
        GoogleBooksService $googleBooksService,
        EntityService $entityService,
        EntityManagerInterface $entityManager,
        Security $security,
        SerializerInterface $serializer
    ) {
        parent::__construct($entityService, $entityManager, $security, $serializer);
        $this->reviewRepository = $reviewRepository;
        $this->bookRepository = $bookRepository;
        $this->formFactory = $formFactory;
        $this->googleBooksService = $googleBooksService;
    }

    #[Route('/api/reviews', name: 'create_review', methods: ['POST'])]
    public function createReview(Request $request): JsonResponse
    {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }
        
        $user = $this->security->getUser();
        $data = json_decode($request->getContent(), true);
        $googleBookId = $data['bookId'] ?? null;

        if (!$googleBookId) {
            return $this->errorResponse('Book ID is required');
        }

        unset($data['bookId']);

        // Find or fetch the book
        $book = $this->bookRepository->findOneBy(['googleBookId' => $googleBookId]);

        if (!$book) {
            $bookData = $this->googleBooksService->fetchBookById($googleBookId);
            if (!$bookData) {
                return $this->errorResponse('Book not found', 404);
            }

            $book = new Book();
            $book->setGoogleBookId($bookData['googleBookId']);
            $book->setTitle($bookData['title']);
            $book->setAuthors($bookData['authors']);
            $book->setThumbnail($bookData['thumbnail']);
            $book->setDescription($bookData['description']);
            $book->setPageCount($bookData['pageCount']);
            $book->setCategories($bookData['categories']);
            $book->setAverageRating($bookData['averageRating']);

            $this->entityManager->persist($book);
        }

        // Check for existing review by the same user for the book
        $existingReview = $this->entityManager->getRepository(Review::class)->findOneBy([
            'user' => $user,
            'book' => $book,
        ]);

        if ($existingReview) {
            return $this->errorResponse(
                'You have already reviewed this book. Please update your review instead.',
                409
            );
        }

        // Create and validate the review
        $review = new Review();
        $form = $this->formFactory->create(ReviewType::class, $review);
        $form->submit($data);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $errors = $this->entityService->getFormErrors($form);
            return $this->errorResponse('Validation failed', 422, $errors);
        }

        $review->setUser($user);
        $review->setBook($book);
        $review->setCreatedAt(new \DateTimeImmutable());
        $review->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($review);

        // Manually add review to the book's reviews collection
        $book->getReviews()->add($review);

        // Calculate average properly
        $book->calculateAverageRating();

        $this->entityManager->flush();

        return $this->successResponse('Review created successfully', [], 201);
    }

    #[Route('/api/reviews/{id}', name: 'update_review', methods: ['PUT'])]
    public function updateReview(int $id, Request $request): JsonResponse
    {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }
        
        $user = $this->security->getUser();
        $review = $this->entityService->findEntity(Review::class, $id);

        if (!$review) {
            return $this->errorResponse('Review not found', 404);
        }

        $ownershipError = $this->checkResourceOwnership($review->getUser(), $this->security);
        if ($ownershipError) {
            return $ownershipError;
        }

        $data = json_decode($request->getContent(), true);

        // Prevent book reassignment
        unset($data['bookId']);

        $form = $this->formFactory->create(ReviewType::class, $review);
        $form->submit($data, false); // false = partial update

        if (!$form->isSubmitted() || !$form->isValid()) {
            $errors = $this->entityService->getFormErrors($form);
            return $this->errorResponse('Validation failed', 400, $errors);
        }

        $review->setUpdatedAt(new \DateTimeImmutable());

        // Recalculate book rating + review count
        $book = $review->getBook();
        $book->calculateAverageRating();

        $this->entityManager->flush();

        return $this->successResponse('Review updated successfully');
    }

    #[Route('/api/reviews', name: 'get_all_reviews', methods: ['GET'])]
    public function getAllReviews(Request $request): JsonResponse
    {
        $query = $request->query->get('q');
        $sort = $request->query->get('sort', 'createdAt');
        $direction = $request->query->get('direction', 'desc');

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, (int) $request->query->get('limit', 5)); // max 50 per page
        $offset = ($page - 1) * $limit;

        $qb = $this->reviewRepository->createQueryBuilder('r')
            ->leftJoin('r.book', 'b')
            ->addSelect('b')
            ->leftJoin('r.user', 'u')
            ->addSelect('u');

        if ($query) {
            $qb->andWhere('r.content LIKE :query OR b.title LIKE :query OR u.name LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        $allowedSortFields = ['createdAt', 'rating'];
        if (!in_array($sort, $allowedSortFields)) {
            $sort = 'createdAt';
        }

        $qb->orderBy('r.' . $sort, $direction === 'asc' ? 'ASC' : 'DESC');

        // Clone the query builder for total count
        $countQb = clone $qb;
        $total = count($countQb->getQuery()->getResult());

        // Apply pagination
        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        $reviews = $qb->getQuery()->getResult();

        $json = $this->serializer->serialize($reviews, 'json', [
            'groups' => ['review_detail'],
            'circular_reference_handler' => fn ($object) => $object->getId(),
        ]);

        return $this->paginatedResponse(
            json_decode($json),
            $total,
            $page,
            $limit
        );
    }

    #[Route('/api/reviews', name: 'get_reviews_by_book', methods: ['GET'])]
    public function getReviewsByBook(Request $request): JsonResponse
    {
        $bookId = $request->query->get('bookId');

        if (!$bookId) {
            return $this->errorResponse('Missing bookId parameter', 400);
        }

        $book = $this->entityManager->getRepository(Book::class)->findOneBy(['googleBookId' => $bookId]);

        if (!$book) {
            return $this->errorResponse('Book not found', 404);
        }

        $user = $this->security->getUser();
        $reviews = $book->getReviews();

        $normalizedReviews = array_map(function ($review) use ($user) {
            $data = json_decode($this->serializer->serialize($review, 'json', ['groups' => ['review_detail']]), true);

            $data['likesCount'] = count($review->getLikedBy());
            $data['likedByCurrentUser'] = $user ? $review->getLikedBy()->contains($user) : false;

            return $data;
        }, $reviews->toArray());

        return new JsonResponse($normalizedReviews, 200);
    }

    #[Route('/api/reviews/{id}', name: 'delete_review', methods: ['DELETE'])]
    public function deleteReview(int $id): JsonResponse
    {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }
        
        $user = $this->security->getUser();
        $review = $this->entityService->findEntity(Review::class, $id);

        if (!$review) {
            return $this->errorResponse('Review not found', 404);
        }

        // Check if user is the review author, an admin, or a moderator
        $isAuthor = $review->getUser()->getId() === $user->getId();
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        $isModerator = $this->security->isGranted('ROLE_MODERATOR');

        if (!$isAuthor && !$isAdmin && !$isModerator) {
            return $this->errorResponse(
                'Access denied. You must be the review author, an admin, or a moderator to delete this review.',
                403
            );
        }

        $book = $review->getBook();
        $this->entityManager->remove($review);
        $book->calculateAverageRating();
        $this->entityManager->flush();

        return $this->successResponse('Review deleted successfully');
    }

    #[Route('/api/reviews/{id}/like', name: 'like_review', methods: ['POST'])]
    public function likeReview(int $id): JsonResponse
    {
        try {
            $authError = $this->checkAuthentication($this->security);
            if ($authError) {
                return $authError;
            }

            $review = $this->entityService->findEntity(Review::class, $id);
            if (!$review) {
                return $this->errorResponse('Review not found', Response::HTTP_NOT_FOUND);
            }

            $user = $this->security->getUser();
            if ($review->isLikedBy($user)) {
                return $this->errorResponse('Review already liked', Response::HTTP_CONFLICT);
            }

            $review->addLike($user);
            $this->entityManager->flush();

            return $this->successResponse('Review liked successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to like review: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/reviews/{id}/unlike', name: 'unlike_review', methods: ['POST'])]
    public function unlikeReview(int $id): JsonResponse
    {
        try {
            $authError = $this->checkAuthentication($this->security);
            if ($authError) {
                return $authError;
            }

            $review = $this->entityService->findEntity(Review::class, $id);
            if (!$review) {
                return $this->errorResponse('Review not found', Response::HTTP_NOT_FOUND);
            }

            $user = $this->security->getUser();
            if (!$review->isLikedBy($user)) {
                return $this->errorResponse('Review not liked', Response::HTTP_NOT_FOUND);
            }

            $review->removeLike($user);
            $this->entityManager->flush();

            return $this->successResponse('Review unliked successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to unlike review: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/reviews/{id}', name: 'get_review', methods: ['GET'])]
    public function getReview(int $id): JsonResponse
    {
        try {
            $review = $this->entityService->findEntity(Review::class, $id);
            if (!$review) {
                return $this->errorResponse('Review not found', Response::HTTP_NOT_FOUND);
            }

            return $this->jsonResponse($review, $this->serializer, ['review_detail']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve review: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/book/{googleBookId}', name: 'get_book_reviews', methods: ['GET'])]
    public function getBookReviews(string $googleBookId, Request $request): JsonResponse
    {
        try {
            $book = $this->bookRepository->findOneBy(['googleBookId' => $googleBookId]);
            if (!$book) {
                return $this->errorResponse('Book not found', Response::HTTP_NOT_FOUND);
            }

            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(50, (int) $request->query->get('limit', 20)));
            $sort = $request->query->get('sort', 'createdAt');
            $direction = $request->query->get('direction', 'desc');

            $reviews = $this->reviewRepository->findByBook($book, $page, $limit, $sort, $direction);
            $total = $this->reviewRepository->count(['book' => $book]);
            $totalPages = ceil($total / $limit);

            return $this->jsonResponse([
                'data' => $reviews,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ], $this->serializer, ['review_list']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve book reviews: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
