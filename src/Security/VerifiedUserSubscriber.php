<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class VerifiedUserSubscriber implements EventSubscriberInterface
{
    private const EXEMPT_ROUTES = [
        'auth_logout',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function enforceVerifiedUser(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (is_string($route) && in_array($route, self::EXEMPT_ROUTES, true)) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User || $user->isVerified()) {
            return;
        }

        $event->setController(static fn (): JsonResponse => new JsonResponse([
            'message' => 'Please verify your email address before accessing this resource.',
        ], Response::HTTP_FORBIDDEN));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'enforceVerifiedUser',
        ];
    }
}
