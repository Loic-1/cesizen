<?php

namespace App\Controller;

use App\Entity\File;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;

class AdminArticleImageUploadController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/avif',
    ];

    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'svg',
        'avif',
    ];

    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/admin/articles/{id}/images', name: 'admin_article_upload_images', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $article = $this->articleRepository->find(Uuid::fromString($id));
        if ($article === null) {
            return $this->json(['message' => 'Article not found.'], Response::HTTP_NOT_FOUND);
        }

        $uploadedFiles = $this->extractUploadedImages($request);
        if ($uploadedFiles === []) {
            return $this->json(['message' => 'At least one image is required.'], Response::HTTP_BAD_REQUEST);
        }

        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/articles';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0777, true) && !is_dir($uploadDirectory)) {
            throw new \RuntimeException('Unable to create upload directory.');
        }

        $slugger = new AsciiSlugger();
        $savedFiles = [];

        foreach ($uploadedFiles as $uploadedFile) {
            $originalName = $uploadedFile->getClientOriginalName() ?: 'image';
            $mimeType = strtolower((string) ($uploadedFile->getClientMimeType() ?? ''));
            $clientExtension = strtolower((string) $uploadedFile->getClientOriginalExtension());
            $size = (int) ($uploadedFile->getSize() ?? 0);

            if (
                !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)
                || !in_array($clientExtension, self::ALLOWED_EXTENSIONS, true)
            ) {
                throw new BadRequestHttpException('Only image files are allowed.');
            }

            $safeBaseName = $slugger->slug(pathinfo($originalName, PATHINFO_FILENAME))->lower()->toString();
            $filename = sprintf(
                '%s-%s.%s',
                $safeBaseName !== '' ? $safeBaseName : 'image',
                bin2hex(random_bytes(8)),
                $clientExtension
            );

            $uploadedFile->move($uploadDirectory, $filename);

            $file = (new File())
                ->setArticle($article)
                ->setOriginalName($originalName)
                ->setStoragePath('/uploads/articles/' . $filename)
                ->setMimeType($mimeType)
                ->setSize($size);

            $this->entityManager->persist($file);
            $savedFiles[] = $file;
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Images uploaded successfully.',
            'files' => $savedFiles,
        ], Response::HTTP_CREATED, [], ['groups' => ['file:read']]);
    }

    /**
     * @return UploadedFile[]
     */
    private function extractUploadedImages(Request $request): array
    {
        $images = $request->files->all('images');
        if ($images === []) {
            $singleImage = $request->files->get('image');

            return $singleImage instanceof UploadedFile ? [$singleImage] : [];
        }

        return array_values(array_filter($images, static fn (mixed $file): bool => $file instanceof UploadedFile));
    }
}
