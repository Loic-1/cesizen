<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\File\FileInput;
use App\Entity\File;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<FileInput, File>
 */
class FileInputProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleRepository $articleRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): File
    {
        if (!$data instanceof FileInput) {
            throw new BadRequestHttpException('Invalid file payload.');
        }

        foreach (['originalName', 'storagePath', 'mimeType', 'size'] as $requiredField) {
            if ($data->{$requiredField} === null || $data->{$requiredField} === '') {
                throw new BadRequestHttpException(sprintf('Field "%s" is required.', $requiredField));
            }
        }

        $articleId = Uuid::fromString((string) ($uriVariables['articleId'] ?? ''));
        $article = $this->articleRepository->find($articleId);
        if ($article === null) {
            throw new NotFoundHttpException('Article not found.');
        }

        $file = (new File())
            ->setArticle($article)
            ->setOriginalName($data->originalName)
            ->setStoragePath($data->storagePath)
            ->setMimeType($data->mimeType)
            ->setSize((int) $data->size);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        return $file;
    }
}
