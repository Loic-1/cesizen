<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Article\ArticleInput;
use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use App\Service\ArticleContentSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ArticleInput, Article>
 */
class ArticleInputProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleRepository $articleRepository,
        private readonly UserRepository $userRepository,
        private readonly ArticleContentSanitizer $articleContentSanitizer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Article
    {
        if (!$data instanceof ArticleInput) {
            throw new BadRequestHttpException('Invalid article payload.');
        }

        $article = $context['previous_data'] ?? null;

        if (!$article instanceof Article) {
            if ($operation->getMethod() === 'POST') {
                $article = new Article();
            } else {
                $articleId = Uuid::fromString((string) ($uriVariables['id'] ?? ''));
                $article = $this->articleRepository->find($articleId);

                if (!$article instanceof Article) {
                    throw new NotFoundHttpException('Article not found.');
                }
            }
        }

        if ($operation->getMethod() === 'POST') {
            foreach (['userId', 'title', 'content'] as $requiredField) {
                if ($data->{$requiredField} === null || $data->{$requiredField} === '') {
                    throw new BadRequestHttpException(sprintf('Field "%s" is required.', $requiredField));
                }
            }
        }

        if ($data->userId !== null) {
            $user = $this->userRepository->find(Uuid::fromString($data->userId));
            if ($user === null) {
                throw new NotFoundHttpException('User not found.');
            }

            $article->setUser($user);
        }

        if ($data->title !== null) {
            $article->setTitle($data->title);
        }

        if ($data->description !== null || $operation->getMethod() === 'POST') {
            $article->setDescription($data->description);
        }

        if ($data->content !== null) {
            $article->setContent($this->articleContentSanitizer->sanitize($data->content));
        }

        if ($operation->getMethod() === 'POST') {
            $this->entityManager->persist($article);
        }
        $this->entityManager->flush();

        return $article;
    }
}
