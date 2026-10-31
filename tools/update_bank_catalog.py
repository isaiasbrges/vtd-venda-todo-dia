"""Refresh public BCB STR catalogue and verified bank icons. No customer data is sent."""
import csv, io, json, re, html, urllib.request, urllib.parse, concurrent.futures, xml.etree.ElementTree as ET
from datetime import date
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
SOURCE='https://www.bcb.gov.br/content/estabilidadefinanceira/str1/ParticipantesSTR.csv'
def get(url):
    req=urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0 VTD asset preparation'})
    with urllib.request.urlopen(req,timeout=12) as r: return r.read(),r.headers.get('Content-Type',''),r.url
raw,_,_=get(SOURCE)
text=raw.decode('utf-8-sig',errors='replace')
if '\ufffd' in text: text=raw.decode('cp1252')
rows=list(csv.DictReader(io.StringIO(text),delimiter=','))
if len(rows)<20: rows=list(csv.DictReader(io.StringIO(text),delimiter=';'))
print('BCB columns:',list(rows[0]),'rows:',len(rows))
# Domains belong to official websites; icon links are extracted from their HTML.
brands={
'00000000':('Banco do Brasil','https://www.bb.com.br'),
'60701190':('Itaú','https://www.itau.com.br'),
'60746948':('Bradesco','https://banco.bradesco'),
'90400888':('Santander','https://www.santander.com.br'),
'00360305':('Caixa','https://www.caixa.gov.br'),
'18236120':('Nubank','https://nubank.com.br'),
'00416968':('Inter','https://inter.co'),
'10573521':('Mercado Pago','https://www.mercadopago.com.br'),
'22896431':('PicPay','https://picpay.com'),
'20855875':('Neon','https://neon.com.br'),
'31872495':('C6 Bank','https://www.c6bank.com.br'),
'02038232':('Sicoob','https://www.sicoob.com.br'),
'01181521':('Sicredi','https://www.sicredi.com.br'),
'92702067':('Banrisul','https://www.banrisul.com.br'),
'00000208':('BRB','https://brb.com.br'),
'92894922':('Banco Original','https://www.original.com.br')
}
out=ROOT/'assets'/'banks';out.mkdir(parents=True,exist_ok=True)
def icon(item):
    ispb,(name,url)=item
    try:
        page,_,final=get(url); page=page.decode('utf-8',errors='ignore')
        links=re.findall(r'<link\b[^>]*>',page,re.I)
        candidates=[]
        for tag in links:
            rel=re.search(r'\brel=[\'\"]([^\'\"]+)',tag,re.I)
            href=re.search(r'\bhref=[\'\"]([^\'\"]+)',tag,re.I)
            if rel and href and 'icon' in rel.group(1).lower(): candidates.append(urllib.parse.urljoin(final,html.unescape(href.group(1))))
        candidates.extend([urllib.parse.urljoin(final, '/favicon.ico'), urllib.parse.urljoin(final, '/favicon.png')])
        for address in list(dict.fromkeys(candidates))[:6]:
            try:
                data,mime,_=get(address)
                ext='png' if data.startswith(b'\x89PNG') else 'ico' if data.startswith(b'\x00\x00\x01\x00') else 'jpg' if data.startswith(b'\xff\xd8') else None
                if ext is None and b'<svg' in data[:2000] and len(data)<500000:
                    svg=ET.fromstring(data)
                    allowed={'svg','g','path','defs','image','symbol','linearGradient','radialGradient','stop','rect','circle','ellipse','polygon','polyline','line','use','title','desc','clipPath','mask','style','text'}
                    if svg.tag.split('}')[-1]!='svg': continue
                    for parent in list(svg.iter()):
                        for child in list(parent):
                            if child.tag.split('}')[-1] not in allowed or (child.tag.split('}')[-1]=='style' and re.search(r'@import|https?:|//',child.text or '')): parent.remove(child)
                        for attr,value in list(parent.attrib.items()):
                            if attr.lower().startswith('on') or ('href' in attr and not value.startswith('#') and not re.fullmatch(r'data:image/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=\s]+',value)) or re.search(r'url\(\s*[\'\"]?(?!#)',value): del parent.attrib[attr]
                    ET.register_namespace('','http://www.w3.org/2000/svg')
                    data=ET.tostring(svg,encoding='utf-8'); ext='svg'
                if ext and len(data)<500000:
                    dest=out/(ispb+'.'+ext);dest.write_bytes(data);return ispb,{'logo':'assets/banks/'+dest.name,'logo_source':address,'website':url,'display_name':name}
            except Exception: pass
    except Exception: pass
    return ispb,{'logo':None,'website':url,'display_name':name}
icons=dict(concurrent.futures.ThreadPoolExecutor(max_workers=6).map(icon,brands.items()))
banks=[]
for r in rows:
    values=list(r.values())
    ispb=str(r.get('ISPB',values[0])).strip().zfill(8)
    name=r.get('Nome_Extenso',r.get('Nome Extenso',values[5] if len(values)>5 else values[1])).strip()
    short=r.get('Nome_Reduzido',r.get('Nome Reduzido',values[1])).strip()
    code=r.get('Numero_Codigo',r.get('Número-Código',values[2] if len(values)>2 else '')).strip()
    if not ispb or ispb in ('00038121','00038166'): continue
    b={'id':ispb,'name':name,'aliases':[short,code],'logo':None}
    if ispb in icons: b.update(icons[ispb]);b['aliases'].append(b['display_name']);b['name']=b['display_name']
    banks.append(b)
banks.sort(key=lambda b:(0 if b.get('display_name') else 1,b['name'].casefold()))

# Enrich by exact ISPB/CNPJ-root match with the official Open Finance directory.
# This is public identity metadata only, never a banking account connection.
DIRECTORY='https://data.directory.openbankingbrasil.org.br/participants'
directory=json.loads(get(DIRECTORY)[0])
from collections import Counter
import hashlib
official={}
for organisation in directory:
    if organisation.get('Status')!='Active': continue
    ispb=str(organisation.get('RegistrationId') or '').strip()
    if not re.fullmatch(r'[A-Za-z0-9]{8}',ispb): continue
    uris=[server.get('CustomerFriendlyLogoUri','') for server in organisation.get('AuthorisationServers',[]) if server.get('CustomerFriendlyLogoUri','').startswith('https://')]
    if uris: official[ispb]=[uri for uri,_ in Counter(uris).most_common()]
def download_official(bank):
    if bank.get('logo') or bank['id'] not in official: return bank
    for uri in official[bank['id']][:3]:
        try:
            data,mime,final=get(uri)
            if len(data)>500000 or not final.startswith('https://'): continue
            source_hash=hashlib.sha256(data).hexdigest()
            ext='png' if data.startswith(b'\x89PNG') else 'ico' if data.startswith(b'\x00\x00\x01\x00') else 'jpg' if data.startswith(b'\xff\xd8') else None
            if ext is None and b'<svg' in data[:2000] and b'<!DOCTYPE' not in data.upper() and b'<!ENTITY' not in data.upper():
                svg=ET.fromstring(data)
                allowed={'svg','g','path','defs','image','symbol','linearGradient','radialGradient','stop','rect','circle','ellipse','polygon','polyline','line','use','title','desc','clipPath','mask','style','text'}
                if svg.tag.split('}')[-1]!='svg': continue
                for parent in list(svg.iter()):
                    for child in list(parent):
                        if child.tag.split('}')[-1] not in allowed or (child.tag.split('}')[-1]=='style' and re.search(r'@import|https?:|//',child.text or '')): parent.remove(child)
                    for attr,value in list(parent.attrib.items()):
                        local=attr.split('}')[-1].lower()
                        if local.startswith('on') or (local=='href' and not value.startswith('#') and not re.fullmatch(r'data:image/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=\s]+',value)) or re.search(r'url\(\s*[\'\"]?(?!#)',value): del parent.attrib[attr]
                ET.register_namespace('','http://www.w3.org/2000/svg')
                data=ET.tostring(svg,encoding='utf-8'); ext='svg'
            if ext:
                dest=out/(bank['id']+'.'+ext);dest.write_bytes(data)
                bank.update({'logo':'assets/banks/'+dest.name,'logo_source':uri,'logo_directory':DIRECTORY,'logo_sha256':hashlib.sha256(data).hexdigest(),'source_sha256':source_hash})
                return bank
        except Exception: pass
    return bank
banks=list(concurrent.futures.ThreadPoolExecutor(max_workers=8).map(download_official,banks))

# Reviewed local fallback assets keep their pinned hash and provenance on refresh.
override_path=ROOT/'tools'/'bank_logo_overrides.json'
if override_path.exists():
    overrides=json.loads(override_path.read_text(encoding='utf-8'))
    for bank in banks:
        override=overrides.get(bank['id'])
        if override:
            asset=ROOT/override['logo']
            if not asset.exists() or hashlib.sha256(asset.read_bytes()).hexdigest()!=override['logo_sha256']:
                raise ValueError('Reviewed logo asset is missing or changed: '+bank['id'])
            bank.update(override)
(ROOT/'backend'/'resources').mkdir(exist_ok=True)
(ROOT/'backend'/'resources'/'banks.json').write_text(json.dumps(banks,ensure_ascii=False,indent=2),encoding='utf-8')
manifest={'source':SOURCE,'source_scope':'Participantes STR; não é uma lista de emissores de cartão','retrieved_on':date.today().isoformat(),'institutions':len(banks),'logos':sum(bool(b['logo']) for b in banks),'entries':{b['id']:{k:v for k,v in b.items() if k.startswith('logo') or k in ['name','source_sha256']} for b in banks if b.get('logo')},'directory':DIRECTORY}
(out/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf-8')
print('Catalog:',len(banks),'official icons:',manifest['logos'])
