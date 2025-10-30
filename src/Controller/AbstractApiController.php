<?php

namespace App\Controller;

use App\Service\EntityService;
use App\Trait\AuthenticationTrait;
use App\Trait\ResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractApiController extends AbstractController
{
    use AuthenticationTrait;
    use ResponseTrait;
    
    protected EntityService $entityService;
    protected EntityManagerInterface $entityManager;
    protected Security $security;
    protected SerializerInterface $serializer;
    
    public function __construct(
        EntityService $entityService,
        EntityManagerInterface $entityManager,
        Security $security,
        SerializerInterface $serializer
    ) {
        $this->entityService = $entityService;
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->serializer = $serializer;
    }
} 