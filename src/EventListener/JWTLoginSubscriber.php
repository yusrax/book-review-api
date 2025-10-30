<?php

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class JWTLoginSubscriber
{
    #[AsEventListener]
    public function onJWTAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $user = $event->getToken()->getUser();

        if ($user instanceof User && $user->isBanned()) {
            throw new AccessDeniedHttpException('Your account has been banned.');
        }
    }
}
