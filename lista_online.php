<?php
require_once 'config.php';

// Busca usuários com atividade global (capturada pelo header.php em qualquer página)
$stmt = $pdo_intra->query("
    SELECT nome_usuario, status, ultima_atividade,
           TIMESTAMPDIFF(MINUTE, ultima_atividade, NOW()) as minutos_inativo
    FROM controle_presenca 
    WHERE status = 'ONLINE' 
    AND ultima_atividade > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ORDER BY ultima_atividade DESC
");
$usuarios = $stmt->fetchAll();
?>

<div class="bg-white rounded-[2rem] p-6 shadow-sm border border-slate-200 flex flex-col h-full">
    <div class="flex items-center justify-between mb-6 px-2 shrink-0">
        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Equipe no Posto</h3>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-emerald-500"><?= count($usuarios) ?> No Portal</span>
        </div>
    </div>

    <div class="space-y-3 overflow-y-auto pr-2 custom-scrollbar" style="max-height: 400px;">
        <?php if (count($usuarios) > 0): ?>
            <?php foreach ($usuarios as $u): 
                // --- LÓGICA DE INICIAIS (NOME + SOBRENOME) ---
                // Divide o nome completo em partes usando o espaço como separador
                $partes_nome = explode(' ', trim($u['nome_usuario']));
                // Pega a primeira letra do primeiro nome
                $p_inicial = substr($partes_nome[0], 0, 1);
                // Se houver mais de um nome, pega a primeira letra do último sobrenome
                $s_inicial = (count($partes_nome) > 1) ? substr(end($partes_nome), 0, 1) : '';
                // Une as letras em maiúsculo
                $iniciais = strtoupper($p_inicial . $s_inicial);
                
                $status_cor = ($u['minutos_inativo'] < 5) ? 'bg-emerald-500' : 'bg-amber-500';
                $texto_atividade = ($u['minutos_inativo'] < 1) ? 'Ativo agora' : 'há ' . $u['minutos_inativo'] . ' min';
            ?>
                <div class="flex items-center justify-between p-3 bg-slate-50/50 rounded-2xl border border-slate-100 transition-all hover:bg-slate-100">
                    <div class="flex items-center gap-3 truncate">
                        <div class="relative shrink-0">
                            <div class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-[11px] font-black text-slate-500 shadow-sm">
                                <?= $iniciais ?>
                            </div>
                            <div class="absolute bottom-0 right-0 w-3 h-3 <?= $status_cor ?> border-2 border-white rounded-full"></div>
                        </div>
                        <div class="flex flex-col truncate">
                            <span class="text-xs font-black text-navy-900 leading-none uppercase tracking-tight truncate">
                                <?= $u['nome_usuario'] ?>
                            </span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase mt-1">
                                <?= $u['status'] ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="text-right shrink-0 ml-2">
                        <p class="text-[8px] font-black text-slate-400 uppercase leading-none italic">Visto</p>
                        <p class="text-[9px] font-bold text-navy-900"><?= $texto_atividade ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-8">
                <p class="text-[10px] font-bold text-slate-400 uppercase italic">Ninguém online</p>
            </div>
        <?php endif; ?>
    </div>
</div>