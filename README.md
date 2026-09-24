# Site Agência Cobra (cobra.art.br)

Site estático (HTML + CSS + JS puro), sem etapa de build. Pronto para deploy na Vercel.

## Estrutura

```
index.html        Home (hero com a cobra, soluções, portfólio, blog, rodapé)
sobre.html        Sobre a Cobra (manifesto, números, serviços, valores, equipe, localização)
solucao.html      Modelo único das páginas de solução. Cada solução é um hash:
                  solucao.html#solucao-branding, #solucao-sites-institucionais,
                  #solucao-e-commerce, #solucao-midias-sociais, #solucao-embalagens-e-tags,
                  #solucao-trafego-pago, #solucao-consultoria, #solucao-design-de-estampas,
                  #solucao-seo, #solucao-manutencao-de-sites
audiovisual.html  Solução Fotos & Vídeos (câmera 360° no scroll, galeria, sala de exibição,
                  marcas, páginas de cliente em audiovisual.html#cliente-...)
portfolio.html    Portfólio com filtros e páginas de serviço (#servico-...)
blog.html         Lista de posts e leitura de post (#post-...)
contato.html      Contato (formulário que monta a mensagem e abre o WhatsApp)
assets/           Imagens, quadros da câmera 360° e vídeo do hero (nomes por hash)
vendor/           GSAP 3.12.5 + ScrollTrigger e Lenis 1.1.13 (cópias locais)
vercel.json       URLs limpas (/sobre, /solucao...) e cache dos assets
```

Fontes: Montserrat e Sedgwick Ave Display via Google Fonts.

## Deploy na Vercel

Opção A, pelo GitHub:
1. Criar um repositório com o conteúdo desta pasta (a raiz do repo é esta pasta).
2. Na Vercel: Add New → Project → importar o repositório.
3. Framework Preset: **Other**. Build Command: vazio. Output Directory: vazio (raiz).
4. Deploy. Depois, em Settings → Domains, apontar o domínio.

Opção B, pela CLI:
```
npm i -g vercel
vercel          # primeiro deploy (preview)
vercel --prod   # produção
```

Para testar localmente: `npx serve .` (ou qualquer servidor estático) e abrir o endereço mostrado.

## Como o código está organizado

- Cada página é um HTML autocontido: CSS no `<style>` do topo e JS nos `<script>` do final.
- As 12 soluções (nome, descrição, entregas, imagem e clientes) ficam num array `SOLS` dentro de `solucao.html`. A home e o rodapé usam os mesmos nomes e links.
- O rodapé é igual em todas as páginas (classe `.hf`, CSS e JS próprios no fim de cada arquivo). Ao alterar, replicar em todos os HTML.
- Scroll: Lenis com `smoothWheel: false` (rolagem nativa do navegador). Evitar reativar o smooth wheel, porque causava a página "voltar" para a seção anterior em touchpad e mouse com rolagem suave.

## Regras de conteúdo (não mudar sem falar com a equipe)

- WhatsApp: 5547999150241, mensagem "Olá! Vim pelo site da Cobra e gostaria de conversar sobre um projeto."
- Depoimentos mostram só o nome da empresa (sem nome de pessoa, telefone, print ou áudio).
- As 3 fotos de gastronomia geradas por IA precisam continuar marcadas como "Exemplo IA".
- Números só com dados reais: 19 vídeos, 12 marcas, nota 5.0 no Google.
- Nos cards de soluções, não usar numeração.

## Pendências conhecidas

- **Clientes por solução:** só Gessner e Catarininho estão ligados a soluções (array `SOLS` em `solucao.html`, campo `clientes`). A seção "Marcas que confiaram" some quando a lista está vazia.
- **Textos "O que entregamos" e títulos em grafite** das páginas de solução: rascunho, aguardando revisão da redação.
- **Equipe** (`sobre.html`): 8 vagas com "Nome da pessoa" e "Foto em breve".
- **Posts do blog:** 6 textos de exemplo marcados como "Texto de exemplo".
- **Objeto 3D** das outras soluções no estilo da câmera do Audiovisual: ainda não definido.
- **Painel administrativo:** foi planejado para cPanel em PHP. Na Vercel não roda PHP; decidir entre um CMS headless (Sanity, Decap, etc.) ou manter o painel no cPanel.
- Links de Privacidade e Cookies no rodapé ainda apontam para o topo.
