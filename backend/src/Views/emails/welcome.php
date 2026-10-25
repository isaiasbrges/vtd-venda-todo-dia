<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seu acesso ao Venda Todo Dia</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #0d0d0d; color: #f0f0f0; margin: 0; padding: 24px; }
        .card { max-width: 560px; margin: 0 auto; background: #161616; border: 1px solid #282828; border-radius: 12px; overflow: hidden; }
        .header { background: #111111; padding: 32px 36px; border-bottom: 2px solid #ebc500; }
        .logo { font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; }
        .logo span { color: #ebc500; }
        .content { padding: 36px; }
        h1 { font-size: 22px; margin-top: 0; color: #ffffff; }
        p { font-size: 15px; line-height: 1.6; color: #b5b5b5; margin: 0 0 18px; }
        .box { background: #202020; border-radius: 8px; padding: 20px; margin: 24px 0; border-left: 4px solid #ebc500; }
        .row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .row:last-child { margin-bottom: 0; }
        .label { color: #888; }
        .val { font-weight: 600; color: #fff; font-family: monospace; font-size: 15px; }
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
            <h1>Olá, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p>Seja bem-vindo(a)! Seu acesso à plataforma VTD foi liberado com sucesso.</p>
            <p>Seu plano ativo é: <strong style="color: #ebc500;"><?= $plan === 'plus' ? 'Kit + Financeiro' : 'Kit Essencial' ?></strong>.</p>
            
            <div class="box">
                <div class="row">
                    <span class="label">Link de Acesso:</span>
                    <span class="val"><?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="row">
                    <span class="label">Seu E-mail:</span>
                    <span class="val"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="row">
                    <span class="label">Sua Senha Inicial:</span>
                    <span class="val"><?= htmlspecialchars($password, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <p>Recomendamos que você altere sua senha no primeiro acesso em <strong>Minha conta</strong>.</p>
            <div style="text-align: center; margin: 28px 0 12px;">
                <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn">Acessar Minha Loja</a>
            </div>
        </div>
        <div class="footer">
            VTD · Venda Todo Dia · Este e-mail contém suas credenciais confidenciais de acesso.
        </div>
    </div>
</body>
</html>
