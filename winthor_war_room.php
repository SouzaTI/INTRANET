<?php
require_once 'config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// 1. Query Padrão FIFA focada na Parametrização (ID 12943)
$sql_war_room = "
    SELECT 
        CASE 
            WHEN responsavel LIKE 'IMPLANTADOR%' OR responsavel = 'EDUARDO' THEN 'IMPLANTADOR (EDUARDO)'
            WHEN responsavel LIKE 'SETOR %' THEN responsavel 
            ELSE 'OUTROS / SEM SETOR'
        END AS grupo_responsavel,
        COUNT(*) as total_tarefas,
        SUM(CASE WHEN status_coluna = 'concluidos' THEN 1 ELSE 0 END) as concluidas,
        SUM(CASE WHEN status_coluna != 'concluidos' AND status IN ('Aguardando', 'Pendente') THEN 1 ELSE 0 END) as bloqueadas,
        ROUND((SUM(CASE WHEN status_coluna = 'concluidos' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as progresso_percentual
    FROM kanban_cards
    WHERE fk_subtarefa = 12943 
    GROUP BY grupo_responsavel
    ORDER BY progresso_percentual DESC";

// Busca os dados no banco de projetos
$stmt_war = $pdo_projetos->query($sql_war_room);
$setores_war = $stmt_war->fetchAll(PDO::FETCH_ASSOC);

// Cálculo do progresso geral da fase para o header
$total_fase = array_sum(array_column($setores_war, 'total_tarefas'));
$concluidas_fase = array_sum(array_column($setores_war, 'concluidas'));
$percentual_geral = ($total_fase > 0) ? round(($concluidas_fase / $total_fase) * 100) : 0;
?>

<main class="flex-1 overflow-y-auto bg-slate-900 p-8">
    <div class="max-w-7xl mx-auto">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-3xl">⚔️</span>
                    <h1 class="text-white text-4xl font-black italic uppercase tracking-tighter">Sala de Guerra</h1>
                </div>
                <p class="text-slate-400 font-bold uppercase text-xs tracking-[0.3em]">Status Real: Parametrização Winthor</p>
            </div>
            
            <div class="bg-navy-800 p-6 rounded-3xl border border-navy-700 shadow-2xl min-w-[280px]">
                <div class="flex justify-between items-end mb-2">
                    <span class="text-slate-400 text-[10px] font-black uppercase">Progresso da Etapa</span>
                    <span class="text-emerald-500 text-3xl font-black"><?php echo $percentual_geral; ?>%</span>
                </div>
                <div class="w-full bg-navy-900 h-3 rounded-full overflow-hidden border border-navy-700">
                    <div class="h-full bg-gradient-to-r from-emerald-600 to-emerald-400 transition-all duration-1000" style="width: <?php echo $percentual_geral; ?>%"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($setores_war as $s): 
                $is_travado = $s['bloqueadas'] > 0;
                $is_implantador = (strpos($s['grupo_responsavel'], 'IMPLANTADOR') !== false);
            ?>
                <div class="group relative bg-navy-800 rounded-[2rem] p-6 border-2 transition-all hover:scale-[1.03] <?php echo $is_travado ? 'border-rose-500/50 bg-rose-500/5' : 'border-navy-700 hover:border-corporate-blue/50'; ?>">
                    
                    <?php if ($is_travado): ?>
                        <div class="absolute -top-3 -right-3">
                            <span class="relative flex h-8 w-8">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-8 w-8 bg-rose-500 items-center justify-center text-white text-xs font-black">!</span>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="flex justify-between items-start mb-6">
                        <div class="space-y-1">
                            <h3 class="text-white font-black text-sm uppercase tracking-wider <?php echo $is_implantador ? 'text-emerald-400' : ''; ?>">
                                <?php echo $s['grupo_responsavel']; ?>
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full <?php echo $is_travado ? 'bg-rose-500 animate-pulse' : 'bg-emerald-500'; ?>"></span>
                                <span class="text-[10px] font-bold uppercase <?php echo $is_travado ? 'text-rose-400' : 'text-slate-500'; ?>">
                                    <?php echo $is_travado ? 'Gargalo Identificado' : 'Operação Normal'; ?>
                                </span>
                            </div>
                        </div>
                        <span class="text-2xl font-black text-white"><?php echo round($s['progresso_percentual']); ?><span class="text-xs text-slate-500">%</span></span>
                    </div>

                    <div class="w-full bg-navy-900 h-4 rounded-xl p-1 mb-4">
                        <div class="h-full rounded-lg transition-all duration-1000 <?php echo $is_travado ? 'bg-rose-500' : ($is_implantador ? 'bg-emerald-500' : 'bg-corporate-blue'); ?>" 
                             style="width: <?php echo $s['progresso_percentual']; ?>%"></div>
                    </div>

                    <div class="flex justify-between items-center text-[10px] font-black uppercase tracking-tighter">
                        <div class="text-slate-500">
                            <span class="text-white"><?php echo $s['concluidas']; ?></span> / <?php echo $s['total_tarefas']; ?> Concluídas
                        </div>
                        <?php if ($is_travado): ?>
                            <div class="text-rose-500">
                                <?php echo $s['bloqueadas']; ?> Pendência(s)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-12 text-center">
            <p class="text-slate-600 text-[10px] font-bold uppercase tracking-widest">Atualizado em tempo real via Kanban Parametrização</p>
        </div>

    </div>
</main>

<?php include 'includes/footer.php'; ?>