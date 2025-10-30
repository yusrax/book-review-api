<?php

namespace App\Trait;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

trait AuthenticationTrait
{
    /**
     * Check if the current user is authenticated
     * 
     * @param Security $security
     * @return JsonResponse|null Returns null if authenticated, JsonResponse with error if not
     */
    protected function checkAuthentication(Security $security): ?JsonResponse
    {
        $user = $security->getUser();
        
        if (!$user) {
            return new JsonResponse(['message' => 'Unauthorized'], 401);
        }
        
        return null;
    }
    
    /**
     * Check if the current user has admin role
     * 
     * @param Security $security
     * @return JsonResponse|null Returns null if admin, JsonResponse with error if not
     */
    protected function checkAdminRole(Security $security): ?JsonResponse
    {
        $user = $security->getUser();
        
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(['message' => 'Access denied'], 403);
        }
        
        return null;
    }
    
    /**
     * Check if the current user has moderator role
     * 
     * @param Security $security
     * @return JsonResponse|null Returns null if moderator, JsonResponse with error if not
     */
    protected function checkModeratorRole(Security $security): ?JsonResponse
    {
        $user = $security->getUser();
        
        if (!$user || 
            (!in_array('ROLE_ADMIN', $user->getRoles(), true) && 
             !in_array('ROLE_MODERATOR', $user->getRoles(), true))) {
            return new JsonResponse(['message' => 'Access denied'], 403);
        }
        
        return null;
    }
    
    /**
     * Check if the current user is the owner of a resource
     * 
     * @param User $resourceOwner
     * @param Security $security
     * @return JsonResponse|null Returns null if owner, JsonResponse with error if not
     */
    protected function checkResourceOwnership(User $resourceOwner, Security $security): ?JsonResponse
    {
        $currentUser = $security->getUser();
        
        if (!$currentUser || $resourceOwner->getId() !== $currentUser->getId()) {
            return new JsonResponse(['message' => 'You are not allowed to perform this action'], 403);
        }
        
        return null;
    }
} 