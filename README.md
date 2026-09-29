# Site Agência Cobra (cobra.art.br)

Site estático (HTML + CSS + JS puro). As páginas finais ficam em `public/` e são **geradas** a partir das partes em `src/` e do conteúdo em `content/`. O mesmo gerador vai ser usado pelo painel administrativo (PHP, no cPanel).

## Estrutura

```
public/                 O que vai para o ar (Vercel e, depois, public_html no cPanel)
  *.html                Páginas geradas. Não editar à mão: rode o build.
  assets/               Imagens, quadros da câmera 360° e vídeo do hero
  vendor/               GSAP 3.12.5 + ScrollTrigger e Lenis 1.1.13 (cópias locais)
src/
  pages/<pagina>/       Uma pasta por página
    page.json           Título, descrição, menu ativo e a ordem das partes da página
    NN-*.html           Partes da página: seções, estilos e scripts
  partials/             Partes compartilhadas entre páginas (head, cabeçalho interno,
                        rodapé, bibliotecas, scripts comuns)
content/                Conteúdo editável
  site.json             WhatsApp, redes, menu, rodapé, números, dados da empresa
  solucoes.json         As 12 soluções (home, página de solução e rodapé usam esta lista)
  blog.json             Posts e imagens do blog
  portfolio.json        Serviços, cases e miniaturas do portfólio
  audiovisual.json      Vídeos, marcas e depoimentos do Audiovisual
app/                    Gerador em PHP (motor de templates e montagem das páginas)
bin/build.php           Gera public/*.html
tests/run.php           Testes do motor e do build
```

Páginas: `index` (home), `sobre`, `solucoes`, `solucao`, `audiovisual`, `portfolio`, `blog` e `contato`.

### Soluções nas URLs do site antigo

As soluções mantêm os endereços do cobra.art.br atual, para não perder SEO e indexação:

- `solucoes` gera `/solucoes-em-marketing-digital/` (lista de todas as soluções).
- `solucao` é um modelo com `"colecao": "solucoes"` no `page.json`. O gerador cria uma página para cada item de `content/solucoes.json` que tenha `url` (por exemplo, `solucoes-em-marketing-digital/branding/` gera `.../branding/index.html`, e `lojas-virtuais/` fica na raiz).
- Nessas páginas o texto sai pronto no HTML (não é montado por JS). Elas têm `<base href="/">`, canonical, Open Graph e dados estruturados (Service, BreadcrumbList e FAQPage).
- Campos de cada solução, além dos visuais: `seo.titulo`, `seo.descricao`, `h1`, `conteudo` (blocos com `titulo`, `paragrafos` e `lista`), `intro`, `olho`, `faq` (itens com `pergunta` e `resposta`, sendo a resposta uma lista de parágrafos), `etapas` (com `etapas_titulo`, `etapas_intro` e `preparar`), `relacionadas`, `cta` e `link_extra`. Os blocos de `conteudo` também aceitam `itens` (título e texto) e `links`.

## Como editar

1. Altere o conteúdo em `content/*.json` ou as partes em `src/`.
2. Gere as páginas: `php bin/build.php` (PHP 8.1 ou mais novo).
3. Confira localmente: `php -S localhost:8000 -t public` e abra http://localhost:8000.
4. Faça commit de tudo, incluindo `public/`. O GitHub Actions roda os testes e recusa o push se `public/` não estiver atualizado.

### Templates

As partes são HTML comum com marcações simples (não executam PHP):

| Marcação | Resultado |
|---|---|
| `{{ site.whatsapp.numero }}` | valor com escape de HTML |
| `{{{ valor }}}` | valor sem escape |
| `{{ texto \| url }}` | filtros: `url`, `js`, `json`, `upper`, `lower`, `count`, `mod N`, `mais N` (soma), `join` (lista em texto), `default "x"` |
| `{{#if cond}}…{{else}}…{{/if}}` | condição: `caminho`, `not caminho`, `a == b`, `a != b` |
| `{{#each lista as item}}…{{/each}}` | repetição, com `@index`, `@first`, `@last` |
| `{{> rodape }}` | inclui `src/partials/rodape.html` |

No template, `site` é o `content/site.json`, `content.<arquivo>` é qualquer arquivo de `content/`, e `page` é o `page.json` da página.

## Como o código está organizado

- Cada página continua autocontida depois de gerada: CSS no `<style>` do topo e JS nos `<script>` do final.
- Rodapé, cabeçalho das páginas internas, `<head>` e scripts comuns são partes únicas em `src/partials/`. Uma alteração ali vale para todas as páginas.
- Os links de WhatsApp saem de `site.json` (`whatsapp.numero`, `whatsapp.mensagem` e `whatsapp.mensagem_audiovisual`).
- Scroll: Lenis com `smoothWheel: false` (rolagem nativa do navegador). Evitar reativar o smooth wheel, porque causava a página "voltar" para a seção anterior em touchpad e mouse com rolagem suave.

## Deploy

**Vercel (prévia):** o `vercel.json` publica a pasta `public/` (Framework Preset **Other**, sem build). Cada commit no `main` atualiza a produção da Vercel.

**cPanel (destino final):** o painel administrativo e o deploy pelo Git Version Control do cPanel entram nas próximas etapas.

## Regras de conteúdo (não mudar sem falar com a equipe)

- WhatsApp: 5547999150241, mensagem "Olá! Vim pelo site da Cobra e gostaria de conversar sobre um projeto."
- Depoimentos mostram só o nome da empresa (sem nome de pessoa, telefone, print ou áudio).
- As 3 fotos de gastronomia geradas por IA precisam continuar marcadas como "Exemplo IA".
- Números só com dados reais. Os da seção "Em números" (Sobre e Soluções) ficam em `site.json` (`numeros.lista`): +500 clientes, 15+ anos, 650+ projetos e 3 MI+ em ativos gerenciados, os mesmos do site atual. Nota 5.0 no Google, 19 vídeos e 12 marcas também são dados confirmados.
- Nos cards de soluções, não usar numeração.

## Pendências conhecidas

- **Clientes por solução:** só Gessner e Catarininho estão ligados a soluções (`content/solucoes.json`, campo `clientes`). A seção "Marcas que confiaram" some quando a lista está vazia.
- **Conteúdo das soluções:** textos, perguntas frequentes, títulos e descrições vieram das páginas atuais do cobra.art.br. As listas "O que entregamos" e os títulos em grafite ainda são rascunho.
- **Posts do blog:** 6 textos de exemplo marcados como "Texto de exemplo".
- **Objeto 3D** das outras soluções no estilo da câmera do Audiovisual: ainda não definido.
- **Painel administrativo:** em PHP no cPanel, usando o gerador de `app/`. Próximas etapas: painel base (login, páginas e seções, mídia, configurações), blog/portfólio/soluções/clientes, SEO/GEO com IA e gerador de posts por tendências.
- Links de Privacidade e Cookies no rodapé ainda apontam para o topo.
