<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo Financeiro Liberado · VTD</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #0d0d0d; color: #f0f0f0; margin: 0; padding: 24px; }
        .card { max-width: 560px; margin: 0 auto; background: #161616; border: 1px solid #282828; border-radius: 12px; overflow: hidden; }
        .header { background: #111111; padding: 32px 36px; border-bottom: 2px solid #ebc500; }
        .logo { font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; }
        .logo span { color: #ebc500; }
        .content { padding: 36px; }
        h1 { font-size: 22px; margin-top: 0; color: #ffffff; }
        p { font-size: 15px; line-height: 1.6; color: #b5b5b5; margin: 0 0 18px; }
        .btn { display: inline-block; background: #ebc500; color: #111; text-decoration: none; font-weight: 700; padding: 14px 28px; border-radius: 8px; font-size: 15px; margin-top: 12px; }
        .footer { padding: 24px 36px; background: #111111; font-size: 12px; color: #666; border-top: 1px solid #222; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="logo">VTD <span>· Venda Todo Dia</span></div>
        </div>
        <div class="content">
            <h1>Parabéns, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p>Seu upgrade para o plano <strong style="color: #ebc500;">Kit + Financeiro</strong> foi confirmado com sucesso!</p>
            <p>O módulo de <strong>Controle Financeiro</strong> já está disponível em sua conta para você acompanhar o fluxo de caixa, saldo realizado e projetado da sua loja.</p>
            
            <div style="text-align: center; margin: 32px 0;">
                <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn">Abrir Meu Financeiro</a>
            </div>
        </div>
        <div class="footer">
            VTD · Venda Todo Dia · Controle Financeiro Profissional.
        </div>
    </div>
</body>
</html>
