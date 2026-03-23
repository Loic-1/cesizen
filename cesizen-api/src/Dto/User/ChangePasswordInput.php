<?php

namespace App\Dto\User;

use App\Validator\NotCommonPassword;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordInput
{
    #[Assert\NotBlank]
    public ?string $currentPassword = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 12, max: 255)]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).+$/',
        message: 'Password must contain at least one lowercase letter, one uppercase letter, and one special character.'
    )]
    #[NotCommonPassword]
    public ?string $newPassword = null;
}
