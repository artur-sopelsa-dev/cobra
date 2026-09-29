<?php
declare(strict_types=1);

namespace Cobra;

/**
 * Monta as páginas estáticas a partir de:
 *   src/pages/<pagina>/page.json   metadados e ordem das partes
 *   src/pages/<pagina>/*.html      partes da página (seções, estilos, scripts)
 *   src/partials/*.html            partes compartilhadas entre páginas
 *   content/*.json                 conteúdo editável (configurações, soluções, posts...)
 */
final class Site
{
    private Template $tpl;

    public function __construct(
        private string $root,
    ) {
        $this->tpl = new Template(fn(string $name) => $this->partialSource($name));
    }

    public function pagesDir(): string
    {
        return $this->root . '/src/pages';
    }

    public function partialsDir(): string
    {
        return $this->root . '/src/partials';
    }

    public function contentDir(): string
    {
        return $this->root . '/content';
    }

    /** @return list<string> */
    public function pageSlugs(): array
    {
        $slugs = [];
        foreach (glob($this->pagesDir() . '/*/page.json') ?: [] as $f) {
            $slugs[] = basename(dirname($f));
        }
        sort($slugs);
        return $slugs;
    }

    public function page(string $slug): \stdClass
    {
        self::assertName($slug);
        return Json::read($this->pagesDir() . "/$slug/page.json");
    }

    /** Conteúdo de content/*.json, indexado pelo nome do arquivo. */
    public function content(): array
    {
        $data = [];
        foreach (glob($this->contentDir() . '/*.json') ?: [] as $f) {
            $data[basename($f, '.json')] = Json::read($f);
        }
        return $data;
    }

    public function renderPage(string $slug, ?array $content = null): string
    {
        $page = $this->page($slug);
        $content ??= $this->content();
        $ctx = [
            'site' => $content['site'] ?? new \stdClass(),
            'content' => $content,
            'page' => $page,
        ];
        $html = '';
        foreach ($page->parts as $part) {
            if (!empty($part->hidden)) {
                continue;
            }
            $source = isset($part->partial)
                ? $this->partialSource($part->partial)
                : $this->read($this->pagesDir() . "/$slug/" . self::assertName($part->file));
            $html .= $this->tpl->render($source, $ctx);
        }
        return $html;
    }

    /**
     * Gera todas as páginas em $outDir.
     * @return array<string, int> arquivo => bytes
     */
    public function build(string $outDir): array
    {
        $content = $this->content();
        $written = [];
        foreach ($this->pageSlugs() as $slug) {
            $page = $this->page($slug);
            $file = $outDir . '/' . self::assertName($page->output);
            $html = $this->renderPage($slug, $content);
            $tmp = $file . '.tmp';
            if (file_put_contents($tmp, $html) === false || !rename($tmp, $file)) {
                throw new \RuntimeException("Falha ao gravar $file");
            }
            $written[$page->output] = strlen($html);
        }
        return $written;
    }

    private function partialSource(string $name): string
    {
        return $this->read($this->partialsDir() . '/' . self::assertName($name) . '.html');
    }

    private function read(string $file): string
    {
        $s = @file_get_contents($file);
        if ($s === false) {
            throw new \RuntimeException("Arquivo não encontrado: $file");
        }
        return $s;
    }

    /** Aceita só nomes simples de arquivo (sem barras nem ..). */
    public static function assertName(string $name): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $name) || str_contains($name, '..')) {
            throw new \InvalidArgumentException("Nome inválido: $name");
        }
        return $name;
    }
}
