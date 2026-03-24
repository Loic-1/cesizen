<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ArticleRepository;

class ArticleAuthorDetachService
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
    ) {
    }

    public function detachFromUser(User $user): void
    {
        foreach ($this->articleRepository->findBy(['user' => $user]) as $article) {
            $article->setUser(null);
        }
    }
}
