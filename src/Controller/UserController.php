<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EntityService;
use App\Service\ImageUploadService;
use App\Form\ProfilePictureUpdateType;
use App\Trait\AuthenticationTrait;
use App\Trait\ResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use App\Controller\AbstractApiController;
use Symfony\Component\Form\FormFactoryInterface;

#[Route('/api/user')]
final class UserController extends AbstractApiController
{
    use AuthenticationTrait;
    use ResponseTrait;
    
    private UserRepository $userRepository;
    private FormFactoryInterface $formFactory;
    private ImageUploadService $imageUploader;
    
    public function __construct(
        UserRepository $userRepository,
        FormFactoryInterface $formFactory,
        ImageUploadService $imageUploader,
        EntityService $entityService,
        EntityManagerInterface $entityManager,
        Security $security,
        SerializerInterface $serializer
    ) {
        parent::__construct($entityService, $entityManager, $security, $serializer);
        $this->userRepository = $userRepository;
        $this->formFactory = $formFactory;
        $this->imageUploader = $imageUploader;
    }

    #[Route('/me', name: 'get_my_profile', methods: ['GET'])]
    public function getMyProfile(): JsonResponse
    {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }
        
        $user = $this->security->getUser();
        return $this->jsonResponse($user, $this->serializer, ['user_detail']);
    }

    #[Route('/update', name: 'update_my_profile', methods: ['PUT'])]
    public function updateMyProfile(Request $request): JsonResponse
    {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }
        
        $user = $this->security->getUser();
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }

        $this->entityManager->flush();

        return $this->successResponse('Profile updated');
    }

    #[Route('/delete', name: 'delete_my_account', methods: ['DELETE'])]
    public function deleteMyAccount(): JsonResponse
    {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }
        
        $user = $this->security->getUser();
        $this->entityService->removeEntity($user);

        return $this->successResponse('Account deleted successfully');
    }

    #[Route('/reviews', name: 'get_my_reviews', methods: ['GET'])]
    public function getMyReviews(): JsonResponse
    {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }
        
        $user = $this->security->getUser();
        return $this->jsonResponse($user->getReviews(), $this->serializer, ['review_detail']);
    }

    #[Route('/reading-list', name: 'get_my_reading_list', methods: ['GET'])]
    public function getMyReadingList(): JsonResponse
    {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }
        
        $user = $this->security->getUser();
        return $this->jsonResponse(
            $user->getReadingList(), 
            $this->serializer, 
            ['book_list', 'book_summary']
        );
    }

    #[Route('/{id}/reviews', name: 'get_user_reviews', methods: ['GET'])]
    public function getUserReviews(int $id): JsonResponse
    {
        $user = $this->entityService->findEntity(User::class, $id);
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        return $this->jsonResponse($user->getReviews(), $this->serializer, ['review_detail']);
    }

    #[Route('/{id}/reading-list', name: 'get_user_reading_list', methods: ['GET'])]
    public function getUserReadingList(int $id): JsonResponse
    {
        $user = $this->entityService->findEntity(User::class, $id);

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        return $this->jsonResponse(
            $user->getReadingList(), 
            $this->serializer, 
            ['book_list', 'book_summary']
        );
    }

    #[Route('/{id}', name: 'get_user_profile', methods: ['GET'])]
    public function getUserProfile(int $id): JsonResponse
    {
        $user = $this->entityService->findEntity(User::class, $id);
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        return $this->jsonResponse($user, $this->serializer, ['user_public_profile']);
    }

    #[Route('/profile-picture', name: 'update_profile_picture', methods: ['POST'])]
    public function updateProfilePicture(
        Request $request,
        ImageUploadService $imageUploadService
    ): JsonResponse {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }
        
        $user = $this->security->getUser();
        $form = $this->createForm(ProfilePictureUpdateType::class);
        $form->handleRequest($request);

        if (!$form->isValid()) {
            $errors = $this->entityService->getFormErrors($form);
            return $this->errorResponse('Validation failed', 400, $errors);
        }

        try {
            $file = $form->get('profilePicture')->getData();
            $profilePicturePath = $imageUploadService->uploadImage($file, 'profile_pictures');
            
            $user->setProfilePicture($profilePicturePath);
            $this->entityManager->flush();

            return $this->successResponse('Profile picture updated successfully', [
                'profilePicture' => $profilePicturePath
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update profile picture: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/{id}/follow', name: 'user_follow', methods: ['POST'])]
    public function followUser(int $id): JsonResponse
    {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }

        $currentUser = $this->security->getUser();
        $userToFollow = $this->userRepository->find($id);
        
        if (!$userToFollow) {
            return $this->errorResponse('User not found', 404);
        }

        if ($currentUser === $userToFollow) {
            return $this->errorResponse('Cannot follow yourself', 400);
        }

        if ($userToFollow->isBanned()) {
            return $this->errorResponse('Cannot follow a banned user', 400);
        }

        try {
            $currentUser->addFollowing($userToFollow);
            $userToFollow->addFollower($currentUser);
            $this->entityManager->flush();

            return $this->successResponse('User followed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to follow user: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/{id}/unfollow', name: 'user_unfollow', methods: ['POST'])]
    public function unfollowUser(int $id): JsonResponse
    {
        $authError = $this->checkAuthentication($this->security);
        if ($authError) {
            return $authError;
        }

        $currentUser = $this->security->getUser();
        $userToUnfollow = $this->userRepository->find($id);
        
        if (!$userToUnfollow) {
            return $this->errorResponse('User not found', 404);
        }

        try {
            $currentUser->removeFollowing($userToUnfollow);
            $userToUnfollow->removeFollower($currentUser);
            $this->entityManager->flush();

            return $this->successResponse('User unfollowed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to unfollow user: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/{id}/followers', name: 'user_followers', methods: ['GET'])]
    public function getUserFollowers(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(50, (int) $request->query->get('limit', 20)));

            $followers = $user->getFollowers();
            $total = $followers->count();
            $totalPages = ceil($total / $limit);
            $offset = ($page - 1) * $limit;

            $paginatedFollowers = $followers->slice($offset, $limit);
            $data = $this->serializer->serialize($paginatedFollowers, 'json', ['groups' => ['user_public_profile']]);

            return new JsonResponse([
                'success' => true,
                'data' => json_decode($data, true),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve followers: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/{id}/following', name: 'user_following', methods: ['GET'])]
    public function getUserFollowing(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(50, (int) $request->query->get('limit', 20)));

            $following = $user->getFollowing();
            $total = $following->count();
            $totalPages = ceil($total / $limit);
            $offset = ($page - 1) * $limit;

            $paginatedFollowing = $following->slice($offset, $limit);
            $data = $this->serializer->serialize($paginatedFollowing, 'json', ['groups' => ['user_public_profile']]);

            return new JsonResponse([
                'success' => true,
                'data' => json_decode($data, true),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve following: ' . $e->getMessage(), 500);
        }
    }
}
