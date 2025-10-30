<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Service\EntityService;
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

#[Route('/api/reading-list')]
class ReadingListController extends AbstractApiController
{
    use AuthenticationTrait;
    use ResponseTrait;
    
    private BookRepository $bookRepository;
    
    public function __construct(
        BookRepository $bookRepository,
        EntityService $entityService,
        EntityManagerInterface $entityManager,
        Security $security,
        SerializerInterface $serializer
    ) {
        parent::__construct($entityService, $entityManager, $security, $serializer);
        $this->bookRepository = $bookRepository;
    }
    
    #[Route('', name: 'get_reading_list', methods: ['GET'])]
    public function getReadingList(): JsonResponse
    {
        try {
            $authError = $this->checkAuthentication($this->security);
            if ($authError) {
                return $authError;
            }
            
            $user = $this->security->getUser();
            return $this->jsonResponse($user->getReadingList(), $this->serializer, ['book_list']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve reading list: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{googleBookId}', name: 'add_to_reading_list', methods: ['POST'])]
    public function addToReadingList(string $googleBookId): JsonResponse
    {
        try {
            $authError = $this->checkAuthentication($this->security);
            if ($authError) {
                return $authError;
            }

            $book = $this->bookRepository->findOneBy(['googleBookId' => $googleBookId]);
            if (!$book) {
                return $this->errorResponse('Book not found', Response::HTTP_NOT_FOUND);
            }

            $user = $this->security->getUser();
            if ($user->hasInReadingList($book)) {
                return $this->errorResponse('Book already in reading list', Response::HTTP_CONFLICT);
            }

            $user->addToReadingList($book);
            $this->entityManager->flush();

            return $this->successResponse('Book added to reading list successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to add book to reading list: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{googleBookId}', name: 'remove_from_reading_list', methods: ['DELETE'])]
    public function removeFromReadingList(string $googleBookId): JsonResponse
    {
        try {
            $authError = $this->checkAuthentication($this->security);
            if ($authError) {
                return $authError;
            }

            $book = $this->bookRepository->findOneBy(['googleBookId' => $googleBookId]);
            if (!$book) {
                return $this->errorResponse('Book not found', Response::HTTP_NOT_FOUND);
            }

            $user = $this->security->getUser();
            if (!$user->hasInReadingList($book)) {
                return $this->errorResponse('Book not in reading list', Response::HTTP_NOT_FOUND);
            }

            $user->removeFromReadingList($book);
            $this->entityManager->flush();

            return $this->successResponse('Book removed from reading list successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to remove book from reading list: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 