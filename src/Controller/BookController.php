<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Service\EntityService;
use App\Service\GoogleBooksService;
use App\Service\OpenLibraryService;
use App\Trait\AuthenticationTrait;
use App\Trait\ResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use App\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\Response;

class BookController extends AbstractApiController
{
    use AuthenticationTrait;
    use ResponseTrait;
    
    private BookRepository $bookRepository;
    private GoogleBooksService $googleBooksService;
    private OpenLibraryService $openLibraryService;
    
    public function __construct(
        BookRepository $bookRepository,
        GoogleBooksService $googleBooksService,
        OpenLibraryService $openLibraryService,
        EntityService $entityService,
        EntityManagerInterface $entityManager,
        Security $security,
        SerializerInterface $serializer
    ) {
        parent::__construct($entityService, $entityManager, $security, $serializer);
        $this->bookRepository = $bookRepository;
        $this->googleBooksService = $googleBooksService;
        $this->openLibraryService = $openLibraryService;
    }
    
    #[Route('/api/books/search', name: 'book_search', methods: ['GET'])]
    public function searchBooks(Request $request): JsonResponse {
        try {
            $query = $request->query->get('q');
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(50, (int) $request->query->get('limit', 10)));

            if (!$query) {
                return $this->errorResponse('Missing search query', Response::HTTP_BAD_REQUEST);
            }

            // Fetch local books with pagination
            $qb = $this->bookRepository->createQueryBuilder('b')
                ->where('LOWER(b.title) LIKE LOWER(:query)')
                ->setParameter('query', '%' . strtolower($query) . '%');

            // Get total count
            $totalLocalBooks = (int) $qb->select('COUNT(b.id)')
                ->getQuery()
                ->getSingleScalarResult();

            // Apply pagination
            $localBooks = $qb->select('b')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            // Create a set of existing Google Book IDs to deduplicate
            $localGoogleIds = array_map(fn($book) => $book->getGoogleBookId(), $localBooks);

            // Fetch external books with pagination
            try {
                $externalBooksResult = $this->googleBooksService->searchBooks($query, $page, $limit);
                $externalBooks = $externalBooksResult['items'];
            } catch (\Throwable $e) {
                return $this->errorResponse(
                    'Error fetching from Google Books API: ' . $e->getMessage(),
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            // Deduplicate external books that already exist locally
            $filteredExternalBooks = array_filter($externalBooks, function ($book) use ($localGoogleIds) {
                return !in_array($book['googleBookId'], $localGoogleIds, true);
            });

            // Serialize local entities
            $serializedLocalBooks = json_decode($this->serializer->serialize($localBooks, 'json', ['groups' => ['book_list']]), true);

            // Merge results
            $mergedResults = array_merge($serializedLocalBooks, $filteredExternalBooks);

            // Calculate total items and pages based on actual results
            $totalItems = count($mergedResults);
            $totalPages = ceil($totalItems / $limit);

            return $this->jsonResponse([
                'data' => $mergedResults,
                'pagination' => [
                    'total' => $totalItems,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ], $this->serializer, ['book_list']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to search books: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/books/{googleBookId}', name: 'get_single_book', methods: ['GET'])]
    public function getBook(string $googleBookId): JsonResponse
    {
        try {
            $book = $this->bookRepository->findOneBy(['googleBookId' => $googleBookId]);

            if (!$book) {
                return $this->errorResponse('Book not found', Response::HTTP_NOT_FOUND);
            }

            $user = $this->security->getUser();

            // Serialize the book
            $responseData = json_decode($this->serializer->serialize($book, 'json', ['groups' => ['book_detail', 'review_detail']]), true);

            // Check if the user has reviewed this book
            $hasReviewed = false;
            if ($user) {
                foreach ($book->getReviews() as $review) {
                    if ($review->getUser() && $review->getUser()->getId() === $user->getId()) {
                        $hasReviewed = true;
                        break;
                    }
                }
            }

            // Append extra info
            $responseData['reviewedByCurrentUser'] = $hasReviewed;

            return $this->jsonResponse($responseData, $this->serializer, ['book_detail', 'review_detail']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve book: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/books', name: 'get_all_books', methods: ['GET'])]
    public function getAllBooks(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(50, (int) $request->query->get('limit', 10)));
            $sort = $request->query->get('sort', 'title');
            $direction = $request->query->get('direction', 'asc');

            $books = $this->bookRepository->findBy([], [$sort => $direction], $limit, ($page - 1) * $limit);
            $total = $this->bookRepository->count([]);
            $totalPages = ceil($total / $limit);

            return $this->jsonResponse([
                'data' => $books,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ], $this->serializer, ['book_list']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve books: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/books/open-library/{key}', name: 'add_book_from_open_library', methods: ['POST'])]
    public function addBookFromOpenLibrary(string $key): JsonResponse
    {
        try {
            $authError = $this->checkAuthentication($this->security);
            if ($authError) {
                return $authError;
            }

            // Check if book already exists
            $existingBook = $this->bookRepository->findOneBy(['googleBookId' => $key]);
            if ($existingBook) {
                return $this->errorResponse('Book already exists in the database', Response::HTTP_CONFLICT);
            }

            // Fetch book data from Open Library
            $bookData = $this->openLibraryService->fetchBookByKey($key);
            if (!$bookData) {
                return $this->errorResponse('Book not found in Open Library', Response::HTTP_NOT_FOUND);
            }

            // Create new book entity
            $book = new Book();
            $book->setGoogleBookId($bookData['googleBookId']);
            $book->setTitle($bookData['title']);
            $book->setAuthors($bookData['authors']);
            $book->setDescription($bookData['description']);
            $book->setPageCount($bookData['pageCount']);
            $book->setCategories($bookData['categories']);
            $book->setThumbnail($bookData['thumbnail']);
            $book->setAverageRating($bookData['averageRating']);

            // Persist the book
            $this->entityManager->persist($book);
            $this->entityManager->flush();

            return $this->successResponse('Book added successfully', [
                'book' => json_decode($this->serializer->serialize($book, 'json', ['groups' => ['book_detail']]), true)
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to add book: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/books/author/{authorName}', name: 'get_author_details', methods: ['GET'])]
    public function getAuthorDetails(string $authorName): JsonResponse
    {
        try {
            // URL decode the author name
            $decodedAuthorName = urldecode($authorName);
            
            $authorData = $this->openLibraryService->searchAuthor($decodedAuthorName);
            
            if (!$authorData) {
                return $this->errorResponse('Author not found', Response::HTTP_NOT_FOUND);
            }

            return $this->jsonResponse($authorData, $this->serializer);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch author details: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
