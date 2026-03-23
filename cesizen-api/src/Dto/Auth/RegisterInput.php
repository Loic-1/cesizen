<?php

namespace App\Dto\Auth;

use App\Validator\NotCommonPassword;
use Symfony\Component\Validator\Constraints as Assert;

class RegisterInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 12, max: 255)]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).+$/',
        message: 'Password must contain at least one lowercase letter, one uppercase letter, and one special character.'
    )]
    #[NotCommonPassword]
    public ?string $password = null;
}
