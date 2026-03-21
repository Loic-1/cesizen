<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Uid\Uuid;

class JwtAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $header = $request->headers->get('Authorization');

        return is_string($header) && str_starts_with($header, 'Bearer ');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $header = $request->headers->get('Authorization');
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }

        $token = substr($header, 7);
        $payload = $this->jwtTokenManager->parse($token);
        $subject = (string) $payload['sub'];

        return new SelfValidatingPassport(
            new UserBadge($subject, function (string $identifier) {
                $user = $this->userRepository->find(Uuid::fromString($identifier));

                if ($user === null) {
                    throw new CustomUserMessageAuthenticationException('User not found.');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'message' => 'Authentication required.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
