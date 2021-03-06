<?php

namespace Phpactor\WorseReflection\Tests\Integration\Bridge\TolerantParser\Reflection;

use Phpactor\WorseReflection\Tests\Integration\IntegrationTestCase;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Visibility;
use Phpactor\WorseReflection\Core\Type;
use Phpactor\WorseReflection\Core\Logger\ArrayLogger;
use Phpactor\WorseReflection\Core\Reflection\ReflectionMethod;
use Phpactor\WorseReflection\Core\Reflection\ReflectionClass;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionMethodCollection;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionParameterCollection;

class ReflectionMethodTest extends IntegrationTestCase
{
    /**
     * @dataProvider provideReflectionMethod
     */
    public function testReflectMethod(string $source, string $class, \Closure $assertion)
    {
        $class = $this->createReflector($source)->reflectClassLike(ClassName::fromString($class));
        $assertion($class->methods(), $this->logger());
    }

    public function provideReflectionMethod()
    {
        return [
            'It reflects a method' => [
                <<<'EOT'
<?php

class Foobar
{
    public function method();
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals('method', $methods->get('method')->name());
                    $this->assertInstanceOf(ReflectionMethod::class, $methods->get('method'));
                },
            ],
            'Private visibility' => [
                <<<'EOT'
<?php

class Foobar
{
    private function method();
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Visibility::private(), $methods->get('method')->visibility());
                },
            ],
            'Protected visibility' => [
                <<<'EOT'
<?php

class Foobar
{
    protected function method()
    {
    }
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Visibility::protected(), $methods->get('method')->visibility());
                },
            ],
            'Public visibility' => [
                <<<'EOT'
<?php

class Foobar
{
    public function method();
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Visibility::public(), $methods->get('method')->visibility());
                },
            ],
            'Return type' => [
                <<<'EOT'
<?php

use Acme\Post;

class Foobar
{
    function method1(): int {}
    function method2(): string {}
    function method3(): float {}
    function method4(): array {}
    function method5(): Barfoo {}
    function method6(): Post {}
    function method7(): self {}
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Type::int(), $methods->get('method1')->returnType());
                    $this->assertEquals(Type::string(), $methods->get('method2')->returnType());
                    $this->assertEquals(Type::float(), $methods->get('method3')->returnType());
                    $this->assertEquals(Type::array(), $methods->get('method4')->returnType());
                    $this->assertEquals(Type::class(ClassName::fromString('Barfoo')), $methods->get('method5')->returnType());
                    $this->assertEquals(Type::class(ClassName::fromString('Acme\Post')), $methods->get('method6')->returnType());
                    $this->assertEquals(Type::class(ClassName::fromString('Foobar')), $methods->get('method7')->returnType());
                },
            ],
            'Nullable return type' => [
                <<<'EOT'
<?php

use Acme\Post;

class Foobar
{
    function method1(): ?int {}
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Type::int(), $methods->get('method1')->returnType());
                },
            ],
            'Inherited methods' => [
                <<<'EOT'
<?php

class ParentParentClass extends NonExisting
{
    public function method5() {}
}

class ParentClass extends ParentParentClass
{
    private function method1() {}
    protected function method2() {}
    public function method3() {}
    public function method4() {}
}

class Foobar extends ParentClass
{
    public function method4() {} // overrides from previous
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(
                        ['method5', 'method2', 'method3', 'method4'],
                        $methods->keys()
                    );
                },
            ],
            'Return type from docblock' => [
                <<<'EOT'
<?php

use Acme\Post;

class Foobar
{
    /**
     * @return Post
     */
    function method1() {}
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Type::class(ClassName::fromString('Acme\Post')), $methods->get('method1')->inferredReturnTypes()->best());
                },
            ],
            'Return type from array docblock' => [
                <<<'EOT'
<?php

use Acme\Post;

class Foobar
{
    /**
     * @return Post[]
     */
    function method1(): array {}
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Type::array('Acme\Post'), $methods->get('method1')->inferredReturnTypes()->best());
                },
            ],
            'Return type from docblock this and static' => [
                <<<'EOT'
<?php

class Foobar
{
    /**
     * @return $this
     */
    function method1() {}

    /**
     * @return static
     */
    function method2() {}
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Type::class(ClassName::fromString('Foobar')), $methods->get('method1')->inferredReturnTypes()->best());
                    $this->assertEquals(Type::class(ClassName::fromString('Foobar')), $methods->get('method2')->inferredReturnTypes()->best());
                },
            ],
            'Return type from class @method annotation' => [
                <<<'EOT'
<?php

use Acme\Post;

/**
 * @method Post method1()
 */
class Foobar
{
    function method1() {}
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Type::class(ClassName::fromString('Acme\Post')), $methods->get('method1')->inferredReturnTypes()->best());
                },
            ],
            'Return type from overridden @method annotation' => [
                <<<'EOT'
<?php

use Acme\Post;

class Barfoo
{
    /**
     * @return AbstractPost
     */
    function method1() {}
}

/**
 * @method Post method1()
 */
class Foobar extends Barfoo
{
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Type::class(ClassName::fromString('Acme\Post')), $methods->get('method1')->inferredReturnTypes()->best());
                },
            ],
            'Return type from inherited docblock' => [
                <<<'EOT'
<?php

use Acme\Post;

class ParentClass
{
    /**
     * @return \Articles\Blog
     */
    function method1() {}
}

class Foobar extends ParentClass
{
    /**
     * {@inheritdoc}
     */
    function method1() {}
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Type::class(ClassName::fromString('\Articles\Blog')), $methods->get('method1')->inferredReturnTypes()->best());
                },
            ],
            'Return type from inherited docblock (from interface)' => [
                <<<'EOT'
<?php

use Acme\Post;

interface Barbar
{
    /**
     * @return \Articles\Blog
     */
    function method1();
}

class Foobar implements Barbar
{
    /**
     * {@inheritdoc}
     */
    function method1()
    {
    }
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(Type::class(ClassName::fromString('\Articles\Blog')), $methods->get('method1')->inferredReturnTypes()->best());
                },
            ],
            'It reflects an abstract method' => [
                <<<'EOT'
<?php

abstract class Foobar
{
    abstract public function method();
    public function methodNonAbstract();
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertTrue($methods->get('method')->isAbstract());
                    $this->assertFalse($methods->get('methodNonAbstract')->isAbstract());
                },
            ],
            'It returns the method parameters' => [
                <<<'EOT'
<?php

class Foobar
{
    public function barfoo($foobar, Barfoo $barfoo, int $number)
    {
    }
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertCount(3, $methods->get('barfoo')->parameters());
                },
            ],
            'It returns the nullable parameter types' => [
                <<<'EOT'
<?php

class Foobar
{
    public function barfoo(?Barfoo $barfoo)
    {
    }
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertCount(1, $methods->get('barfoo')->parameters());
                    $this->assertEquals('Barfoo', $methods->get('barfoo')->parameters()->first()->type());
                },
            ],
            'It tolerantes and logs method parameters with missing variables parameter' => [
                <<<'EOT'
<?php

class Foobar
{
    public function barfoo(Barfoo = null)
    {
    }
}
EOT
                ,
                'Foobar',
                function ($methods, ArrayLogger $logger) {
                    $this->assertEquals('', $methods->get('barfoo')->parameters()->first()->name());
                    $this->assertContains(
                        'Parameter has no variable',
                        $logger->messages()[0]
                    );
                },
            ],
            'It returns the raw docblock' => [
                <<<'EOT'
<?php

class Foobar
{
    /**
     * Hello this is a docblock.
     */
    public function barfoo($foobar, Barfoo $barfoo, int $number)
    {
    }
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertContains(<<<EOT
Hello this is a docblock.
EOT
                    , $methods->get('barfoo')->docblock()->raw());
                },
            ],
            'It returns the formatted docblock' => [
                <<<'EOT'
<?php

class Foobar
{
    /**
     * Hello this is a docblock.
     *
     * Yes?
     */
    public function barfoo($foobar, Barfoo $barfoo, int $number)
    {
    }
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals(<<<EOT
Hello this is a docblock.

Yes?
EOT
                    , $methods->get('barfoo')->docblock()->formatted());
                },
            ],
            'It returns true if the method is static' => [
                <<<'EOT'
<?php

class Foobar
{
    public static function barfoo($foobar, Barfoo $barfoo, int $number)
    {
    }
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertTrue($methods->get('barfoo')->isStatic());
                },
            ],
            'It returns the method body' => [
                <<<'EOT'
<?php

class Foobar
{
    public function barfoo()
    {
        echo "Hello!";
    }
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertEquals('echo "Hello!";', (string) $methods->get('barfoo')->body());
                },
            ],
            'It reflects a method from an inteface' => [
                <<<'EOT'
<?php

interface Foobar
{
    public function barfoo()
    {
        echo "Hello!";
    }
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertTrue($methods->has('barfoo'));
                    $this->assertEquals('Foobar', (string) $methods->get('barfoo')->declaringClass()->name());
                },
            ],
            'It reflects a method from a trait' => [
                <<<'EOT'
<?php

trait Foobar
{
    public function barfoo()
    {
        echo "Hello!";
    }
}
EOT
                ,
                'Foobar',
                function ($methods) {
                    $this->assertTrue($methods->has('barfoo'));
                    $this->assertEquals('Foobar', (string) $methods->get('barfoo')->declaringClass()->name());
                },
            ],
        ];
    }

    /**
     * @dataProvider provideReflectionMethodCollection
     */
    public function testReflectCollection(string $source, string $class, \Closure $assertion)
    {
        $class = $this->createReflector($source)->reflectClassLike(ClassName::fromString($class));
        $assertion($class);
    }

    public function provideReflectionMethodCollection()
    {
        return [
            'Only methods belonging to a given class' => [
                <<<'EOT'
<?php

class ParentClass
{
    public function method1() {}
}

class Foobar extends ParentClass
{
    public function method4() {}
}
EOT
                ,
                'Foobar',
                function (ReflectionClass $class) {
                    $methods = $class->methods()->belongingTo($class->name());
                    $this->assertEquals(
                        ['method4'],
                        $methods->keys()
                    );
                },
            ],
        ];
    }
}
