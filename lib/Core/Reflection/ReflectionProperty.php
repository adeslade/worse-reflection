<?php

namespace Phpactor\WorseReflection\Core\Reflection;

use Phpactor\WorseReflection\Core\Position;
use Phpactor\WorseReflection\Core\Visibility;
use Phpactor\WorseReflection\Core\DocBlock\DocBlock;
use Phpactor\WorseReflection\Core\Types;

interface ReflectionProperty extends ReflectionNode
{
    public function position(): Position;

    public function declaringClass(): ReflectionClassLike;

    public function class(): ReflectionClassLike;

    public function name(): string;

    public function visibility(): Visibility;

    public function inferredTypes(): Types;

    public function isStatic(): bool;

    public function docblock(): DocBlock;
}
