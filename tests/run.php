<?php
declare(strict_types=1);

// Testes do motor de templates e do gerador. Uso: php tests/run.php

require __DIR__ . '/../app/bootstrap.php';

use Cobra\Json;
use Cobra\Site;
use Cobra\Template;

$failures = 0;
function check(string $name, mixed $got, mixed $want): void
{
    global $failures;
    if ($got === $want) {
        echo "ok   $name\n";
        return;
    }
    $failures++;
    echo "FAIL $name\n  esperado: " . var_export($want, true) . "\n  obtido:   " . var_export($got, true) . "\n";
}

$partials = ['saudacao' => 'Olá, {{ nome }}!'];
$t = new Template(fn(string $n) => $partials[$n] ?? throw new RuntimeException("partial $n"));
$ctx = [
    'nome' => 'Cobra & Cia <b>',
    'html' => '<em>ok</em>',
    'lista' => ['a', 'b', 'c'],
    'obj' => json_decode('{"x": {"y": "z"}, "vazio": {}, "itens": [{"n": 1}, {"n": 2}]}'),
    'msg' => 'Olá! Tudo bem?',
];

check('escapa HTML', $t->render('{{ nome }}', $ctx), 'Cobra &amp; Cia &lt;b&gt;');
check('escapa aspas em atributo', $t->render('<a title="{{ q }}">', ['q' => 'a "b"']), '<a title="a &quot;b&quot;">');
check('sem escape com chaves triplas', $t->render('{{{ html }}}', $ctx), '<em>ok</em>');
check('caminho em objeto', $t->render('{{ obj.x.y }}', $ctx), 'z');
check('valor ausente vira vazio', $t->render('[{{ nada.aqui }}]', $ctx), '[]');
check('filtro url', $t->render('{{ msg | url }}', $ctx), 'Ol%C3%A1%21%20Tudo%20bem%3F');
check('filtro js', $t->render('{{{ obj.itens | js }}}', $ctx), '[{"n": 1}, {"n": 2}]');
check('filtro js objeto vazio', $t->render('{{{ obj.vazio | js }}}', $ctx), '{}');
check('each com @index', $t->render('{{#each lista as i}}{{ @index }}{{ i }}{{#if not @last}},{{/if}}{{/each}}', $ctx), '0a,1b,2c');
check('if/else', $t->render('{{#if nada}}s{{else}}n{{/if}}{{#if lista}}S{{/if}}', $ctx), 'nS');
check('comparação', $t->render('{{#each lista as i}}{{#if i == "b"}}[{{ i }}]{{/if}}{{/each}}', $ctx), '[b]');
check('filtro em condição', $t->render('{{#each lista as i}}{{#if @index | mod 2 == 0}}{{ i }}{{/if}}{{/each}}', $ctx), 'ac');
check('partial', $t->render('{{> saudacao }}', $ctx), 'Olá, Cobra &amp; Cia &lt;b&gt;!');
check('comentário some', $t->render('a{{! nota }}b', $ctx), 'ab');
check('chaves simples do CSS/JS passam intactas', $t->render('a{b{c}}d', $ctx), 'a{b{c}}d');

$erro = null;
try {
    $t->render('{{#if x}}sem fim', $ctx);
} catch (RuntimeException $e) {
    $erro = $e->getMessage();
}
check('bloco sem fechamento dá erro', $erro !== null, true);

check('json estilo das páginas', Json::encodePretty(json_decode('{"a": [1, "é/x"], "b": false}')), '{"a": [1, "é/x"], "b": false}');

$nomeInvalido = false;
try {
    Site::assertName('../etc/passwd');
} catch (InvalidArgumentException) {
    $nomeInvalido = true;
}
check('bloqueia caminho com ..', $nomeInvalido, true);

// O site inteiro precisa gerar sem erros.
$site = new Site(dirname(__DIR__));
$tmp = sys_get_temp_dir() . '/cobra-build-' . getmypid();
@mkdir($tmp);
$written = $site->build($tmp);
check('gera todas as páginas', count($written), count($site->pageSlugs()));
foreach ($written as $file => $bytes) {
    $html = file_get_contents("$tmp/$file");
    check("$file sem marcações de template sobrando", preg_match('/\{\{|\}\}\}/', $html) === 0, true);
    unlink("$tmp/$file");
}
rmdir($tmp);

echo $failures ? "\n$failures falha(s)\n" : "\nTudo certo.\n";
exit($failures ? 1 : 0);
