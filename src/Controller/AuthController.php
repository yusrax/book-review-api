<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserRegisterType;
use App\Service\EntityService;
use App\Service\ImageUploadService;
use App\Trait\ResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Controller\AbstractApiController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\SerializerInterface;

class AuthController extends AbstractApiController
{
    use ResponseTrait;
    
    private FormFactoryInterface $formFactory;
    private UserPasswordHasherInterface $passwordHasher;
    private ImageUploadService $imageUploader;
    private JWTTokenManagerInterface $jwtManager;
    
    public function __construct(
        FormFactoryInterface $formFactory,
        UserPasswordHasherInterface $passwordHasher,
        ImageUploadService $imageUploader,
        JWTTokenManagerInterface $jwtManager,
        EntityService $entityService,
        EntityManagerInterface $entityManager,
        Security $security,
        SerializerInterface $serializer
    ) {
        parent::__construct($entityService, $entityManager, $security, $serializer);
        $this->formFactory = $formFactory;
        $this->passwordHasher = $passwordHasher;
        $this->imageUploader = $imageUploader;
        $this->jwtManager = $jwtManager;
    }
    
    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $user = new User();
            $form = $this->formFactory->create(UserRegisterType::class, $user);
            
            // Handle form data
            $form->submit([
                'name' => $request->request->get('name'),
                'email' => $request->request->get('email'),
                'password' => $request->request->get('password')
            ]);

            if (!$form->isValid()) {
                $errors = $this->entityService->getFormErrors($form);
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errors);
            }

            // Handle profile picture upload if provided
            if ($request->files->has('profilePicture') && $request->files->get('profilePicture') !== null) {
                try {
                    $profilePicture = $request->files->get('profilePicture');
                    $profilePicturePath = $this->imageUploader->uploadImage($profilePicture, 'profile_pictures');
                    $user->setProfilePicture($profilePicturePath);
                } catch (\Exception $e) {
                    return $this->errorResponse('Failed to upload profile picture: ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
                }
            }

            // Hash the password
            try {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $form->get('password')->getData());
                $user->setPassword($hashedPassword);
            } catch (\Exception $e) {
                return $this->errorResponse('Failed to hash password: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Set default role
            $user->setRoles(['ROLE_USER']);

            try {
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                return $this->errorResponse('Failed to save user: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Generate JWT token
            try {
                $token = $this->jwtManager->create($user);
            } catch (\Exception $e) {
                return $this->errorResponse('Failed to generate authentication token: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->successResponse('User registered successfully', [
                'token' => $token,
                'user' => json_decode($this->serializer->serialize($user, 'json', ['groups' => ['user_detail']]), true)
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            if (!$email || !$password) {
                return $this->errorResponse('Email and password are required', Response::HTTP_BAD_REQUEST);
            }

            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user) {
                return $this->errorResponse('Invalid credentials', Response::HTTP_UNAUTHORIZED);
            }

            if (!$this->passwordHasher->isPasswordValid($user, $password)) {
                return $this->errorResponse('Invalid credentials', Response::HTTP_UNAUTHORIZED);
            }

            if ($user->isBanned()) {
                return $this->errorResponse('Your account has been banned', Response::HTTP_FORBIDDEN);
            }

            try {
                $token = $this->jwtManager->create($user);
            } catch (\Exception $e) {
                return $this->errorResponse('Failed to generate authentication token: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->successResponse('Login successful', [
                'token' => $token,
                'user' => json_decode($this->serializer->serialize($user, 'json', ['groups' => ['user_detail']]), true)
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Login failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
