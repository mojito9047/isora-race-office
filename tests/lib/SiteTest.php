<?php

namespace IsoraTests;

/**
 * A test that starts from an empty extension with default settings.
 */
abstract class SiteTest extends TestCase
{
    protected function setUp(): void
    {
        Env::reset();
    }

    /** PHP problems that Joomla prints into a page when error reporting is at maximum. */
    protected function assertNoPhpErrors(Response $page, string $what = 'Page'): void
    {
        foreach (['<b>Warning</b>', '<b>Deprecated</b>', '<b>Notice</b>', '<b>Fatal error</b>', 'Warning: ', 'Deprecated: ', 'Fatal error: ', 'Stack trace', 'Call Stack'] as $sign) {
            $this->assertNotContains($sign, $page->body, $what);
        }
    }
}
