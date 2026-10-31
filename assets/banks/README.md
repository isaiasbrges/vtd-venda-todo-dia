# Instituições e identificação visual

Catálogo obtido em 2026-10-31 da lista de participantes do STR do Banco Central:
https://www.bcb.gov.br/content/estabilidadefinanceira/str1/ParticipantesSTR.csv

Referência e atribuição: Banco Central do Brasil / Deban. Licença do conjunto de dados: Open Data Commons Open Database License (ODbL), conforme o portal de dados abertos do BCB. O arquivo inclui instituições participantes do STR; não afirma que todas emitem cartões.

O catálogo atual contém 468 instituições e 50 logos oficiais verificados. As demais aparecem com nome e monograma neutro; o cadastro também permite Outra instituição. Não anunciar cobertura integral de logos.

Os logos pertencem aos respectivos titulares, são usados para identificar a instituição selecionada e não indicam parceria ou conexão bancária. As origens de cada imagem estão em manifest.json. Os arquivos não contêm dados de clientes.

Atualização: python tools/update_bank_catalog.py. Conferir a cobertura, nomes e aparência antes de publicar. O script só utiliza dados públicos e sites das instituições; não envia informações de usuários. O catálogo consumido pela API está em backend/resources/banks.json.

Ampliação: o diretório público https://data.directory.openbankingbrasil.org.br/participants fornece os endereços dos logos por instituição. O vínculo usa o ISPB, sem aproximação por nome. SVGs são sanitizados; imagens incorporadas só são mantidas quando são PNG/JPEG/WebP em base64.

O logo do Itaú foi incluído a partir do asset PNG do projeto https://github.com/rzmt/logos-bancos-br, cuja proveniência aponta o SVG publicado pelo Itaú no Open Finance. A licença MIT está em LICENSE-logos-bancos-br.txt; fonte e hash estão em tools/bank_logo_overrides.json e manifest.json. A atualização preserva esse asset revisado pelo hash.
