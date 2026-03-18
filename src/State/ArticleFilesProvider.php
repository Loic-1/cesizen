<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ArticleRepository;
use App\Repository\FileRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<array>
 */
class ArticleFilesProvider implements ProviderInterface
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly FileRepository $fileRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $articleId = Uuid::fromString((string) ($uriVariables['articleId'] ?? ''));
        $article = $this->articleRepository->find($articleId);

        if ($article === null) {
            throw new NotFoundHttpException('Article not found.');
        }

        return $this->fileRepository->findByArticleId($articleId);
    }
}
