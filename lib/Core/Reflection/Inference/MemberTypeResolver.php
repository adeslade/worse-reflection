<?php

namespace Phpactor\WorseReflection\Core\Reflection\Inference;

use Phpactor\WorseReflection\Core\Reflector;
use Phpactor\WorseReflection\Core\Logger;
use Phpactor\WorseReflection\Core\Type;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Exception\SourceNotFound;
use Phpactor\WorseReflection\Core\Exception\ClassNotFound;
use Phpactor\WorseReflection\Core\Reflection\ReflectionClass;
use Phpactor\WorseReflection\Core\Exception\NotFound;

class MemberTypeResolver
{
    /**
     * @var Reflector
     */
    private $reflector;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Reflector $reflector, Logger $logger)
    {
        $this->reflector = $reflector;
        $this->logger = $logger;
    }

    public function methodType(Type $ownerType, string $name): Type
    {
        $class = $this->reflectClassOrNull($ownerType, $name);

        if (null === $class) {
            return Type::unknown();
        }

        try {
            if (false === $class->methods()->has($name)) {
                $this->logger->warning(sprintf(
                    'Class "%s" has no method named "%s"',
                    (string) $ownerType, $name
                ));
                return Type::undefined();
            }
        } catch (NotFound $e) {
            $this->logger->warning($e->getMessage());
            return Type::undefined();
        }

        return $class->methods()->get($name)->inferredReturnType();
    }

    public function constantType(Type $ownerType, string $name): Type
    {
        $class = $this->reflectClassOrNull($ownerType, $name);

        if (null === $class) {
            return Type::unknown();
        }

        try {
            if (false === $class->constants()->has($name)) {
                $this->logger->warning(sprintf(
                    'Class "%s" has no constant named "%s"',
                    (string) $ownerType, $name
                ));
                return Type::undefined();
            }
        } catch (NotFound $e) {
            $this->logger->warning($e->getMessage());
            return Type::undefined();
        }

        return $class->constants()->get($name)->type();
    }

    public function propertyType(Type $ownerType, string $name): Type
    {
        $class = $this->reflectClassOrNull($ownerType, $name);

        if (null === $class) {
            return Type::unknown();
        }

        // interfaces do not have properties...
        if (false === $class instanceof ReflectionClass) {
            return Type::unknown();
        }

        try {
            if (false === $class->properties()->has($name)) {
                $this->logger->warning(sprintf(
                    'Class "%s" has no property named "%s"',
                    (string) $ownerType, $name
                ));
                return Type::undefined();
            }
        } catch (NotFound $e) {
            $this->logger->warning($e->getMessage());
            return Type::undefined();
        }

        return $class->properties()->get($name)->type();
    }

    private function reflectClassOrNull(Type $ownerType, string $name)
    {
        try {
            return $this->reflector->reflectClass(ClassName::fromString((string) $ownerType));
        } catch (NotFound $e) {
            $this->logger->warning(sprintf(
                'Unable to locate class "%s" for method "%s"', (string) $ownerType, $name
            ));
        }
    }
}