<?php
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$nome = $dados['nome_destinatario'] ?? 'Colaborador(a)';
$titulo = $dados['titulo'] ?? 'Documento para assinatura';
$criador = $dados['criador'] ?? 'Intranet Souza';
$tipo = $dados['tipo_fluxo'] ?? 'Sequencial';
$etapa = $dados['etapa'] ?? '1';
$totalEtapas = $dados['total_etapas'] ?? '1';
$quantidade = $dados['quantidade_documentos'] ?? '1';
$url = $dados['url_acao'] ?? '#';
$bannerCid = $dados['banner_cid'] ?? null;
?>
<div style="margin:0;background:#f1f5f9;padding:28px 12px;font-family:Arial,sans-serif;color:#0f172a">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center">
<table role="presentation" width="680" style="width:100%;max-width:680px;background:#fff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden" cellspacing="0" cellpadding="0" border="0">
<?php if ($bannerCid): ?><tr><td><img src="cid:<?= $e($bannerCid) ?>" alt="Novo documento para assinatura" width="680" style="display:block;width:100%;height:auto;border:0"></td></tr><?php else: ?><tr><td style="background:#0f172a;padding:27px 30px;color:#fff"><div style="font-size:11px;letter-spacing:2px;color:#93c5fd;font-weight:bold">PORTAL INTERNO DE ASSINATURAS</div><div style="font-size:22px;font-weight:bold;margin-top:10px">Você recebeu um documento para assinar</div></td></tr><?php endif; ?>
<tr><td style="padding:30px"><p style="font-size:15px;line-height:1.65;margin:0">Olá, <strong><?= $e($nome) ?></strong>. Um novo envelope foi encaminhado para sua assinatura na intranet.</p>
<div style="margin:22px 0;padding:17px;border-radius:12px;border:1px solid #bfdbfe;background:#eff6ff"><strong style="color:#1d4ed8">Nova pendência disponível</strong><p style="margin:7px 0 0;color:#475569;font-size:13px;line-height:1.5">Revise todos os documentos antes de registrar sua decisão com a senha atual da intranet.</p></div>
<table role="presentation" width="100%" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;font-size:13px" cellspacing="0" cellpadding="0"><tr><td style="padding:18px"><p style="margin:0 0 9px;color:#64748b">Envelope<br><strong style="color:#0f172a"><?= $e($titulo) ?></strong></p><p style="margin:0 0 9px;color:#64748b">Enviado por<br><strong style="color:#0f172a"><?= $e($criador) ?></strong></p><p style="margin:0;color:#64748b">Fluxo: <strong style="color:#0f172a"><?= $e($tipo) ?></strong> &nbsp;·&nbsp; Etapa: <strong style="color:#0f172a"><?= $e($etapa) ?> de <?= $e($totalEtapas) ?></strong> &nbsp;·&nbsp; Documentos: <strong style="color:#0f172a"><?= $e($quantidade) ?></strong></p></td></tr></table>
<div style="text-align:center;margin:24px 0"><a href="<?= $e($url) ?>" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;font-size:12px;font-weight:bold;padding:15px 24px;border-radius:10px">VISUALIZAR E ASSINAR</a></div>
<div style="padding:15px;border-left:4px solid #2563eb;background:#eff6ff;color:#1e3a8a;font-size:12px;line-height:1.5">Sua senha é pessoal, será apenas validada e não será armazenada no fluxo de assinatura. A ação será registrada com identificação, data, hora e IP.</div>
<p style="margin:25px 0 0;color:#94a3b8;font-size:11px;text-align:center;line-height:1.5">Mensagem automática da Intranet Souza.<br>Você também pode acessar pelo menu “Minhas Assinaturas”.</p></td></tr></table>
</td></tr></table></div>
