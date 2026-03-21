<?php

namespace App\Dto\Article;

use Symfony\Component\Validator\Constraints as Assert;

class ArticleInput
{
    #[Assert\Uuid]
    public ?string $userId = null;

    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Assert\Length(max: 255)]
    public ?string $description = null;

    public ?string $content = null;
}
