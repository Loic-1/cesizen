<?php

namespace App\Dto\File;

use Symfony\Component\Validator\Constraints as Assert;

class FileInput
{
    #[Assert\Length(max: 255)]
    public ?string $originalName = null;

    #[Assert\Length(max: 255)]
    public ?string $storagePath = null;

    #[Assert\Length(max: 100)]
    public ?string $mimeType = null;

    #[Assert\PositiveOrZero]
    public ?int $size = null;
}
