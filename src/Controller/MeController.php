<?php

namespace App\Controller;

use App\Dto\User\ChangePasswordInput;
use App\Dto\User\UpdateMeInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\RequestPayloadResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MeController extends AbstractController
{
    /**
     * Constructeur de la classe avec promotion de propriétés pour les services nécessaires.
     * @param RequestPayloadResolver $payloadResolver
     * @param UserRepository $userRepository
     * @param UserPasswordHasherInterface $passwordHasher
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private readonly RequestPayloadResolver $payloadResolver,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    /**
     * Affiche les informations de l'utilisateur connecté.
     * @param User|null $user
     * @return JsonResponse
     */
    #[Route('/users/me', name: 'users_me_get', methods: ['GET'])]
    public function show(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null)
        {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($user, Response::HTTP_OK, [], ['groups' => ['user:read']]);
    }

    /**
     * Met à jour les informations de l'utilisateur connecté.
     * @param Request $request
     * @param User|null $user
     * @return JsonResponse
     */
    #[Route('/users/me', name: 'users_me_patch', methods: ['PATCH'])]
    public function update(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null)
        {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var UpdateMeInput $input */
        $input = $this->payloadResolver->resolve($request, UpdateMeInput::class);

        if ($input->email !== null && $input->email !== $user->getEmail())
        {
            $existing = $this->userRepository->findOneByEmail($input->email);
            if ($existing !== null && $existing->getId() != $user->getId())
            {
                return $this->json(['message' => 'Email already in use.'], Response::HTTP_CONFLICT);
            }

            $user->setEmail($input->email);
        }

        $this->entityManager->flush();

        return $this->json($user, Response::HTTP_OK, [], ['groups' => ['user:read']]);
    }

    /**
     * Modifie le mot de passe de l'utilisateur connecté.
     * @param Request $request
     * @param User|null $user
     * @return JsonResponse
     */
    #[Route('/users/me/password', name: 'users_me_password', methods: ['PATCH'])]
    public function changePassword(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null)
        {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var ChangePasswordInput $input */
        $input = $this->payloadResolver->resolve($request, ChangePasswordInput::class);

        if (!$this->passwordHasher->isPasswordValid($user, (string) $input->currentPassword))
        {
            return $this->json(['message' => 'Current password is invalid.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $input->newPassword));
        $this->entityManager->flush();

        return $this->json(['message' => 'Password updated.']);
    }

    /**
     * Supprime le compte de l'utilisateur connecté.
     * @param User|null $user
     * @return JsonResponse
     */
    #[Route('/users/me', name: 'users_me_delete', methods: ['DELETE'])]
    public function delete(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null)
        {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
