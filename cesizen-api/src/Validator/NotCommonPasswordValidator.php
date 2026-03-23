<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NotCommonPasswordValidator extends ConstraintValidator
{
    /**
     * This lightweight blacklist covers the most common weak passwords seen in
     * French and English-speaking contexts and avoids adding an external dependency.
     */
    private const COMMON_PASSWORDS = [
        '12345678',
        '123456789',
        '1234567890',
        'admin',
        'admin123',
        'azerty',
        'azerty123',
        'azertyuiop',
        'bonjour123',
        'changeme',
        'football123',
        'iloveyou123',
        'letmein123',
        'motdepasse',
        'motdepasse123',
        'motdepasse123!',
        'password',
        'password1',
        'password12',
        'password123',
        'password123!',
        'qwerty123',
        'qwertyuiop',
        'soleil123',
        'test1234',
        'welcome123',
        'john'
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotCommonPassword) {
            throw new UnexpectedTypeException($constraint, NotCommonPassword::class);
        }

        if (!is_string($value) || $value === '') {
            return;
        }

        $normalizedValue = mb_strtolower(trim($value));

        if (!in_array($normalizedValue, self::COMMON_PASSWORDS, true)) {
            return;
        }

        $this->context
            ->buildViolation($constraint->message)
            ->addViolation();
    }
}
