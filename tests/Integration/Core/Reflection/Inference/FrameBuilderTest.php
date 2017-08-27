<?php

namespace Phpactor\WorseReflection\Tests\Integration\Core\Reflection\Inference;

use Phpactor\WorseReflection\Tests\Integration\IntegrationTestCase;
use Phpactor\WorseReflection\Core\Type;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Reflection\Inference\Frame;
use Phpactor\WorseReflection\Core\Reflection\Inference\Symbol;
use Phpactor\WorseReflection\Tests\Integration\Util\CodeHelper;
use Phpactor\WorseReflection\Core\Offset;
use Phpactor\WorseReflection\Core\SourceCode;

class FrameBuilderTest extends IntegrationTestCase
{
    /**
     * @dataProvider provideForMethod
     */
    public function testForMethod(string $source, array $classAndMethod, \Closure $assertion)
    {
        list($className, $methodName) = $classAndMethod;
        $reflector = $this->createReflector($source);
        $method = $reflector->reflectClass(ClassName::fromString($className))->methods()->get($methodName);
        $frame = $method->frame();

        $assertion($frame);
    }

    public function provideForMethod()
    {
        return [
            'It returns this' => [
                <<<'EOT'
<?php

namespace Foobar\Barfoo;

use Acme\Factory;

class Foobar
{
    public function hello()
    {
    }
}

EOT
            , [ 'Foobar\Barfoo\Foobar', 'hello' ], function (Frame $frame) {
                $this->assertCount(1, $frame->locals()->byName('$this'));
                $this->assertEquals(Type::fromString('Foobar\Barfoo\Foobar'), $frame->locals()->byName('$this')->first()->symbolInformation()->type());
                $this->assertEquals(Symbol::VARIABLE, $frame->locals()->byName('$this')->first()->symbolInformation()->symbol()->symbolType());
            }],
            'It returns method arguments' => [
                <<<'EOT'
<?php

namespace Foobar\Barfoo;

use Acme\Factory;
use Phpactor\WorseReflection\Core\Logger\ArrayLogger;

class Foobar
{
    public function hello(World $world)
    {
    }
}

EOT
            , [ 'Foobar\Barfoo\Foobar', 'hello' ], function (Frame $frame) {
                $this->assertCount(1, $frame->locals()->byName('$this'));
                $this->assertEquals(Type::fromString('Foobar\Barfoo\Foobar'), $frame->locals()->byName('$this')->first()->symbolInformation()->type());
            }],
            'It registers string assignments' => [
                <<<'EOT'
<?php

class Foobar
{
    public function hello()
    {
        $foobar = 'foobar';
    }
}

EOT
            , [ 'Foobar', 'hello' ], function (Frame $frame) {
                $this->assertCount(1, $frame->locals()->byName('$foobar'));
                $symbolInformation = $frame->locals()->byName('$foobar')->first()->symbolInformation();
                $this->assertEquals('string', (string) $symbolInformation->type());
                $this->assertEquals('foobar', (string) $symbolInformation->value());
            }],
            'It returns types for reassigned variables' => [
                <<<'EOT'
<?php

class Foobar
{
    public function hello(World $world = 'test')
    {
        $foobar = $world;
    }
}

EOT
            , [ 'Foobar', 'hello' ], function (Frame $frame) {
                $vars = $frame->locals()->byName('$foobar');
                $this->assertCount(1, $vars);
                $symbolInformation = $vars->first()->symbolInformation();
                $this->assertEquals('World', (string) $symbolInformation->type());
                $this->assertEquals('test', (string) $symbolInformation->value());
            }],
            'It returns type for $this' => [
                <<<'EOT'
<?php

class Foobar
{
    public function hello(World $world)
    {
    }
}

EOT
            , [ 'Foobar', 'hello' ], function (Frame $frame) {
                $vars = $frame->locals()->byName('$this');
                $this->assertCount(1, $vars);
                $symbolInformation = $vars->first()->symbolInformation();
                $this->assertEquals('Foobar', (string) $symbolInformation->type());
            }],
            'It tracks assigned properties' => [
                <<<'EOT'
<?php

class Foobar
{
    public function hello(Barfoo $world)
    {
        $this->foobar = 'foobar';
    }
}
EOT
            , [ 'Foobar', 'hello' ], function (Frame $frame) {
                $vars = $frame->properties()->byName('foobar');
                $this->assertCount(1, $vars);
                $symbolInformation = $vars->first()->symbolInformation();
                $this->assertEquals('string', (string) $symbolInformation->type());
                $this->assertEquals('foobar', (string) $symbolInformation->value());
            }],
            'It tracks assigned from variable' => [
                <<<'EOT'
<?php

class Foobar
{
    public function hello(Barfoo $world)
    {
        $foobar = 'foobar';
        $this->$foobar = 'foobar';
    }
}
EOT
            , [ 'Foobar', 'hello' ], function (Frame $frame) {
                $vars = $frame->properties()->byName('foobar');
                $this->assertCount(1, $vars);
                $symbolInformation = $vars->first()->symbolInformation();
                $this->assertEquals('string', (string) $symbolInformation->type());
                $this->assertEquals('foobar', (string) $symbolInformation->value());
            }],
            'It returns type for a for each member (with a docblock)' => [
                <<<'EOT'
<?php

class Foobar
{
    public function hello()
    {
        /** @var $foobar Foobar */
        foreach ($collection as $foobar) {
            $foobar->foobar();
        }
    }
}
EOT
            , [ 'Foobar', 'hello' ], function (Frame $frame) {
                $vars = $frame->locals()->byName('$foobar');
                $this->assertCount(1, $vars);
                $symbolInformation = $vars->first()->symbolInformation();
                $this->assertEquals('Foobar', (string) $symbolInformation->type());
            }],
            'Redeclared variables' => [
                <<<'EOT'
<?php

class Foobar
{
    public function hello()
    {
        $foobar = new Foobar();
        $foobar = new \stdClass();
    }
}
EOT
            , [ 'Foobar', 'hello' ], function (Frame $frame) {
                $vars = $frame->locals()->byName('$foobar');
                $this->assertCount(2, $vars);
                $this->assertEquals('Foobar', (string) $vars->first()->symbolInformation()->type()->className());
                $this->assertEquals('stdClass', (string) $vars->last()->symbolInformation()->type()->className());
            }],
        ];
    }

    /**
     * @dataProvider provideBuildForNode
     */
    public function testBuildForNode(string $source, \Closure $assertion)
    {
        list($source, $offset) = CodeHelper::offsetFromCode($source);
        $reflector = $this->createReflector($source);
        $offset = $reflector->reflectOffset(SourceCode::fromString($source), Offset::fromInt($offset));
        $assertion($offset->frame());
    }

    public function provideBuildForNode()
    {
        return [
            'Respects closure scope' => [
                <<<'EOT'
<?php
$foo = 'bar';

$hello = function () {
    $bar = 'foo';
    <>
};
EOT
                , 
                function (Frame $frame) {
                    $this->assertCount(1, $frame->locals()->byName('$bar'));
                    $this->assertCount(0, $frame->locals()->byName('$foo'));
                }
            ],
            'Injects closure parameters' => [
                <<<'EOT'
<?php
$foo = 'bar';

$hello = function (Foobar $foo) {
    <>
};
EOT
                , 
                function (Frame $frame) {
                    $this->assertCount(1, $frame->locals()->byName('$foo'));
                }
            ],
            'Injects imported closure parent scope variables' => [
                <<<'EOT'
<?php
$zed = 'zed';
$art = 'art';

$hello = function () use ($zed) {
    <>
};
EOT
                , 
                function (Frame $frame) {
                    $this->assertCount(1, $frame->locals()->byName('$zed'));
                    $this->assertEquals('string', (string) $frame->locals()->byName('$zed')->first()->symbolInformation()->type());
                }
            ]
        ];
    }
}
