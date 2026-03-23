<?php

namespace App\Controller;

use App\Dto\Auth\LoginInput;
use App\Dto\Auth\RegisterInput;
use App\Dto\Auth\ResendVerificationEmailInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerificationManager;
use App\Security\JwtTokenManager;
use App\Service\IssuedRefreshToken;
use App\Service\RefreshTokenCookieManager;
use App\Service\RefreshTokenManager;
use App\Service\RequestPayloadResolver;
use App\Service\VerificationEmailSender;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

class AuthController extends AbstractController
{
    private const LOGIN_ATTEMPT_LIMIT = 5;
    private const LOGIN_ATTEMPT_WINDOW_IN_SECONDS = 900;

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
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $loginAttemptCache,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly RefreshTokenManager $refreshTokenManager,
        private readonly RefreshTokenCookieManager $refreshTokenCookieManager,
        private readonly EmailVerificationManager $emailVerificationManager,
        private readonly VerificationEmailSender $verificationEmailSender,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $frontendUrl,
    )
    {
    }

    /**
     * Enregistrement d'un nouvel utilisateur puis envoi d'un e-mail de validation.
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
            // Au moins le rôle d'utilisateur
            ->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $input->password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->sendVerificationEmail($user);

        return $this->json([
            'message' => 'Registration successful. Please verify your email address.',
            'user' => $user,
        ], Response::HTTP_CREATED, [], ['groups' => ['user:read']]);
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
        $attemptKey = $this->buildLoginAttemptCacheKey($request, (string) $input->email);
        $attemptData = $this->getLoginAttemptData($attemptKey);

        if ($attemptData['count'] >= self::LOGIN_ATTEMPT_LIMIT && $attemptData['resetAt'] > time())
        {
            $response = $this->json(
                ['message' => 'Too many login attempts. Please try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
            $response->headers->set('Retry-After', (string) max(1, $attemptData['resetAt'] - time()));

            return $response;
        }

        $user = $this->userRepository->findOneByEmail((string) $input->email);
        if ($user === null || !$this->passwordHasher->isPasswordValid($user, (string) $input->password))
        {
            $this->recordFailedLoginAttempt($attemptKey, $attemptData);

            return $this->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isVerified())
        {
            return $this->json([
                'message' => 'Please verify your email address before logging in.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->clearLoginAttempts($attemptKey);

        return $this->buildAuthResponse($user);
    }

    #[Route('/auth/verify-email', name: 'auth_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $token = (string) $request->query->get('token', '');
        if ($token === '') {
            return $this->json(['message' => 'Missing verification token.'], Response::HTTP_BAD_REQUEST);
        }

        try
        {
            $user = $this->emailVerificationManager->verify($token);
        }
        catch (BadRequestHttpException $exception)
        {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Email verified.',
            'user' => $user,
        ], Response::HTTP_OK, [], ['groups' => ['user:read']]);
    }

    #[Route('/auth/resend-verification-email', name: 'auth_resend_verification_email', methods: ['POST'])]
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        /** @var ResendVerificationEmailInput $input */
        $input = $this->payloadResolver->resolve($request, ResendVerificationEmailInput::class);

        $user = $this->userRepository->findOneByEmail((string) $input->email);
        if ($user !== null && !$user->isVerified()) {
            $this->sendVerificationEmail($user);
        }

        return $this->json([
            'message' => 'If the account exists and is not yet verified, a verification email has been sent.',
        ]);
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
        }
        catch (UnauthorizedHttpException $exception)
        {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isVerified())
        {
            $this->refreshTokenManager->revokeAllForUser($user);

            $response = $this->json([
                'message' => 'Please verify your email address before accessing this resource.',
            ], Response::HTTP_FORBIDDEN);
            $response->headers->setCookie($this->refreshTokenCookieManager->clearCookie());

            return $response;
        }

        $newRefreshToken = $this->refreshTokenManager->rotate($plainRefreshToken);

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

    private function sendVerificationEmail(User $user): void
    {
        $verificationToken = $this->emailVerificationManager->create($user);
        $verificationUrl = sprintf(
            '%s/verify-email?token=%s',
            rtrim($this->frontendUrl, '/'),
            urlencode($verificationToken->plainToken)
        );

        $this->verificationEmailSender->send($user, $verificationUrl);
    }

    private function buildLoginAttemptCacheKey(Request $request, string $email): string
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $clientIp = $request->getClientIp() ?? 'unknown';

        return 'login_attempts_'.hash('sha256', $normalizedEmail.'|'.$clientIp);
    }

    /**
     * @return array{count: int, resetAt: int}
     */
    private function getLoginAttemptData(string $attemptKey): array
    {
        $item = $this->loginAttemptCache->getItem($attemptKey);
        $value = $item->isHit() ? $item->get() : null;

        if (!is_array($value) || !isset($value['count'], $value['resetAt'])) {
            return [
                'count' => 0,
                'resetAt' => time() + self::LOGIN_ATTEMPT_WINDOW_IN_SECONDS,
            ];
        }

        return [
            'count' => (int) $value['count'],
            'resetAt' => (int) $value['resetAt'],
        ];
    }

    /**
     * @param array{count: int, resetAt: int} $attemptData
     */
    private function recordFailedLoginAttempt(string $attemptKey, array $attemptData): void
    {
        $now = time();
        if ($attemptData['resetAt'] <= $now) {
            $attemptData = [
                'count' => 0,
                'resetAt' => $now + self::LOGIN_ATTEMPT_WINDOW_IN_SECONDS,
            ];
        }

        $item = $this->loginAttemptCache->getItem($attemptKey);
        $item->set([
            'count' => $attemptData['count'] + 1,
            'resetAt' => $attemptData['resetAt'],
        ]);
        $item->expiresAfter(max(1, $attemptData['resetAt'] - $now));
        $this->loginAttemptCache->save($item);
    }

    private function clearLoginAttempts(string $attemptKey): void
    {
        $this->loginAttemptCache->deleteItem($attemptKey);
    }
}
