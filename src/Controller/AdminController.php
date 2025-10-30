<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use App\Controller\AbstractApiController;

#[Route('/api/admin')]
class AdminController extends AbstractApiController
{
    private UserRepository $userRepository;
    
    public function __construct(
        UserRepository $userRepository,
        \App\Service\EntityService $entityService,
        Security $security,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ) {
        parent::__construct($entityService, $entityManager, $security, $serializer);
        $this->userRepository = $userRepository;
    }
    
    #[Route('/users', name: 'admin_list_users', methods: ['GET'])]
    public function listUsers(Request $request): JsonResponse {
        $currentUser = $this->security->getUser();
        
        if (!$currentUser || 
            (!in_array('ROLE_ADMIN', $currentUser->getRoles(), true) && 
             !in_array('ROLE_MODERATOR', $currentUser->getRoles(), true))) {
            return $this->errorResponse('Access denied. Admin or moderator role required.', 403);
        }

        // Get pagination parameters from request
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20))); // Default 20, max 100
        $offset = ($page - 1) * $limit;

        // Get total count of users
        $totalUsers = $this->userRepository->count([]);
        $totalPages = ceil($totalUsers / $limit);

        // Get paginated users
        $users = $this->userRepository->findBy([], ['id' => 'ASC'], $limit, $offset);
        
        // Serialize users
        $json = $this->serializer->serialize($users, 'json', ['groups' => ['user_list']]);
        $data = json_decode($json, true);

        // Return paginated response
        return $this->paginatedResponse($data, $totalUsers, $page, $limit);
    }

    #[Route('/users/{id}', name: 'admin_get_user', methods: ['GET'])]
    public function getUserById(int $id): JsonResponse {
        $currentUser = $this->security->getUser();
        
        if (!$currentUser || 
            (!in_array('ROLE_ADMIN', $currentUser->getRoles(), true) && 
             !in_array('ROLE_MODERATOR', $currentUser->getRoles(), true))) {
            return $this->errorResponse('Access denied. Admin or moderator role required.', 403);
        }

        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        return $this->jsonResponse($user, $this->serializer, ['user_detail']);
    }

    #[Route('/users/{id}/promote', name: 'admin_promote_user', methods: ['POST'])]
    public function promoteUser(int $id): JsonResponse {
        $currentUser = $this->security->getUser();
        
        if (!$currentUser || !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return $this->errorResponse('Access denied. Admin role required.', 403);
        }

        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->errorResponse('User is already an admin', 400);
        }

        $roles = $user->getRoles();
        $roles[] = 'ROLE_ADMIN';
        $user->setRoles(array_unique($roles));

        $this->entityManager->flush();

        return $this->successResponse('User promoted to admin successfully');
    }

    #[Route('/users/{id}/demote', name: 'admin_demote_user', methods: ['POST'])]
    public function demoteUser(int $id): JsonResponse {
        $currentUser = $this->security->getUser();
        
        if (!$currentUser || !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return $this->errorResponse('Access denied. Admin role required.', 403);
        }

        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->errorResponse('User is not an admin', 400);
        }

        $roles = array_diff($user->getRoles(), ['ROLE_ADMIN']);
        $user->setRoles(array_values($roles));

        $this->entityManager->flush();

        return $this->successResponse('User demoted from admin successfully');
    }

    #[Route('/users/{id}/ban', name: 'admin_ban_user', methods: ['POST'])]
    public function banUser(int $id): JsonResponse {
        $currentUser = $this->security->getUser();
        
        if (!$currentUser || 
            (!in_array('ROLE_ADMIN', $currentUser->getRoles(), true) && 
             !in_array('ROLE_MODERATOR', $currentUser->getRoles(), true))) {
            return $this->errorResponse('Access denied. Admin or moderator role required.', 403);
        }

        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        if ($user->isBanned()) {
            return $this->errorResponse('User is already banned', 400);
        }

        $user->setBanned(true);
        $this->entityManager->flush();

        return $this->successResponse('User banned successfully');
    }

    #[Route('/users/{id}/unban', name: 'admin_unban_user', methods: ['POST'])]
    public function unbanUser(int $id): JsonResponse {
        $currentUser = $this->security->getUser();
        
        if (!$currentUser || 
            (!in_array('ROLE_ADMIN', $currentUser->getRoles(), true) && 
             !in_array('ROLE_MODERATOR', $currentUser->getRoles(), true))) {
            return $this->errorResponse('Access denied. Admin or moderator role required.', 403);
        }

        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        if (!$user->isBanned()) {
            return $this->errorResponse('User is not banned', 400);
        }

        $user->setBanned(false);
        $this->entityManager->flush();

        return $this->successResponse('User unbanned successfully');
    }

    #[Route('/users/{id}', name: 'admin_delete_user', methods: ['DELETE'])]
    public function deleteUser(int $id): JsonResponse {
        $currentUser = $this->security->getUser();
        
        if (!$currentUser || !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return $this->errorResponse('Access denied. Admin role required.', 403);
        }

        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->successResponse('User deleted successfully');
    }

    #[Route('/users/{id}', name: 'admin_update_user', methods: ['PUT'])]
    public function updateUser(int $id, Request $request): JsonResponse {
        $currentUser = $this->security->getUser();
        
        if (!$currentUser || !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return $this->errorResponse('Access denied. Admin role required.', 403);
        }

        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }

        $this->entityManager->flush();

        return $this->successResponse('User updated successfully');
    }
} 