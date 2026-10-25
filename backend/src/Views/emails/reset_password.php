<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperação de Acesso · VTD</title>
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
            <h1>Recuperação de Acesso</h1>
            <p>Olá, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>.</p>
            <p>Recebemos uma solicitação para redefinir a sua senha de acesso à plataforma VTD.</p>
            <p>Clique no botão abaixo para escolher uma nova senha. Este link expira em <strong>60 minutos</strong>:</p>
            
            <div style="text-align: center; margin: 32px 0;">
                <a href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn">Redefinir Minha Senha</a>
            </div>

            <p style="font-size: 13px; color: #888;">Se você não solicitou a recuperação, pode ignorar este e-mail com segurança. Sua senha atual permanecerá inalterada.</p>
        </div>
        <div class="footer">
            VTD · Venda Todo Dia · Mensagem automática de segurança.
        </div>
    </div>
</body>
</html>
