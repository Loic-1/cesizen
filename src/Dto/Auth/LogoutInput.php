<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class LogoutInput
{
    #[Assert\NotBlank]
    public ?string $refreshToken = null;
}
