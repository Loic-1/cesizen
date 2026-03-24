<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\ArticleAuthorDetachService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<User, void>
 */
class UserDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleAuthorDetachService $articleAuthorDetachService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof User) {
            throw new BadRequestHttpException('Invalid user payload.');
        }

        $this->articleAuthorDetachService->detachFromUser($data);
        $this->entityManager->remove($data);
        $this->entityManager->flush();
    }
}
