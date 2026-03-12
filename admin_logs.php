<?php
require_once 'config.php';
include 'includes/header.php';

// Bloqueio de segurança idêntico ao da gestão
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    ?>
    <main class="flex-1 flex items-center justify-center bg-slate-100 h-screen">
        <div class="text-center p-12 bg-white rounded-3xl shadow-2xl max-w-lg border border-slate-200 mx-4">
            <div class="mb-8"><img src="img/logo.svg" alt="Nave Logo" class="h-16 mx-auto"></div>
            <div class="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h1 class="text-4xl font-black text-navy-900 mb-4 tracking-tight">Acesso Restrito</h1>
            <p class="text-slate-500 text-lg mb-8 leading-relaxed">Procure a <strong>EQUIPE DE T.I.</strong></p>
            <a href="index.php" class="inline-flex items-center gap-2 px-8 py-4 bg-corporate-blue text-white rounded-2xl font-bold shadow-lg hover:bg-corporate-blueDark transition-all transform hover:scale-105"><span>🏠</span> Voltar para o Início</a>
        </div>
    </main>
    <?php exit;
}
include 'includes/sidebar.php';

$sql = "SELECT l.*, u.firstname, u.realname 
        FROM logs_acesso l
        LEFT JOIN " . DB_GLPI . ".glpi_users u ON l.user_id = u.id 
        ORDER BY l.data_hora DESC LIMIT 100";

$stmt = $pdo_intra->query($sql);
$logs = $stmt->fetchAll();
?>

<main class="flex-1 overflow-y-auto bg-slate-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h2 class="text-3xl font-bold text-navy-900 tracking-tight">Logs de Auditoria</h2>
                <p class="text-slate-500 mt-1">Histórico completo de acessos e permissões alteradas.</p>
            </div>
            <div class="bg-white p-3 rounded-xl border border-slate-200 text-xs font-bold text-slate-400">
                REGISTROS TOTAIS: <span class="text-corporate-blue"><?php echo count($logs); ?></span>
            </div>
        </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="max-h-[650px] overflow-y-auto custom-scroll">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-900 text-white text-[10px] uppercase tracking-widest">
                        <th class="p-5">Data/Hora</th>
                        <th class="p-5">Colaborador</th>
                        <th class="p-5">Ação Realizada</th>
                        <th class="p-5">Endereço IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="4" class="p-20 text-center text-slate-400 italic">Nenhum log registrado até o momento.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($logs as $log): ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="p-5 text-sm font-mono text-slate-500">
                            <?php echo date('d/m/Y H:i:s', strtotime($log['data_hora'])); ?>
                        </td>
                        
                        <td class="p-5">
                            <p class="font-bold text-navy-900">
                                <?php echo ($log['firstname'] || $log['realname']) ? $log['firstname'] . ' ' . $log['realname'] : 'ID: ' . $log['user_id']; ?>
                            </p>
                        </td>
                        
                        <td class="p-5">
                            <?php 
                                $acao = strtoupper($log['acao']);
                                $badgeColor = (strpos($acao, 'NEGADO') !== false || strpos($acao, 'ERRO') !== false) 
                                            ? 'bg-red-100 text-red-700' 
                                            : 'bg-emerald-100 text-emerald-700';
                            ?>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?php echo $badgeColor; ?>">
                                <?php echo $acao; ?>
                            </span>
                            <p class="text-xs text-slate-500 mt-2 font-medium">
                                <?php echo $log['detalhes']; // Coluna com 'S' conforme sua estrutura ?>
                            </p>
                        </td>
                        
                        <td class="p-5 text-xs text-slate-400 font-semibold">
                            <?php echo $log['ip']; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>