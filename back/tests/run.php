<?php
declare(strict_types=1);

// Les tests d'integration ne tournent que si TEST_DB_NAME est defini (fait par run.sh).

use Tests\TestCase;
use Tests\TestServer;

// Les tests doivent echouer sur un warning PHP, pas le noyer dans un log.
set_error_handler(static function (int $type, string $message, string $file, int $line): bool {
    if ((error_reporting() & $type) === 0) {
        return false; // operateur @
    }
    throw new ErrorException($message, 0, $type, $file, $line);
});

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/autoload.php';

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Tests\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$args     = array_slice($argv, 1);
$unitOnly = in_array('--unit', $args, true);
$filter   = array_values(array_filter($args, fn (string $a): bool => !str_starts_with($a, '--')))[0] ?? '';
$testDb   = (string) getenv('TEST_DB_NAME');

// Dossier jetable : logs du runner et du serveur, jamais ceux de dev.
$tmpDir = sys_get_temp_dir() . '/dayout-tests-' . bin2hex(random_bytes(4));
mkdir($tmpDir . '/logs', 0775, true);
putenv("LOG_PATH=$tmpDir/logs"); // herite par les sous-processus (fixtures)
putenv('TEST_TMP_DIR=' . $tmpDir);
App\Core\Log\Log::configure(App\Core\Log\Level::Debug, "$tmpDir/logs");

$suites = ['Unit' => __DIR__ . '/Unit'];
if (!$unitOnly && $testDb !== '') {
    $suites['Http'] = __DIR__ . '/Http';
} elseif (!$unitOnly) {
    echo "TEST_DB_NAME absent : tests d'integration ignores (lancer tests/run.sh).\n";
}

$server = null;
$exit   = 1;
try {
    if (isset($suites['Http'])) {
        putenv("TEST_SERVER_LOG_PATH=$tmpDir/server-logs");
        $server = TestServer::start(APP_ROOT . '/public', [
            'DB_NAME'     => $testDb,
            'LOG_PATH'    => "$tmpDir/server-logs",
            'LOG_LEVEL'   => 'info',
            'APP_DEBUG'   => 'false',
            'CORS_ORIGIN' => Tests\HttpTestCase::ALLOWED_ORIGIN,
        ], "$tmpDir/server.out");
        putenv('TEST_BASE_URL=' . $server->baseUrl);
    }
    $exit = runSuites($suites, $filter);
} finally {
    $server?->stop();
    removeDir($tmpDir);
}
exit($exit);

/** @param array<string, string> $suites nom => dossier */
function runSuites(array $suites, string $filter): int
{
    $passed = $assertions = 0;
    $problems = [];
    $start = microtime(true);

    foreach ($suites as $suite => $dir) {
        echo "\n$suite\n";
        foreach (glob($dir . '/*Test.php') ?: [] as $file) {
            $class = "Tests\\$suite\\" . basename($file, '.php');
            $tests = array_filter(
                get_class_methods($class),
                fn (string $m): bool => str_starts_with($m, 'test') && ($filter === '' || str_contains(basename($file, '.php') . "::$m", $filter))
            );
            if ($tests === []) {
                continue;
            }
            echo '  ' . basename($file, '.php') . ' ';
            foreach ($tests as $method) {
                /** @var TestCase $test */
                $test = new $class();
                try {
                    $test->setUp();
                    $test->$method();
                    if ($test->assertions === 0) {
                        throw new Tests\AssertionFailed('Test made no assertion');
                    }
                    $passed++;
                    echo '.';
                } catch (Tests\AssertionFailed $e) {
                    $problems[] = ['FAIL', "$class::$method", $e->getMessage(), $e];
                    echo 'F';
                } catch (Throwable $e) {
                    $problems[] = ['ERROR', "$class::$method", $e::class . ': ' . $e->getMessage(), $e];
                    echo 'E';
                }
                $assertions += $test->assertions;
            }
            echo "\n";
        }
    }

    foreach ($problems as $i => [$kind, $name, $message, $e]) {
        $where = testLocation($e);
        echo "\n" . ($i + 1) . ") $kind $name" . ($where !== '' ? " ($where)" : '') . "\n   " . str_replace("\n", "\n   ", $message) . "\n";
    }

    $total = $passed + count($problems);
    if ($total === 0) {
        echo "\nAucun test ne correspond au filtre.\n";
        return 1;
    }
    printf(
        "\n%s : %d/%d tests, %d assertions, %.2fs\n",
        $problems === [] ? 'OK' : 'ECHEC',
        $passed,
        $total,
        $assertions,
        microtime(true) - $start
    );
    return $problems === [] ? 0 : 1;
}

/** Ligne du fichier de test ou l'assertion a echoue. */
function testLocation(Throwable $e): string
{
    $frames = array_merge([['file' => $e->getFile(), 'line' => $e->getLine()]], $e->getTrace());
    foreach ($frames as $frame) {
        $file = $frame['file'] ?? '';
        if (preg_match('#[\\\\/]tests[\\\\/](Unit|Http)[\\\\/]#', $file) === 1) {
            return 'tests/' . str_replace('\\', '/', substr($file, strpos($file, 'tests') + 6)) . ':' . $frame['line'];
        }
    }
    return '';
}

function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        // @ : sous Windows, un fichier encore verrouille par le serveur ne doit pas faire echouer la suite.
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}
