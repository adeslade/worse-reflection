<?php

namespace Phpactor\WorseReflection\Core\Inference;

use Phpactor\WorseReflection\Core\Type;
use Phpactor\WorseReflection\Core\Types;
use Phpactor\WorseReflection\Core\Reflection\ReflectionScope;

final class SymbolContext
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * @var Types
     */
    private $types;

    /**
     * @var Symbol
     */
    private $symbol;

    /**
     * @var Type
     */
    private $containerType;

    /**
     * @var string[]
     */
    private $issues = [];

    /**
     * @var ReflectionScope
     */
    private $scope;

    private function __construct(Symbol $symbol, Types $types, $value = null, Type $containerType = null, ReflectionScope $scope = null)
    {
        $this->value = $value;
        $this->symbol = $symbol;
        $this->containerType = $containerType;
        $this->types = $types;
        $this->containerType = $containerType;
        $this->scope = $scope;
    }

    public static function for(Symbol $symbol): SymbolContext
    {
        return new self($symbol, Types::fromTypes([ Type::unknown() ]));
    }

    /**
     * @deprecated
     */
    public static function fromTypeAndValue(Type $type, $value): SymbolContext
    {
        return new self(Symbol::unknown(), Types::fromTypes([ $type ]), $value);
    }

    /**
     * @deprecated Types are plural
     */
    public static function fromType(Type $type)
    {
        return new self(Symbol::unknown(), Types::fromTypes([ $type ]));
    }

    public static function none(): SymbolContext
    {
        return new self(Symbol::unknown(), Types::empty());
    }

    public function withValue($value): SymbolContext
    {
        $new = clone $this;
        $new->value = $value;

        return $new;
    }

    public function withContainerType(Type $containerType): SymbolContext
    {
        $new = clone $this;
        $new->containerType = $containerType;

        return $new;
    }

    /**
     * @deprecated Types are plural
     */
    public function withType(Type $type): SymbolContext
    {
        $new = clone $this;
        $new->types = Types::fromTypes([ $type ]);

        return $new;
    }

    public function withTypes(Types $types): SymbolContext
    {
        $new = clone $this;
        $new->types = $types;

        return $new;
    }

    public function withScope(ReflectionScope $scope)
    {
        $new = clone $this;
        $new->scope = $scope;

        return $new;
    }

    public function withIssue(string $message): SymbolContext
    {
        $new = clone $this;
        $new->issues[] = $message;

        return $new;
    }

    /**
     * @deprecated
     */
    public function type(): Type
    {
        foreach ($this->types() as $type) {
            return $type;
        }

        return Type::unknown();
    }

    public function types(): Types
    {
        return $this->types;
    }

    public function value()
    {
        return $this->value;
    }

    public function symbol(): Symbol
    {
        return $this->symbol;
    }

    public function hasContainerType(): bool
    {
        return null !== $this->containerType;
    }

    /**
     * @return Type
     */
    public function containerType()
    {
        return $this->containerType;
    }

    public function issues(): array
    {
        return $this->issues;
    }

    public function scope(): ReflectionScope
    {
        return $this->scope;
    }
}
