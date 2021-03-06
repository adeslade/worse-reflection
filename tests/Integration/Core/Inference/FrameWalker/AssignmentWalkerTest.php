<?php

namespace Phpactor\WorseReflection\Tests\Integration\Core\Inference\FrameWalker;

use Phpactor\WorseReflection\Tests\Integration\Core\Inference\FrameWalkerTestCase;
use Phpactor\WorseReflection\Core\Inference\Frame;
use Generator;

class AssignmentWalkerTest extends FrameWalkerTestCase
{
    public function provideWalk(): Generator
    {
        yield 'It registers string assignments' => [
            <<<'EOT'
<?php

class Foobar
{
    public function hello()
    {
        $foobar = 'foobar';
        <>
    }
}

EOT
        , function (Frame $frame) {
            $this->assertCount(1, $frame->locals()->byName('foobar'));
            $symbolInformation = $frame->locals()->byName('foobar')->first()->symbolContext();
            $this->assertEquals('string', (string) $symbolInformation->type());
            $this->assertEquals('foobar', (string) $symbolInformation->value());
        }];
        yield 'It returns types for reassigned variables' => [
            <<<'EOT'
<?php

class Foobar
{
    public function hello(World $world = 'test')
    {
        $foobar = $world;
        <>
    }
}

EOT
        , function (Frame $frame) {
            $vars = $frame->locals()->byName('foobar');
            $this->assertCount(1, $vars);
            $symbolInformation = $vars->first()->symbolContext();
            $this->assertEquals('World', (string) $symbolInformation->type());
            $this->assertEquals('test', (string) $symbolInformation->value());
        }];

        yield 'It returns type for $this' => [
            <<<'EOT'
<?php

class Foobar
{
    public function hello(World $world)
    {
        <>
    }
}

EOT
        , function (Frame $frame) {
            $vars = $frame->locals()->byName('this');
            $this->assertCount(1, $vars);
            $symbolInformation = $vars->first()->symbolContext();
            $this->assertEquals('Foobar', (string) $symbolInformation->type());
        }];

        yield 'It tracks assigned properties' => [
            <<<'EOT'
<?php

class Foobar
{
    public function hello(Barfoo $world)
    {
        $this->foobar = 'foobar';
        <>
    }
}
EOT
        , function (Frame $frame) {
            $vars = $frame->properties()->byName('foobar');
            $this->assertCount(1, $vars);
            $symbolInformation = $vars->first()->symbolContext();
            $this->assertEquals('string', (string) $symbolInformation->type());
            $this->assertEquals('foobar', (string) $symbolInformation->value());
        }];

        yield 'It assigns property values to assignments' => [
            <<<'EOT'
<?php

class Foobar
{
    /** @var Foobar[] */
    private $foobar;

    public function hello(Barfoo $world)
    {
        $foobar = $this->foobar;
        <>
    }
}
EOT
        , function (Frame $frame) {
            $vars = $frame->locals()->byName('foobar');
            $this->assertCount(1, $vars);
            $symbolInformation = $vars->first()->symbolContext();
            $this->assertEquals('Foobar[]', (string) $symbolInformation->type());
            $this->assertEquals('Foobar', (string) $symbolInformation->type()->arrayType());
        }];


        yield 'It tracks assigned array properties' => [
            <<<'EOT'
<?php

class Foobar
{
    public function hello()
    {
        $this->foobar[] = 'foobar';
        <>
    }
}
EOT
        , function (Frame $frame) {
            $vars = $frame->properties()->byName('foobar');
            $this->assertCount(1, $vars);
            $symbolInformation = $vars->first()->symbolContext();
            $this->assertEquals('array', (string) $symbolInformation->type());
            $this->assertEquals('foobar', (string) $symbolInformation->value());
        }];

        yield 'It tracks assigned from variable' => [
            <<<'EOT'
<?php

class Foobar
{
    public function hello(Barfoo $world)
    {
        $foobar = 'foobar';
        $this->$foobar = 'foobar';
        <>
    }
}
EOT
        , function (Frame $frame) {
            $vars = $frame->properties()->byName('foobar');
            $this->assertCount(1, $vars);
            $symbolInformation = $vars->first()->symbolContext();
            $this->assertEquals('string', (string) $symbolInformation->type());
            $this->assertEquals('foobar', (string) $symbolInformation->value());
        }];

        yield 'Handles array assignments' => [
            <<<'EOT'
<?php
$foo = [ 'foo' => 'bar' ];
$bar = $foo['foo'];
<>
EOT
        ,
            function (Frame $frame) {
                $this->assertCount(2, $frame->locals());
                $this->assertEquals('array', (string) $frame->locals()->first()->symbolContext()->type());
                $this->assertEquals(['foo' => 'bar'], $frame->locals()->first()->symbolContext()->value());
                $this->assertEquals('string', (string) $frame->locals()->last()->symbolContext()->type());
                $this->assertEquals('bar', (string) $frame->locals()->last()->symbolContext()->value());
            }
        ];

        yield 'Includes list assignments' => [
            <<<'EOT'
<?php
list($foo, $bar) = [ 'foo', 'bar' ];
<>
EOT
        ,
            function (Frame $frame) {
                $this->assertCount(2, $frame->locals());
                $this->assertEquals('foo', $frame->locals()->first()->symbolContext()->value());
                $this->assertEquals('string', (string) $frame->locals()->first()->symbolContext()->type());
            }
        ];

        yield 'From return type with docblock' => [
            <<<'EOT'
<?php

namespace Foobar;

use Foo\Lister;

interface Barfoo
{
    /**
     * @return Lister<Collection>
     */
    public static function bar(): List;
}

class Baz
{
    public function (Barfoo $barfoo)
    {
        $bar = $barfoo->bar();
        <>
    }
}
<>
}
EOT
        ,
            function (Frame $frame) {
                $this->assertCount(3, $frame->locals());
                $this->assertEquals('Foo\Lister<Foobar\Collection>', (string) $frame->locals()->byName('bar')->first()->symbolContext()->types()->best());
                $this->assertEquals('Foobar\Collection', (string) $frame->locals()->byName('bar')->first()->symbolContext()->types()->best()->arrayType());
            }
        ];
    }
}
