# VTD — Venda Todo Dia

Plataforma HTML/CSS/JavaScript com API PHP, fornecedores, calendário, guia, financeiro por loja e painel administrativo Master.

## Executar localmente

Requisitos: PHP 8.2+ com PDO SQLite e sessões. Não há build nem dependências npm.

```sh
php setup_local_db.php
php -S 127.0.0.1:8080 local_router.php
```

Acesse http://127.0.0.1:8080. O servidor PHP é necessário para acesso e persistência; abrir o HTML diretamente ou usar servidor estático não atende a esses fluxos.

| Conta de demonstração | E-mail | Senha |
| --- | --- | --- |
| Administrador | demo@exemplo.com | 123456 |
| Cliente Essencial | ana@example.com | 123456 |

Use essas contas somente em desenvolvimento. A carga inicial contém dados fictícios e pode sobrescrever registros demonstrativos; execute-a apenas em um banco preparado para esse fim.

## Funcionalidades

- Autenticação por sessão, proteção CSRF e recuperação de senha por e-mail.
- Fornecedores com busca, filtros, paginação, favoritos e contatos.
- Calendário com checklists; guia com progresso e anotações.
- Financeiro por loja: categorias, lançamentos em centavos, quitação, projeções e CSV; exige plano Plus.
- Painel Master em #/master/overview: clientes, acessos, produtos, integrações, eventos e configurações.
- Administração de fornecedores com cadastro e edição; edição dos capítulos e meses.
- Integrações com mapeamento de ofertas e reprocessamento de eventos. A URL usa app_url da configuração.

## Configuração e publicação

Copie backend/config/.env.example.php para backend/config/.env.php e ajuste banco, endereço da aplicação e SMTP. Esse arquivo e os bancos SQLite locais são ignorados pelo Git.

O driver de e-mail padrão é mock: não envia mensagens. Para convites e recuperação reais, configure SMTP. Valide os provedores de checkout com eventos reais em ambiente de testes antes do uso comercial.

O backend suporta SQLite e MySQL. Consulte [o guia de hospedagem](docs/DEPLOY_HOSTINGER.md). As migrações 001 e 002 fazem parte da instalação atual; o modelo de configuração atualizado está em backend/config/.env.example.php.

## Verificação

```sh
php tests/run_all_tests.php
node --check app.js
node --check master.js
node --check data.js
node --test tests/frontend.test.cjs
```

O executor PHP ativa as asserções em cada processo. As suítes utilizam bancos locais e dados demonstrativos: não execute os testes com a configuração de produção.

O GitHub Actions executa a suíte com SQLite e verifica instalação, integrações, configurações e fornecedores também em MySQL 8.

O frontend também tem testes de favoritos, busca concorrente, navegação, rascunhos, filtros financeiros, falhas de conexão e prevenção de envios duplicados.

## Estrutura

- app.js, master.js: interface do cliente e do administrador.
- styles.css, styles-master.css: layouts e navegação responsiva.
- styles-ui.css: identidade visual compartilhada, estados de interação e adaptações para celular.
- api.php: rotas da API.
- backend/src/: controladores, segurança, serviços e banco.
- backend/migrations/, backend/seeds/: esquema e dados demonstrativos.
- tests/: testes automatizados.
- vendas.html: página comercial; checkout depende de configuração.
