<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class LoginInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\NotBlank]
    public ?string $password = null;
}
