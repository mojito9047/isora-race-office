<?php

/**
 * Quick checks of the project's files. Needs only PHP, so it runs anywhere,
 * including on Windows:
 *
 *   php tests/static.php
 *
 * It catches the slips that are easy to make when preparing a version: a
 * syntax error, version numbers that disagree, a file missing from the
 * package, a changelog or guide that was not updated.
 */

namespace IsoraTests\Cases;

use IsoraTests\Runner;
use IsoraTests\TestCase;

require __DIR__ . '/lib/framework.php';

class ProjectFilesTest extends TestCase
{
    private const MANIFESTS = [
        'src/com_isorairc/isorairc.xml',
        'src/plg_task_isorairc/isorairc.xml',
        'src/pkg_isorairc/pkg_isorairc.xml',
    ];

    private function root(): string
    {
        return \dirname(__DIR__);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->root() . '/' . $path);
    }

    /** @return string[] Paths below the project root */
    private function files(string $folder, string $pattern): array
    {
        $found = [];
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . '/' . $folder, \FilesystemIterator::SKIP_DOTS));

        foreach ($items as $item) {
            $path = str_replace('\\', '/', substr($item->getPathname(), \strlen($this->root()) + 1));

            if (preg_match($pattern, $path)) {
                $found[] = $path;
            }
        }

        sort($found);

        return $found;
    }

    private function version(): string
    {
        return (string) simplexml_load_string($this->read(self::MANIFESTS[0]))->version;
    }

    public function testEveryPhpFileIsFreeOfSyntaxErrors(): void
    {
        foreach (array_merge($this->files('src', '/\.php$/'), $this->files('tests', '/\.php$/')) as $file) {
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($this->root() . '/' . $file) . ' 2>&1', $output, $code);
            $this->assertSame(0, $code, $file . ': ' . implode(' ', $output));
            $output = [];
        }
    }

    public function testEveryPhpFileInThePackageRefusesToRunOutsideJoomla(): void
    {
        foreach ($this->files('src', '/\.php$/') as $file) {
            $this->assertContains("defined('_JEXEC') or die", $this->read($file), $file);
        }
    }

    public function testEveryXmlFileIsWellFormed(): void
    {
        foreach ($this->files('src', '/\.xml$/') as $file) {
            $this->assertTrue(@simplexml_load_string($this->read($file)) !== false, $file . ' is well-formed');
        }
    }

    public function testAllThreeManifestsHaveTheSameVersion(): void
    {
        $versions = [];

        foreach (self::MANIFESTS as $manifest) {
            $versions[$manifest] = (string) simplexml_load_string($this->read($manifest))->version;
        }

        $this->assertMatches('/^\d+\.\d+\.\d+$/', $this->version(), 'Version');
        $this->assertCount(1, array_unique($versions), 'Different versions: ' . json_encode($versions));
    }

    public function testChangelogStartsWithThisVersion(): void
    {
        preg_match('/^## (\S+)/m', $this->read('CHANGELOG.md'), $match);

        $this->assertSame($this->version(), $match[1] ?? '', 'Newest entry in CHANGELOG.md');
    }

    public function testChangelogVersionsAreNewestFirstWithoutRepeats(): void
    {
        preg_match_all('/^## (\d+\.\d+\.\d+) \(\d{1,2} \w+ \d{4}\)$/m', $this->read('CHANGELOG.md'), $matches);
        $versions = $matches[1];
        $sorted   = $versions;
        usort($sorted, static function ($a, $b) {
            return version_compare($b, $a);
        });

        $this->assertTrue(\count($versions) > 0, 'Changelog has entries');
        $this->assertSame($sorted, $versions, 'Order of entries');
        $this->assertSame(\count($versions), \count(array_unique($versions)), 'No version listed twice');
        $this->assertSame(preg_match_all('/^## /m', $this->read('CHANGELOG.md')), \count($versions), 'Every heading is "## x.y.z (day month year)"');
    }

    public function testAdminGuideIsMarkedForThisVersion(): void
    {
        $this->assertContains('Written for version ' . $this->version() . '.', $this->read('docs/admin-guide.md'));
    }

    public function testComponentManifestListsEveryFolderAndFile(): void
    {
        $manifest = simplexml_load_string($this->read(self::MANIFESTS[0]));
        $parts    = ['site' => $manifest->files, 'admin' => $manifest->administration->files];

        foreach ($parts as $folder => $files) {
            $listed = [];

            foreach ($files->children() as $entry) {
                $listed[] = (string) $entry;
            }

            $actual = array_map('basename', glob($this->root() . '/src/com_isorairc/' . $folder . '/*'));
            sort($listed);
            sort($actual);
            $this->assertSame($actual, $listed, 'Entries under <files folder="' . $folder . '">');
        }
    }

    public function testPluginManifestListsEveryFolder(): void
    {
        $manifest = simplexml_load_string($this->read(self::MANIFESTS[1]));
        $listed   = [];

        foreach ($manifest->files->children() as $entry) {
            $listed[] = (string) $entry;
        }

        foreach (glob($this->root() . '/src/plg_task_isorairc/*', GLOB_ONLYDIR) as $folder) {
            $this->assertTrue(\in_array(basename($folder), $listed, true) || basename($folder) === 'language', basename($folder) . ' is in the plugin manifest');
        }
    }

    public function testDatabaseUpdateFilesAreNamedAsVersionsUpToThisOne(): void
    {
        $files = $this->files('src/com_isorairc/admin/sql/updates/mysql', '/\.sql$/');
        $this->assertTrue(\count($files) > 0);

        foreach ($files as $file) {
            $name = basename($file, '.sql');
            $this->assertMatches('/^\d+\.\d+\.\d+$/', $name, $file);
            $this->assertTrue(version_compare($name, $this->version(), '<='), $file . ' is not newer than the package');
        }
    }

    public function testEveryTableCreatedIsAlsoRemovedOnUninstall(): void
    {
        preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)`/', $this->read('src/com_isorairc/admin/sql/install.mysql.utf8.sql'), $created);
        preg_match_all('/DROP TABLE IF EXISTS `([^`]+)`/', $this->read('src/com_isorairc/admin/sql/uninstall.mysql.utf8.sql'), $dropped);
        sort($created[1]);
        sort($dropped[1]);

        $this->assertCount(5, $created[1]);
        $this->assertSame($created[1], $dropped[1]);
    }

    public function testEveryColumnAddedByAnUpdateIsAlsoInAFreshInstall(): void
    {
        $install = $this->read('src/com_isorairc/admin/sql/install.mysql.utf8.sql');
        $dropped = [];
        $added   = [];

        foreach ($this->files('src/com_isorairc/admin/sql/updates/mysql', '/\.sql$/') as $file) {
            preg_match_all('/ALTER TABLE `([^`]+)` (ADD|DROP) COLUMN `([^`]+)`/', $this->read($file), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $table, $action, $column]) {
                if ($action === 'ADD') {
                    $added[$table . '.' . $column] = [$table, $column];
                } else {
                    $dropped[$table . '.' . $column] = true;
                }
            }
        }

        foreach ($added as $key => [$table, $column]) {
            if (isset($dropped[$key])) {
                continue;
            }

            preg_match('/CREATE TABLE IF NOT EXISTS `' . preg_quote($table, '/') . '` \((.*?)\) ENGINE/s', $install, $definition);
            $this->assertContains('`' . $column . '`', $definition[1] ?? '', $key . ' in install.mysql.utf8.sql');
        }
    }

    public function testEveryLanguageKeyUsedHasATranslation(): void
    {
        $translated = [];

        foreach ($this->files('src', '/\.ini$/') as $file) {
            preg_match_all('/^([A-Z0-9_]+)=/m', $this->read($file), $keys);
            $translated += array_flip($keys[1]);
        }

        foreach ($this->files('src', '/\.(php|xml)$/') as $file) {
            preg_match_all('/\b((?:COM|PLG_TASK|PKG)_ISORAIRC[A-Z0-9_]*)\b/', $this->read($file), $used);

            foreach (array_unique($used[1]) as $key) {
                // Joomla adds these endings itself.
                if (preg_match('/^(COM_ISORAIRC|PLG_TASK_ISORAIRC_IMPORT)$/', $key)) {
                    $this->assertTrue(isset($translated[$key]) || isset($translated[$key . '_TITLE']), $key . ' used in ' . $file);

                    continue;
                }

                $this->assertTrue(isset($translated[$key]), $key . ' used in ' . $file . ' has a translation');
            }
        }
    }

    public function testFleetsheetTemplateIsACleanWorkbook(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            echo "        (skipped: this PHP has no zip extension)
";

            return;
        }

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($this->root() . '/src/com_isorairc/admin/resources/yb-fleetsheet-template.xlsx') === true, 'Template opens');
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertContains('<row r="5"', $sheet, 'Heading row');
        $this->assertContains('<row r="7"', $sheet, 'First boat row');
        $this->assertNotContains('<row r="8"', $sheet, 'No boats left in the template');
        $this->assertNotContains('<hyperlink', $sheet, 'No links left in the template');
    }

    public function testBuildScriptMakesACompletePackage(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            echo "        (skipped: this PHP has no zip extension)
";

            return;
        }

        $python = null;

        foreach (['python3', 'python'] as $candidate) {
            exec($candidate . ' --version 2>&1', $ignored, $code);

            if ($code === 0) {
                $python = $candidate;

                break;
            }
        }

        if ($python === null) {
            echo "        (skipped: Python is not installed)\n";

            return;
        }

        exec($python . ' ' . escapeshellarg($this->root() . '/build.py') . ' 2>&1', $output, $code);
        $this->assertSame(0, $code, 'build.py: ' . implode(' ', $output));

        $package = new \ZipArchive();
        $this->assertTrue($package->open($this->root() . '/dist/pkg_isorairc.zip') === true);

        foreach (['pkg_isorairc.xml', 'script.php', 'packages/com_isorairc.zip', 'packages/plg_task_isorairc.zip'] as $name) {
            $this->assertTrue($package->locateName($name) !== false, $name . ' is in the package');
        }

        $this->assertContains('<version>' . $this->version() . '</version>', (string) $package->getFromName('pkg_isorairc.xml'));

        // Every source file of the component must be inside its zip.
        $inner = tempnam(sys_get_temp_dir(), 'com');
        file_put_contents($inner, $package->getFromName('packages/com_isorairc.zip'));
        $package->close();
        $component = new \ZipArchive();
        $component->open($inner);

        foreach ($this->files('src/com_isorairc', '/./') as $file) {
            $name = substr($file, \strlen('src/com_isorairc/'));
            $this->assertTrue($component->locateName($name) !== false, $name . ' is in com_isorairc.zip');
        }

        $component->close();
        unlink($inner);
    }

    public function testNothingPrivateIsTrackedByGit(): void
    {
        exec('git -C ' . escapeshellarg($this->root()) . ' ls-files 2>&1', $tracked, $code);

        if ($code !== 0) {
            echo "        (skipped: not a git checkout)\n";

            return;
        }

        foreach ($tracked as $file) {
            $this->assertFalse((bool) preg_match('#^(backup|dist)/|\.(jpa|j\d\d|sql|sql\.gz|env)$|configuration\.php$#', $file) && strpos($file, 'src/') !== 0, $file . ' should not be in the repository');
        }
    }
}

exit(Runner::run([ProjectFilesTest::class], $argv[1] ?? '') ? 1 : 0);
