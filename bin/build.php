<?php
declare(strict_types=1);

// Gera as páginas do site em public/ a partir de src/ e content/.
// Uso: php bin/build.php [pasta-de-saida]

require __DIR__ . '/../app/bootstrap.php';

$root = dirname(__DIR__);
$out = $argv[1] ?? $root . '/public';
if (!is_dir($out) && !mkdir($out, 0775, true)) {
    fwrite(STDERR, "Não foi possível criar $out\n");
    exit(1);
}

try {
    $written = (new Cobra\Site($root))->build($out);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
foreach ($written as $file => $bytes) {
    printf("%-18s %7d bytes\n", $file, $bytes);
}
