<?php

namespace Phpactor\WorseReflection;

use Phpactor\WorseReflection\Core\Logger\ArrayLogger;
use Phpactor\WorseReflection\Core\Logger;
use Phpactor\WorseReflection\Core\SourceCodeLocator;
use Phpactor\WorseReflection\Core\ServiceLocator;
use Phpactor\WorseReflection\Core\SourceCodeLocator\ChainSourceLocator;
use Phpactor\WorseReflection\Core\SourceCodeLocator\StringSourceLocator;
use Phpactor\WorseReflection\Core\SourceCode;
use Phpactor\WorseReflection\Core\SourceCodeLocator\TemporarySourceLocator;

final class ReflectorBuilder
{
    /**
     * @var ArrayLogger
     */
    private $logger;

    /**
     * @var SourceCodeLocator[]
     */
    private $locators;

    /**
     * @var bool
     */
    private $contextualSourceLocation = false;

    /**
     * @var bool
     */
    private $enableCache = false;

    /**
     * @var bool
     */
    private $enableContextualSourceLocation = false;

    /**
     * Create a new instance of the builder
     */
    public static function create(): ReflectorBuilder
    {
        return new self();
    }

    /**
     * Replace the logger implementation.
     */
    public function withLogger(Logger $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Add a source locator
     */
    public function addLocator(SourceCodeLocator $locator): ReflectorBuilder
    {
        $this->locators[] = $locator;

        return $this;
    }

    /**
     * Add some source code
     */
    public function addSource($code): ReflectorBuilder
    {
        $source = SourceCode::fromUnknown($code);

        $this->addLocator(new StringSourceLocator($source));

        return $this;
    }

    /**
     * Build the reflector
     */
    public function build(): Reflector
    {
        return (new ServiceLocator(
            $this->buildLocator(),
            $this->buildLogger(),
            $this->enableCache,
            $this->enableContextualSourceLocation
        ))->reflector();
    }

    /**
     * Enable contextual source location.
     *
     * Enable WR to locate classes from any source code passed
     * to the SourceReflector (this is to enable property / class
     * reflection on the current class.
     *
     * WARNING: This makes the reflector stateful - any source code
     *          passed to source reflector methods will be retained
     *          for the duration of the process.
     */
    public function enableContextualSourceLocation(): ReflectorBuilder
    {
        $this->enableContextualSourceLocation = true;

        return $this;
    }

    /**
     * Enable class reflection cache.
     *
     * Wraps the ClassReflector in a memonizing cache.
     */
    public function enableCache(): ReflectorBuilder
    {
        $this->enableCache = true;

        return $this;
    }

    private function buildLocator(): SourceCodeLocator
    {
        if (empty($this->locators)) {
            return new StringSourceLocator(SourceCode::empty());
        }

        if (count($this->locators) > 1) {
            return new ChainSourceLocator($this->locators);
        }

        return reset($this->locators);
    }

    private function buildLogger(): Logger
    {
        return $this->logger ?: new ArrayLogger();
    }
}
