<?php
/**
 * Rule checker for GudangPC.
 * Usage: php bin/check_rules.php [path ...]
 * Default paths: app views config public/index.php bin
 *
 * Flags every use of:
 *   - ternary operator        ( cond ? a : b )
 *   - short ternary           ( a ?: b )
 *   - null coalescing         ( a ?? b )
 *   - null coalescing assign  ( a ??= b )
 * Nullable types (?string) and nullsafe (?->) are NOT flagged.
 * Exit code 0 = clean, 1 = violations found.
 */

// Set to false if '??' should be allowed. Ternary and '?:' are always banned.
$banNullCoalescing = true;

$root = dirname(__DIR__);
$targets = [];
if ($argc > 1) {
    for ($i = 1; $i < $argc; $i++) {
        $targets[] = $argv[$i];
    }
} else {
    $targets = ['app', 'views', 'config', 'public/index.php', 'bin'];
}

$files = [];
foreach ($targets as $target) {
    $full = $target;
    if (!file_exists($full)) {
        $full = $root . '/' . $target;
    }
    if (is_file($full)) {
        $files[] = $full;
        continue;
    }
    if (is_dir($full)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
                $files[] = $file->getPathname();
            }
        }
    }
}

// Token types that can END an expression. A '?' right after one of these is a ternary.
$expressionEnders = [T_VARIABLE, T_STRING, T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];
$expressionEnderChars = [')', ']'];

$violations = 0;
foreach ($files as $file) {
    if (basename($file) === 'check_rules.php') {
        continue;
    }
    $code = file_get_contents($file);
    $tokens = token_get_all($code);
    $count = count($tokens);
    $previous = null;
    $line = 1;

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        $type = null;
        $text = $token;
        if (is_array($token)) {
            $type = $token[0];
            $text = $token[1];
            $line = $token[2];
        }

        if ($type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT) {
            continue;
        }

        $found = '';
        if ($banNullCoalescing && $type === T_COALESCE) {
            $found = '?? (null coalescing)';
        }
        if ($banNullCoalescing && $type === T_COALESCE_EQUAL) {
            $found = '??= (null coalescing assign)';
        }
        if ($type === null && $text === '?') {
            $isTernary = false;
            if ($previous !== null) {
                if (in_array($previous['type'], $expressionEnders, true)) {
                    $isTernary = true;
                }
                if ($previous['type'] === null && in_array($previous['text'], $expressionEnderChars, true)) {
                    $isTernary = true;
                }
            }
            if ($isTernary) {
                $found = '? : (ternary)';
            }
        }

        if ($found !== '') {
            echo $file . ':' . $line . '  ' . $found . "\n";
            $violations++;
        }

        $previous = ['type' => $type, 'text' => $text];
    }
}

if ($violations === 0) {
    echo "OK: no ternary or null-coalescing syntax found.\n";
    exit(0);
}
echo "\nFAILED: " . $violations . " violation(s).\n";
exit(1);
