<?php
declare(strict_types=1);

namespace Cobra;

final class Json
{
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    /** Lê um arquivo JSON mantendo objetos como stdClass (distingue {} de []). */
    public static function read(string $file): mixed
    {
        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new \RuntimeException("Não foi possível ler $file");
        }
        return json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
    }

    /** Grava JSON legível (indentado), usado nos arquivos de conteúdo. */
    public static function write(string $file, mixed $data): void
    {
        $json = json_encode($data, self::FLAGS | JSON_PRETTY_PRINT) . "\n";
        $json = preg_replace_callback('/^ +/m', fn($m) => str_repeat(' ', intdiv(strlen($m[0]), 2)), $json);
        if (file_put_contents($file, $json, LOCK_EX) === false) {
            throw new \RuntimeException("Não foi possível gravar $file");
        }
    }

    /** JSON compacto: {"a":1,"b":[2,3]} */
    public static function encode(mixed $data): string
    {
        return json_encode($data, self::FLAGS);
    }

    /** JSON em uma linha com espaços após vírgula e dois-pontos: {"a": 1, "b": [2, 3]} (formato usado nos scripts das páginas). */
    public static function encodePretty(mixed $data): string
    {
        if ($data instanceof \stdClass) {
            $data = get_object_vars($data);
            if (!$data) {
                return '{}';
            }
            $out = [];
            foreach ($data as $k => $v) {
                $out[] = self::encode((string) $k) . ': ' . self::encodePretty($v);
            }
            return '{' . implode(', ', $out) . '}';
        }
        if (is_array($data)) {
            if ($data && !array_is_list($data)) {
                return self::encodePretty((object) $data);
            }
            return '[' . implode(', ', array_map([self::class, 'encodePretty'], $data)) . ']';
        }
        return self::encode($data);
    }
}
