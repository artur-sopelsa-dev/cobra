<?php
declare(strict_types=1);

namespace Cobra;

/**
 * Motor de templates simples e seguro (não executa PHP), usado nas partes das páginas.
 *
 *   {{ caminho.do.valor }}          valor escapado para HTML (serve em texto e atributos)
 *   {{{ caminho }}}                 valor sem escape (HTML confiável)
 *   {{ valor | url }}               filtros: url, js, json, upper, lower, mod N, default "texto"
 *   {{#if expr}} ... {{else}} ... {{/if}}      expr: caminho, not caminho, a == b, a != b (aceitam filtros)
 *   {{#each lista as item}} ... {{/each}}      dentro: item, @index, @first, @last
 *   {{> nome-do-partial }}          inclui src/partials/<nome>.html
 *   {{! comentário }}
 */
final class Template
{
    /** @var callable(string): string */
    private $loadPartial;

    /** @var array<string, array> */
    private array $cache = [];

    public function __construct(callable $loadPartial)
    {
        $this->loadPartial = $loadPartial;
    }

    public function render(string $source, array $context): string
    {
        return $this->renderNodes($this->parse($source), $context);
    }

    // ---------------------------------------------------------------- parser

    private function parse(string $source): array
    {
        $key = md5($source);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $root = ['type' => 'root', 'children' => []];
        $stack = [&$root];
        $offset = 0;
        $re = '/\{\{\{\s*(.+?)\s*\}\}\}|\{\{\s*(.+?)\s*\}\}/s';
        while (preg_match($re, $source, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $top = &$stack[count($stack) - 1];
            if ($start > $offset) {
                $top['children'][] = ['type' => 'text', 'text' => substr($source, $offset, $start - $offset)];
            }
            $offset = $start + strlen($m[0][0]);
            if (isset($m[1]) && $m[1][1] >= 0 && $m[1][0] !== '') {
                $top['children'][] = ['type' => 'out', 'expr' => $m[1][0], 'raw' => true];
                unset($top);
                continue;
            }
            $tag = $m[2][0];
            $c = $tag[0];
            if ($c === '!') {
                // comentário
            } elseif ($c === '>') {
                $top['children'][] = ['type' => 'partial', 'name' => trim(substr($tag, 1))];
            } elseif (str_starts_with($tag, '#if ')) {
                $node = ['type' => 'if', 'cond' => trim(substr($tag, 4)), 'then' => [], 'else' => [], 'children' => [], 'inElse' => false];
                $top['children'][] = $node;
                $stack[] = &$top['children'][count($top['children']) - 1];
            } elseif (str_starts_with($tag, '#each ')) {
                if (!preg_match('/^#each\s+(\S+)\s+as\s+(\w+)$/', $tag, $em)) {
                    throw new \RuntimeException("each inválido: {{{$tag}}}");
                }
                $node = ['type' => 'each', 'path' => $em[1], 'as' => $em[2], 'children' => []];
                $top['children'][] = $node;
                $stack[] = &$top['children'][count($top['children']) - 1];
            } elseif ($tag === 'else') {
                if (($top['type'] ?? '') !== 'if' || $top['inElse']) {
                    throw new \RuntimeException('{{else}} fora de {{#if}}');
                }
                $top['then'] = $top['children'];
                $top['children'] = [];
                $top['inElse'] = true;
            } elseif ($tag === '/if' || $tag === '/each') {
                $want = substr($tag, 1);
                if (($top['type'] ?? '') !== $want) {
                    throw new \RuntimeException("{{{$tag}}} sem abertura correspondente");
                }
                if ($want === 'if') {
                    if ($top['inElse']) {
                        $top['else'] = $top['children'];
                    } else {
                        $top['then'] = $top['children'];
                    }
                    $top['children'] = [];
                }
                array_pop($stack);
            } else {
                $top['children'][] = ['type' => 'out', 'expr' => $tag, 'raw' => false];
            }
            unset($top);
        }
        if (count($stack) !== 1) {
            throw new \RuntimeException('Bloco {{#if}}/{{#each}} sem fechamento');
        }
        if ($offset < strlen($source)) {
            $root['children'][] = ['type' => 'text', 'text' => substr($source, $offset)];
        }
        return $this->cache[$key] = $root['children'];
    }

    // -------------------------------------------------------------- renderer

    private function renderNodes(array $nodes, array $ctx): string
    {
        $out = '';
        foreach ($nodes as $n) {
            switch ($n['type']) {
                case 'text':
                    $out .= $n['text'];
                    break;
                case 'out':
                    $v = $this->evalOutput($n['expr'], $ctx);
                    $out .= $n['raw'] ? $v : htmlspecialchars($v, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8', false);
                    break;
                case 'partial':
                    $out .= $this->render(($this->loadPartial)($n['name']), $ctx);
                    break;
                case 'if':
                    $out .= $this->renderNodes($this->truthy($this->evalCond($n['cond'], $ctx)) ? $n['then'] : $n['else'], $ctx);
                    break;
                case 'each':
                    $list = $this->lookup($n['path'], $ctx);
                    if ($list instanceof \stdClass) {
                        $list = get_object_vars($list);
                    }
                    if (!is_array($list)) {
                        break;
                    }
                    $i = 0;
                    $last = count($list) - 1;
                    foreach ($list as $key => $item) {
                        $local = $ctx;
                        $local[$n['as']] = $item;
                        $local['@index'] = $i;
                        $local['@key'] = $key;
                        $local['@first'] = $i === 0;
                        $local['@last'] = $i === $last;
                        $out .= $this->renderNodes($n['children'], $local);
                        $i++;
                    }
                    break;
            }
        }
        return $out;
    }

    private function evalOutput(string $expr, array $ctx): string
    {
        return $this->str($this->expr($expr, $ctx));
    }

    /** Valor seguido de filtros: caminho | filtro | filtro argumento */
    private function expr(string $expr, array $ctx): mixed
    {
        $pieces = array_map('trim', explode('|', $expr));
        $value = $this->value(array_shift($pieces), $ctx);
        foreach ($pieces as $f) {
            [$name, $arg] = array_pad(preg_split('/\s+/', $f, 2), 2, null);
            $value = match ($name) {
                'url' => rawurlencode($this->str($value)),
                'js' => Json::encodePretty($value),
                'json' => Json::encode($value),
                'upper' => mb_strtoupper($this->str($value)),
                'lower' => mb_strtolower($this->str($value)),
                'mod' => (int) $value % max(1, (int) $arg),
                'default' => $this->truthy($value) ? $value : $this->value((string) $arg, $ctx),
                default => throw new \RuntimeException("Filtro desconhecido: $name"),
            };
        }
        return $value;
    }

    private function evalCond(string $cond, array $ctx): mixed
    {
        if (str_starts_with($cond, 'not ')) {
            return !$this->truthy($this->evalCond(substr($cond, 4), $ctx));
        }
        if (preg_match('/^(.+?)\s*(==|!=)\s*(.+)$/', $cond, $m)) {
            $eq = $this->str($this->expr($m[1], $ctx)) === $this->str($this->expr($m[3], $ctx));
            return $m[2] === '==' ? $eq : !$eq;
        }
        return $this->expr($cond, $ctx);
    }

    private function value(string $token, array $ctx): mixed
    {
        $token = trim($token);
        if (preg_match('/^"(.*)"$/s', $token, $m) || preg_match("/^'(.*)'$/s", $token, $m)) {
            return $m[1];
        }
        if (is_numeric($token)) {
            return $token + 0;
        }
        return $this->lookup($token, $ctx);
    }

    private function lookup(string $path, array $ctx): mixed
    {
        $cur = $ctx;
        foreach (explode('.', $path) as $seg) {
            if (is_array($cur) && array_key_exists($seg, $cur)) {
                $cur = $cur[$seg];
            } elseif ($cur instanceof \stdClass && property_exists($cur, $seg)) {
                $cur = $cur->$seg;
            } else {
                return null;
            }
        }
        return $cur;
    }

    private function truthy(mixed $v): bool
    {
        if ($v instanceof \stdClass) {
            return (bool) get_object_vars($v);
        }
        return (bool) $v;
    }

    private function str(mixed $v): string
    {
        if ($v === null || $v === false) {
            return '';
        }
        if ($v === true) {
            return '1';
        }
        if (is_array($v) || is_object($v)) {
            return Json::encode($v);
        }
        return (string) $v;
    }
}
