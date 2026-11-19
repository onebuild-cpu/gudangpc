<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'bin/migrate.php is a CLI-only script.' . PHP_EOL);
    exit(1);
}

require_once __DIR__ . '/../app/Core/Autoloader.php';
App\Core\Autoloader::register();
App\Core\Autoloader::addNamespace('App', __DIR__ . '/../app');

App\Core\Dotenv::load();

$dbHost = App\Core\Dotenv::get('DB_HOST', '127.0.0.1');
$dbName = App\Core\Dotenv::get('DB_NAME', 'gudangpc');
$dbUser = App\Core\Dotenv::get('DB_USER', 'root');
$dbPass = App\Core\Dotenv::get('DB_PASS', '');
$dbCharset = App\Core\Dotenv::get('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * Return migration files numbered 026 or higher, in numeric order.
 */
function migrationFiles(string $directory): array
{
    $files = [];
    $entries = scandir($directory);
    if ($entries === false) {
        return $files;
    }

    foreach ($entries as $entry) {
        $matches = [];
        if (preg_match('/^(\d{3})_[a-z0-9_]+\.sql$/', $entry, $matches) !== 1) {
            continue;
        }
        $number = (int) $matches[1];
        if ($number < 26) {
            continue;
        }
        $files[] = [
            'number' => $number,
            'filename' => $entry,
            'path' => $directory . '/' . $entry,
        ];
    }

    usort($files, function (array $a, array $b): int {
        if ($a['number'] === $b['number']) {
            return 0;
        }
        if ($a['number'] < $b['number']) {
            return -1;
        }
        return 1;
    });

    return $files;
}

/**
 * Create the migrations tracking table if it does not exist.
 */
function ensureMigrationsTable(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        filename varchar(190) NOT NULL,
        applied_at timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

/**
 * Return applied migration filenames keyed by filename.
 */
function appliedFilenames(PDO $pdo): array
{
    $rows = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_ASSOC);
    $applied = [];
    foreach ($rows as $row) {
        $applied[(string) $row['filename']] = true;
    }
    return $applied;
}

/**
 * Split a migration file into statements on semicolons at end of line.
 */
function statementList(string $sql): array
{
    $statements = [];
    $buffer = [];
    $lines = preg_split('/\r\n|\r|\n/', $sql);
    if ($lines === false) {
        return $statements;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0) {
            continue;
        }
        $buffer[] = $line;
        if (substr(rtrim($line), -1) === ';') {
            $statement = trim(implode("\n", $buffer));
            $buffer = [];
            if (trim(str_replace(';', '', $statement)) !== '') {
                $statements[] = $statement;
            }
        }
    }

    $tail = trim(implode("\n", $buffer));
    if (trim(str_replace(';', '', $tail)) !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

/**
 * Ask the operator to type YES before a write command.
 */
function confirmWrite(string $dbName, bool $assumeYes): bool
{
    echo 'Database: ' . $dbName . PHP_EOL;
    if ($assumeYes) {
        return true;
    }

    echo 'Type YES to continue: ';
    $answer = fgets(STDIN);
    if ($answer === false) {
        return false;
    }

    return trim($answer) === 'YES';
}

$command = '';
if (isset($argv[1])) {
    $command = $argv[1];
}
$assumeYes = false;
if (isset($argv) && is_array($argv)) {
    foreach ($argv as $arg) {
        if ($arg === '--yes') {
            $assumeYes = true;
        }
    }
}

if ($command !== 'status' && $command !== 'up' && $command !== 'baseline') {
    echo 'Usage: php bin/migrate.php status|up|baseline [--yes]' . PHP_EOL;
    exit(1);
}

$migrationsDir = __DIR__ . '/../database/migrations';
ensureMigrationsTable($pdo);
$files = migrationFiles($migrationsDir);
$applied = appliedFilenames($pdo);

if ($command === 'status') {
    $appliedCount = 0;
    $pendingCount = 0;
    foreach ($files as $file) {
        $state = 'pending';
        if (isset($applied[$file['filename']])) {
            $state = 'applied';
        }
        if ($state === 'applied') {
            $appliedCount++;
        } else {
            $pendingCount++;
        }
        echo $file['filename'] . ' - ' . $state . PHP_EOL;
    }
    echo 'Applied: ' . $appliedCount . PHP_EOL;
    echo 'Pending: ' . $pendingCount . PHP_EOL;
    exit(0);
}

if ($command === 'up') {
    if (!confirmWrite((string) $dbName, $assumeYes)) {
        echo 'Aborted.' . PHP_EOL;
        exit(1);
    }

    $updated = 0;
    foreach ($files as $file) {
        if (isset($applied[$file['filename']])) {
            continue;
        }

        $sql = file_get_contents($file['path']);
        if ($sql === false) {
            echo 'Could not read migration file: ' . $file['filename'] . PHP_EOL;
            exit(1);
        }

        $statements = statementList($sql);
        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                echo 'FAILED migration file: ' . $file['filename'] . PHP_EOL;
                echo 'Statement: ' . $statement . PHP_EOL;
                echo 'Database error: ' . $e->getMessage() . PHP_EOL;
                echo 'MySQL DDL cannot be rolled back; fix the file and repair the schema by hand.' . PHP_EOL;
                exit(1);
            }
        }

        try {
            $record = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
            $record->execute([$file['filename']]);
        } catch (PDOException $e) {
            echo 'Migration ran but could not be recorded: ' . $file['filename'] . PHP_EOL;
            echo 'Database error: ' . $e->getMessage() . PHP_EOL;
            exit(1);
        }

        echo 'Applied: ' . $file['filename'] . PHP_EOL;
        $updated++;
    }

    echo 'Applied ' . $updated . ' migration file(s).' . PHP_EOL;
    exit(0);
}

if (count($applied) > 0) {
    echo 'baseline is only allowed when schema_migrations is empty.' . PHP_EOL;
    exit(1);
}

if (!confirmWrite((string) $dbName, $assumeYes)) {
    echo 'Aborted.' . PHP_EOL;
    exit(1);
}

$marked = 0;
foreach ($files as $file) {
    try {
        $record = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
        $record->execute([$file['filename']]);
    } catch (PDOException $e) {
        echo 'Could not mark: ' . $file['filename'] . PHP_EOL;
        echo 'Database error: ' . $e->getMessage() . PHP_EOL;
        exit(1);
    }
    echo 'Marked: ' . $file['filename'] . PHP_EOL;
    $marked++;
}

echo 'Marked ' . $marked . ' migration file(s) as applied.' . PHP_EOL;
exit(0);
