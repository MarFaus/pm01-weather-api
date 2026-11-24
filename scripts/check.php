<?php

/*
 * Помощник для scripts/start.ps1.
 *
 *   php scripts/check.php php           — версия PHP >= 8.3 и нужные расширения (код 0 = всё есть)
 *   php scripts/check.php ini <php.ini> — включить нужные расширения в переносном PHP
 */

const NEED_EXT = ['openssl', 'mbstring', 'pdo_sqlite', 'sqlite3', 'fileinfo', 'curl', 'zip', 'tokenizer', 'ctype', 'dom'];

$cmd = $argv[1] ?? '';

switch ($cmd) {
    case 'php':
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            fwrite(STDERR, 'PHP '.PHP_VERSION.' слишком старый, нужен 8.3+'.PHP_EOL);
            exit(1);
        }
        $missing = array_values(array_filter(NEED_EXT, fn ($e) => ! extension_loaded($e)));
        if ($missing) {
            fwrite(STDERR, 'PHP '.PHP_VERSION.': нет расширений '.implode(', ', $missing).PHP_EOL);
            exit(1);
        }
        echo PHP_VERSION;
        exit(0);

    case 'ini':
        $file = $argv[2];
        $ini = file_get_contents($file);
        $ini = preg_replace('/^;\s*extension_dir\s*=\s*"ext"/m', 'extension_dir = "ext"', $ini);
        foreach (NEED_EXT as $ext) {
            $ini = preg_replace('/^;\s*extension\s*=\s*'.$ext.'\s*$/m', 'extension='.$ext, $ini);
        }
        file_put_contents($file, $ini);
        exit(0);

    default:
        fwrite(STDERR, "Неизвестная команда: {$cmd}".PHP_EOL);
        exit(2);
}
