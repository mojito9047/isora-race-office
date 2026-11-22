<?php

/**
 * A very small test framework, so the tests need nothing but PHP.
 *
 * A test class extends TestCase; every public method whose name starts with
 * "test" is one test. setUp() runs before each test and tearDown() after it.
 */

namespace IsoraTests;

class Failure extends \Exception
{
}

abstract class TestCase
{
    /** Number of checks made, across all tests. */
    public static int $assertions = 0;

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function runTest(string $method): void
    {
        $this->setUp();

        try {
            $this->$method();
        } finally {
            $this->tearDown();
        }
    }

    protected function fail(string $message): void
    {
        throw new Failure($message);
    }

    protected function assertTrue($value, string $what = ''): void
    {
        self::$assertions++;

        if ($value !== true) {
            $this->fail(($what ?: 'Value') . ' should be true, got ' . self::show($value));
        }
    }

    protected function assertFalse($value, string $what = ''): void
    {
        self::$assertions++;

        if ($value !== false) {
            $this->fail(($what ?: 'Value') . ' should be false, got ' . self::show($value));
        }
    }

    protected function assertSame($expected, $actual, string $what = ''): void
    {
        self::$assertions++;

        if ($expected !== $actual) {
            $this->fail(($what ? $what . ': ' : '') . 'expected ' . self::show($expected) . ', got ' . self::show($actual));
        }
    }

    /** Equal as numbers, to the given number of decimal places. */
    protected function assertNumber(float $expected, $actual, string $what = '', int $places = 3): void
    {
        self::$assertions++;

        if (!is_numeric($actual) || round((float) $actual, $places) !== round($expected, $places)) {
            $this->fail(($what ? $what . ': ' : '') . 'expected ' . $expected . ', got ' . self::show($actual));
        }
    }

    protected function assertCount(int $expected, $actual, string $what = ''): void
    {
        self::$assertions++;

        if (\count($actual) !== $expected) {
            $this->fail(($what ?: 'Count') . ': expected ' . $expected . ' item(s), got ' . \count($actual));
        }
    }

    protected function assertContains(string $needle, string $haystack, string $what = ''): void
    {
        self::$assertions++;

        if (strpos($haystack, $needle) === false) {
            $this->fail(($what ?: 'Text') . ' should contain ' . self::show($needle) . '; got ' . self::show(self::excerpt($haystack)));
        }
    }

    protected function assertNotContains(string $needle, string $haystack, string $what = ''): void
    {
        self::$assertions++;
        $at = strpos($haystack, $needle);

        if ($at !== false) {
            $this->fail(($what ?: 'Text') . ' should not contain ' . self::show($needle) . '; found near ' . self::show(substr($haystack, max(0, $at - 60), 160)));
        }
    }

    protected function assertMatches(string $pattern, string $text, string $what = ''): void
    {
        self::$assertions++;

        if (!preg_match($pattern, $text)) {
            $this->fail(($what ?: 'Text') . ' should match ' . $pattern . '; got ' . self::show(self::excerpt($text)));
        }
    }

    /** The call must throw, with a message containing $messagePart. */
    protected function assertThrows(callable $call, string $messagePart = '', string $what = ''): void
    {
        self::$assertions++;

        try {
            $call();
        } catch (Failure $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($messagePart !== '' && stripos($e->getMessage(), $messagePart) === false) {
                $this->fail(($what ?: 'Error') . ' should mention ' . self::show($messagePart) . ', got ' . self::show($e->getMessage()));
            }

            return;
        }

        $this->fail(($what ?: 'The call') . ' should have failed but did not');
    }

    private static function excerpt(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return \strlen($text) > 400 ? substr($text, 0, 400) . '...' : $text;
    }

    private static function show($value): string
    {
        if (\is_string($value)) {
            return '"' . $value . '"';
        }

        return \is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value);
    }
}

class Runner
{
    /**
     * Run every test in the given classes and print one line per test.
     *
     * @param string[] $classes
     * @param string   $filter  Only tests whose "Class::method" contains this text
     *
     * @return int Number of failed tests
     */
    public static function run(array $classes, string $filter = ''): int
    {
        $passed = 0;
        $failed = [];
        $start  = microtime(true);

        foreach ($classes as $class) {
            $short   = substr($class, strrpos($class, '\\') + 1);
            $methods = array_filter(get_class_methods($class), static function ($method) use ($short, $filter) {
                return strncmp($method, 'test', 4) === 0 && ($filter === '' || stripos($short . '::' . $method, $filter) !== false);
            });

            if (!$methods) {
                continue;
            }

            echo "\n", $short, "\n";

            foreach ($methods as $method) {
                $label = self::label($method);

                try {
                    (new $class())->runTest($method);
                    echo '  ok    ', $label, "\n";
                    $passed++;
                } catch (\Throwable $e) {
                    $where = $e instanceof Failure ? self::caller($e) : basename($e->getFile()) . ':' . $e->getLine();
                    $text  = ($e instanceof Failure ? '' : \get_class($e) . ': ') . $e->getMessage();
                    echo '  FAIL  ', $label, "\n        ", $text, "\n        at ", $where, "\n";
                    $failed[] = $short . '::' . $method;
                }
            }
        }

        echo "\n", str_repeat('-', 60), "\n";
        echo $passed + \count($failed), ' tests, ', TestCase::$assertions, ' checks, ', \count($failed), ' failed (',
            number_format(microtime(true) - $start, 1), " s)\n";

        foreach ($failed as $name) {
            echo '  failed: ', $name, "\n";
        }

        return \count($failed);
    }

    /** "testLatestFormWins" => "latest form wins". */
    private static function label(string $method): string
    {
        return strtolower(trim(preg_replace('/(?<!^)([A-Z]|\d+)/', ' $1', substr($method, 4))));
    }

    /** File and line of the test that made the failing check. */
    private static function caller(\Throwable $e): string
    {
        foreach ($e->getTrace() as $frame) {
            if (isset($frame['file']) && basename($frame['file']) !== 'framework.php') {
                return basename($frame['file']) . ':' . $frame['line'];
            }
        }

        return basename($e->getFile()) . ':' . $e->getLine();
    }
}
