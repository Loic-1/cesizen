<?php

namespace App\Tests\Unit;

use App\Dto\Auth\RegisterInput;
use App\Service\RequestPayloadResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestPayloadResolverTest extends TestCase
{
    public function testResolveUsesEmptyJsonObjectWhenRequestBodyIsBlank(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new RequestPayloadResolver($serializer, $validator);
        $payload = new RegisterInput();

        $serializer
            ->expects(self::once())
            ->method('deserialize')
            ->with('{}', RegisterInput::class, 'json')
            ->willReturn($payload);

        $validator
            ->expects(self::once())
            ->method('validate')
            ->with($payload)
            ->willReturn(new ConstraintViolationList());

        self::assertSame($payload, $resolver->resolve(new Request(content: '   '), RegisterInput::class));
    }

    public function testResolveWrapsDeserializerErrorsAsBadRequest(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new RequestPayloadResolver($serializer, $validator);

        $serializer
            ->expects(self::once())
            ->method('deserialize')
            ->willThrowException(new \RuntimeException('Broken payload'));

        $validator->expects(self::never())->method('validate');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid JSON payload.');

        $resolver->resolve(new Request(content: '{invalid'), RegisterInput::class);
    }

    public function testResolveAggregatesValidationErrorsIntoSingleMessage(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new RequestPayloadResolver($serializer, $validator);
        $payload = new RegisterInput();

        $serializer
            ->expects(self::once())
            ->method('deserialize')
            ->willReturn($payload);

        $validator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList([
                new ConstraintViolation('This value should not be blank.', '', [], null, 'email', null),
                new ConstraintViolation('This value is too short.', '', [], null, 'password', null),
            ]));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            'email: This value should not be blank. password: This value is too short.'
        );

        $resolver->resolve(new Request(content: '{}'), RegisterInput::class);
    }
}
