<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestPayloadResolver
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    public function resolve(Request $request, string $className): object
    {
        $content = trim($request->getContent());
        $content = $content !== '' ? $content : '{}';

        try {
            $payload = $this->serializer->deserialize($content, $className, 'json');
        } catch (\Throwable $exception) {
            throw new BadRequestHttpException('Invalid JSON payload.', $exception);
        }

        $violations = $this->validator->validate($payload);
        if (count($violations) === 0) {
            return $payload;
        }

        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
        }

        throw new BadRequestHttpException(implode(' ', $errors));
    }
}
