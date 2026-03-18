<?php

namespace App\Controller;

use App\Dto\Auth\LoginInput;
use App\Dto\Auth\LogoutInput;
use App\Dto\Auth\RefreshTokenInput;
use App\Dto\Auth\RegisterInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\JwtTokenManager;
use App\Service\RefreshTokenManager;
use App\Service\RequestPayloadResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly RequestPayloadResolver $payloadResolver,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly RefreshTokenManager $refreshTokenManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        /** @var RegisterInput $input */
        $input = $this->payloadResolver->resolve($request, RegisterInput::class);

        if ($this->userRepository->findOneByEmail($input->email ?? '') !== null) {
            return $this->json(['message' => 'Email already in use.'], Response::HTTP_CONFLICT);
        }

        $user = (new User())
            ->setEmail((string) $input->email)
            ->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $input->password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->buildAuthResponse($user, Response::HTTP_CREATED);
    }

    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        /** @var LoginInput $input */
        $input = $this->payloadResolver->resolve($request, LoginInput::class);

        $user = $this->userRepository->findOneByEmail((string) $input->email);
        if ($user === null || !$this->passwordHasher->isPasswordValid($user, (string) $input->password)) {
            return $this->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->buildAuthResponse($user);
    }

    #[Route('/auth/refresh-token', name: 'auth_refresh', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function refreshToken(Request $request): JsonResponse
    {
        /** @var RefreshTokenInput $input */
        $input = $this->payloadResolver->resolve($request, RefreshTokenInput::class);
        $refreshToken = $this->refreshTokenManager->getValidToken((string) $input->refreshToken);
        $user = $refreshToken->getUser();
        $newRefreshToken = $this->refreshTokenManager->rotate((string) $input->refreshToken);

        return $this->json([
            'accessToken' => $this->jwtTokenManager->create($user),
            'refreshToken' => $newRefreshToken,
            'user' => $user,
        ], Response::HTTP_OK, [], ['groups' => ['user:read']]);
    }

    #[Route('/auth/logout', name: 'auth_logout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function logout(Request $request): JsonResponse
    {
        /** @var LogoutInput $input */
        $input = $this->payloadResolver->resolve($request, LogoutInput::class);
        $this->refreshTokenManager->revoke((string) $input->refreshToken);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/admin/users/{id}/logout', name: 'admin_user_logout', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function logoutUser(string $id): JsonResponse
    {
        $user = $this->userRepository->find(Uuid::fromString($id));
        if ($user === null) {
            return $this->json(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $revokedCount = $this->refreshTokenManager->revokeAllForUser($user);

        return $this->json([
            'message' => 'User sessions revoked.',
            'revokedSessions' => $revokedCount,
        ]);
    }

    private function buildAuthResponse(User $user, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->json([
            'accessToken' => $this->jwtTokenManager->create($user),
            'refreshToken' => $this->refreshTokenManager->create($user),
            'user' => $user,
        ], $status, [], ['groups' => ['user:read']]);
    }
}
