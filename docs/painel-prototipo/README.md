# Protótipo do painel administrativo

Protótipo navegável do painel, usado para definir as telas e os fluxos antes de construir o painel de verdade (PHP no cPanel). Nada nele altera o site.

- Versão publicada: https://claude.ai/artifact/TPbEDMQzorRcu5gxykN7ny (acesso restrito à conta dona do link)
- `painel.src.html`: telas, estilos e comportamento do protótipo
- `gerar.py`: junta ao protótipo os dados reais do site (`content/`, `src/pages/`, imagens de `public/assets/`) e a auditoria de SEO feita sobre `public/*.html`

Para gerar e abrir localmente:

```
python3 docs/painel-prototipo/gerar.py
# abre docs/painel-prototipo/painel.html no navegador
```

## O que o protótipo cobre

- Login com verificação em duas etapas
- Início: pendências, pautas em alta, atividade
- Páginas: seções (ordenar, esconder, editar campos ou código, adicionar a partir de modelos), título e descrição com prévia do Google e de resposta de IA
- Soluções, Equipe, Mídia (texto alternativo com IA)
- Clientes: cadastro, serviços contratados e materiais por serviço (vídeos, fotos, site, arquivos)
- Portfólio: montado sozinho a partir dos clientes, por serviço ou por cliente, com prévia do que o visitante vê
- Audiovisual: vídeos, marcas e depoimentos
- Blog: editor por blocos com nota de SEO, rascunhos da IA para aprovação
- Pautas em alta e SEO/GEO com correções sugeridas pela IA
- Cobra IA (Ctrl/⌘ + K), Configurações (geral, menu e rodapé, IA, usuários, histórico) e fluxo de publicação

Pautas, respostas da IA, rascunhos e convites são exemplos.
