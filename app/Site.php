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

    public function renderPage(string $slug, ?array $content = null, ?\stdClass $item = null): string
    {
        $page = $this->page($slug);
        $content ??= $this->content();
        if ($item !== null) {
            $page = $this->pageForItem($page, $item, $content);
        }
        $ctx = [
            'site' => $content['site'] ?? new \stdClass(),
            'content' => $content,
            'page' => $page,
            'item' => $item,
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
     * Itens de uma página-modelo: page.json com "colecao" gera uma página por item
     * de content/<colecao>.json que tenha "url" (ex.: "solucoes-em-marketing-digital/branding/").
     * @return list<\stdClass>
     */
    public function items(\stdClass $page, array $content): array
    {
        if (empty($page->colecao)) {
            return [];
        }
        $list = $content[self::assertName($page->colecao)] ?? [];
        return array_values(array_filter(is_array($list) ? $list : [], fn($i) => $i instanceof \stdClass && !empty($i->url)));
    }

    /** Título, descrição e endereço da página de um item da coleção. */
    private function pageForItem(\stdClass $page, \stdClass $item, array $content): \stdClass
    {
        $p = clone $page;
        $seo = $item->seo ?? new \stdClass();
        $p->title = $seo->titulo ?? (($item->nome ?? '') . ' | ' . $page->title);
        $p->description = $seo->descricao ?? ($item->desc ?? $page->description ?? '');
        $p->output = self::outputFor($item->url);
        $p->url = self::assertPath($item->url);
        return $p;
    }

    /** "pasta/sub/" vira "pasta/sub/index.html". */
    public static function outputFor(string $url): string
    {
        $url = self::assertPath($url);
        return str_ends_with($url, '/') ? $url . 'index.html' : $url;
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
            $items = $this->items($page, $content);
            if ($items) {
                foreach ($items as $item) {
                    $out = self::outputFor($item->url);
                    $written[$out] = $this->write($outDir, $out, $this->renderPage($slug, $content, $item));
                }
                continue;
            }
            $out = self::assertPath($page->output);
            $written[$out] = $this->write($outDir, $out, $this->renderPage($slug, $content));
        }
        return $written;
    }

    private function write(string $outDir, string $rel, string $html): int
    {
        $file = $outDir . '/' . $rel;
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new \RuntimeException("Falha ao criar $dir");
        }
        $tmp = $file . '.tmp';
        if (file_put_contents($tmp, $html) === false || !rename($tmp, $file)) {
            throw new \RuntimeException("Falha ao gravar $file");
        }
        return strlen($html);
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

    /** Caminho relativo de saída: segmentos simples separados por "/" (sem .., sem barra inicial). */
    public static function assertPath(string $path): string
    {
        $trim = rtrim($path, '/');
        if ($trim === '' || str_starts_with($path, '/')) {
            throw new \InvalidArgumentException("Caminho inválido: $path");
        }
        foreach (explode('/', $trim) as $seg) {
            self::assertName($seg);
        }
        return $path;
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
