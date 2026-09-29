"""Collect real site data for the admin prototype and inject it into painel.src.html -> painel.html."""
import base64, html, json, os, re

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..'))
HERE = os.path.dirname(os.path.abspath(__file__))

def load(p):
    return json.load(open(os.path.join(ROOT, p), encoding='utf-8'))

site = load('content/site.json')
sols = load('content/solucoes.json')
blog = load('content/blog.json')
equipe = load('content/equipe.json')
av = load('content/audiovisual.json')
pf = load('content/portfolio.json')
sig = re.findall(r'<path pathLength="1" d="([^"]+)"', open(f'{ROOT}/src/partials/rodape.html', encoding='utf-8').read())[:2]

# images used by solutions and blog, embedded as data URIs
imgs = {}
def embed(path):
    if path not in imgs:
        raw = open(os.path.join(ROOT, 'public', path), 'rb').read()
        mime = {'webp': 'image/webp', 'png': 'image/png', 'svg': 'image/svg+xml'}.get(path.rsplit('.', 1)[-1], 'image/jpeg')
        imgs[path] = {'uri': f'data:{mime};base64,' + base64.b64encode(raw).decode(), 'kb': round(len(raw) / 1024)}
    return path
for s in sols:
    embed(s['img'])
for v in blog['imagens'].values():
    embed(v)
for v in av['videos']:
    if v.get('thumb'): embed(v['thumb'])
for m in av['marcas']:
    embed(m['src'])
for c in pf['cases']:
    if c.get('img'): embed(c['img'])
embed('assets/76faebec2532.webp')

def texts(src):
    s = re.sub(r'<(script|style|svg)[\s\S]*?</\1>', ' ', src)
    s = re.sub(r'\{\{[\s\S]*?\}\}', ' ', s)
    out = []
    for hm in re.finditer(r'<(h[1-3]|p|button|a|span class="lab")[^>]*>([\s\S]*?)</(h[1-3]|p|button|a|span)>', s):
        t = html.unescape(' '.join(re.sub(r'<[^>]+>', ' ', hm.group(2)).split()))
        t = re.sub(r'\s+([.,!?])', r'\1', t)
        if len(t) > 2 and not re.fullmatch(r'[\W\d_]+', t) and t not in out:
            out.append(t)
    for chunk in re.split(r'<[^>]+>', s):
        t = html.unescape(' '.join(chunk.split()))
        if len(t) > 2 and not re.fullmatch(r'[\W\d_]+', t) and t not in out:
            out.append(t)
    return out[:8]

pages = []
order = ['index', 'sobre', 'solucao', 'audiovisual', 'portfolio', 'blog', 'contato']
names = {'index': 'Home', 'sobre': 'Sobre', 'solucao': 'Soluções (modelo)', 'audiovisual': 'Audiovisual',
         'portfolio': 'Portfólio', 'blog': 'Blog', 'contato': 'Contato'}
for slug in order:
    pj = load(f'src/pages/{slug}/page.json')
    parts = []
    for p in pj['parts']:
        if 'partial' in p:
            src = open(f'{ROOT}/src/partials/{p["partial"]}.html', encoding='utf-8').read()
        else:
            src = open(f'{ROOT}/src/pages/{slug}/{p["file"]}', encoding='utf-8').read()
        parts.append({'label': p['label'], 'kind': p['kind'], 'shared': p.get('partial', ''),
                      'code': src[:4000] + ('\n…' if len(src) > 4000 else ''), 'lines': src.count('\n') + 1,
                      'fields': texts(src) if p['kind'] in ('section', 'header', 'footer') else []})
    pages.append({'slug': slug, 'name': names[slug], 'output': pj['output'], 'title': pj['title'],
                  'description': pj.get('description', ''), 'parts': parts})

# real SEO/GEO checks on the generated pages
audit = []
for slug in order:
    h = open(f'{ROOT}/public/{slug}.html', encoding='utf-8').read()
    title = html.unescape(re.search(r'<title>(.*?)</title>', h).group(1))
    body = h[h.find('<body'):]
    static_text = re.sub(r'<(script|style|svg)[\s\S]*?</\1>', ' ', body)
    words = len(re.sub(r'<[^>]+>', ' ', static_text).split())
    imgs_no_alt = len(re.findall(r'<img(?![^>]*\balt=)[^>]*>', body))
    h1 = len(re.findall(r'<h1[\s>]', body))
    checks = [
        ('Título entre 30 e 65 caracteres', 30 <= len(title) <= 65, f'{len(title)} caracteres'),
        ('Meta description', bool(re.search(r'<meta name="description"', h)), 'ausente' if not re.search(r'<meta name="description"', h) else 'ok'),
        ('Um único H1', h1 == 1, f'{h1} encontrado(s)'),
        ('Imagens com texto alternativo', imgs_no_alt == 0, f'{imgs_no_alt} sem alt' if imgs_no_alt else 'ok'),
        ('Open Graph (compartilhamento)', 'og:title' in h, 'ausente' if 'og:title' not in h else 'ok'),
        ('Dados estruturados (schema.org)', 'application/ld+json' in h, 'ausente' if 'application/ld+json' not in h else 'ok'),
        ('URL canônica', 'rel="canonical"' in h, 'ausente' if 'rel="canonical"' not in h else 'ok'),
        ('Texto legível sem JavaScript (IAs)', words >= 250, f'{words} palavras no HTML'),
    ]
    score = round(100 * sum(1 for c in checks if c[1]) / len(checks))
    audit.append({'slug': slug, 'name': names[slug], 'title': title, 'score': score,
                  'checks': [{'label': a, 'ok': b, 'detail': c} for a, b, c in checks]})

# ---- clients: merge cases, audiovisual items, logos and solution links into one register
def ckey(n):
    n = n.lower()
    for a, b in (('butzke móveis', 'butzke'), ('frigorífico gessner', 'gessner')):
        n = n.replace(a, b)
    return re.sub(r'[^a-z0-9]+', '-', n.encode('ascii', 'ignore').decode() if False else n).strip('-')
SKIP = {'Agência Cobra', 'Cafeteria', 'Gastronomia', 'Drinks & detalhes'}
SVC_MAP = {'performance': 'trafego-pago', 'conteudo': 'midias-sociais', 'tecnologia': 'sites-institucionais', 'branding': 'branding'}
clients = {}
def get(nome):
    k = ckey(nome)
    if k not in clients:
        clients[k] = {'id': k, 'nome': nome, 'segmento': '', 'cidade': '', 'site': '', 'logo': '', 'noPortfolio': False,
                      'desc': '', 'servicos': {}}
    return clients[k]
def svc(c, slug):
    return c['servicos'].setdefault(slug, {'desc': '', 'itens': []})
for x in pf['cases']:
    c = get(x['cliente']); c['noPortfolio'] = True; c['desc'] = x['desc']; c['tags'] = x['tags']
    for sv in x['svcs']:
        if sv in SVC_MAP:
            it = svc(c, SVC_MAP[sv])
            if sv == 'branding' and x.get('img'):
                it['itens'].append({'tipo': 'imagem', 'src': x['img'], 'titulo': 'Aplicação da marca', 'capa': True})
for sol in sols:
    for k in sol['clientes']:
        svc(get(k['nome']), sol['slug'])
for v in av['videos']:
    if not v.get('client') or v['client'] in SKIP:
        continue
    c = get(v['client'])
    slug = 'fotografia' if v['kind'] == 'photo' else 'producao-de-video'
    it = svc(c, slug)
    it['itens'].append({'tipo': v['kind'], 'yt': v.get('id', ''), 'src': v.get('thumb', ''), 'titulo': v['title'], 'legenda': v.get('label', ''), 'dur': v.get('dur', 0), 'capa': not it['itens']})
    c['noPortfolio'] = True
for m in av['marcas']:
    c = get(m['nome']); c['logo'] = m['src']; c['logoBranca'] = m['branca']
if 'gessner' in clients:
    svc(clients['gessner'], 'sites-institucionais')['itens'].append({'tipo': 'site', 'url': '', 'titulo': 'Site institucional', 'capa': True})
for c in clients.values():
    if c['logo']:
        embed(c['logo'])
    for s_ in c['servicos'].values():
        for it in s_['itens']:
            if it.get('src'):
                embed(it['src'])
clientes = sorted(clients.values(), key=lambda c: (-len(c['servicos']), c['nome']))

data = {
    'site': site, 'solucoes': sols, 'posts': blog['posts'], 'blogImg': blog['imagens'], 'equipe': equipe,
    'pages': pages, 'audit': audit, 'imgs': imgs, 'sig': sig, 'hero': 'assets/76faebec2532.webp',
    'videos': av['videos'], 'marcas': av['marcas'], 'depoimentos': av['depoimentos'],
    'clientes': clientes,
    'servicos': [{k: x[k] for k in ('id', 'nome', 'curto', 'lead', 'deliv')} for x in pf['servicos']], 'cases': pf['cases'],
    'siteFiles': [
        {'label': 'sitemap.xml', 'ok': os.path.exists(f'{ROOT}/public/sitemap.xml')},
        {'label': 'robots.txt', 'ok': os.path.exists(f'{ROOT}/public/robots.txt')},
        {'label': 'llms.txt (guia do site para IAs)', 'ok': os.path.exists(f'{ROOT}/public/llms.txt')},
    ],
}
tpl = open(os.path.join(HERE, 'painel.src.html'), encoding='utf-8').read()
out = tpl.replace('/*DATA*/null', json.dumps(data, ensure_ascii=False).replace('</', '<\\/'))
open(os.path.join(HERE, 'painel.html'), 'w', encoding='utf-8').write(out)
print('ok', len(out) // 1024, 'KB', [ (a['slug'], a['score']) for a in audit])
