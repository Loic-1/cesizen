<?php

namespace App\Controller;

use App\Dto\Auth\LoginInput;
use App\Dto\Auth\RegisterInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\JwtTokenManager;
use App\Service\IssuedRefreshToken;
use App\Service\RefreshTokenCookieManager;
use App\Service\RefreshTokenManager;
use App\Service\RequestPayloadResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

class AuthController extends AbstractController
{
    /**
     * Constructeur de la classe avec promotion de propriétés pour les services nécessaires.
     * @param RequestPayloadResolver $payloadResolver
     * @param UserRepository $userRepository
     * @param UserPasswordHasherInterface $passwordHasher
     * @param JwtTokenManager $jwtTokenManager
     */
    public function __construct(
        private readonly RequestPayloadResolver $payloadResolver,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly RefreshTokenManager $refreshTokenManager,
        private readonly RefreshTokenCookieManager $refreshTokenCookieManager,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    /**
     * Enregistrement d'un nouvel utilisateur et renvoi des tokens d'authentification.
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        /** @var RegisterInput $input */
        $input = $this->payloadResolver->resolve($request, RegisterInput::class);

        if ($this->userRepository->findOneByEmail($input->email ?? '') !== null)
        {
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

    /**
     * Connexion d'un utilisateur existant et renvoi des tokens d'authentification.
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        /** @var LoginInput $input */
        $input = $this->payloadResolver->resolve($request, LoginInput::class);

        $user = $this->userRepository->findOneByEmail((string) $input->email);
        if ($user === null || !$this->passwordHasher->isPasswordValid($user, (string) $input->password))
        {
            return $this->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->buildAuthResponse($user);
    }

    /**
     * Renouvellement du token d'authentification + rotation du token de rafraîchissement. Nécessite le cookie de rafraîchissement.
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/auth/refresh-token', name: 'auth_refresh', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        $plainRefreshToken = $request->cookies->get($this->refreshTokenCookieManager->getCookieName());
        if (!is_string($plainRefreshToken) || $plainRefreshToken === '')
        {
            return $this->json(['message' => 'Missing refresh token cookie.'], Response::HTTP_UNAUTHORIZED);
        }

        try
        {
            $refreshToken = $this->refreshTokenManager->getValidToken($plainRefreshToken);
            $user = $refreshToken->getUser();
            $newRefreshToken = $this->refreshTokenManager->rotate($plainRefreshToken);
        }
        catch (UnauthorizedHttpException $exception)
        {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        return $this->withRefreshCookie($this->json([
            'accessToken' => $this->jwtTokenManager->create($user),
            'user' => $user,
        ], Response::HTTP_OK, [], ['groups' => ['user:read']]), $newRefreshToken);
    }

    /**
     * Déconnexion d'un utilisateur et révocation du token de rafraîchissement + suppression du cookie.
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/auth/logout', name: 'auth_logout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function logout(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User)
        {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $this->refreshTokenManager->revokeAllForUser($user);

        $response = $this->json(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->refreshTokenCookieManager->clearCookie());

        return $response;
    }

    /**
     * Déconnexion d'un utilisateur par un administrateur et révocation de tous ses tokens de rafraîchissement.
     * @param string $id
     * @return JsonResponse
     */
    #[Route('/admin/users/{id}/logout', name: 'admin_user_logout', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function logoutUser(string $id): JsonResponse
    {
        $user = $this->userRepository->find(Uuid::fromString($id));
        if ($user === null)
        {
            return $this->json(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $revokedCount = $this->refreshTokenManager->revokeAllForUser($user);

        return $this->json([
            'message' => 'User sessions revoked.',
            'revokedSessions' => $revokedCount,
        ]);
    }

    /**
     * Construit la réponse d'authentification avec les tokens et les informations de l'utilisateur et ajoute le cookie de rafraîchissement.
     * @param User $user
     * @param int $status
     * @return JsonResponse
     */
    private function buildAuthResponse(User $user, int $status = Response::HTTP_OK): JsonResponse
    {
        $refreshToken = $this->refreshTokenManager->create($user);

        return $this->withRefreshCookie($this->json([
            'accessToken' => $this->jwtTokenManager->create($user),
            'user' => $user,
        ], $status, [], ['groups' => ['user:read']]), $refreshToken);
    }

    private function withRefreshCookie(JsonResponse $response, IssuedRefreshToken $refreshToken): JsonResponse
    {
        $response->headers->setCookie(
            $this->refreshTokenCookieManager->createCookie($refreshToken->plainToken, $refreshToken->expiresAt)
        );

        return $response;
    }
}
