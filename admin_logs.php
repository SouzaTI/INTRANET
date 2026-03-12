<?php
require_once 'config.php';
include 'includes/header.php';

// Bloqueio de segurança
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
            <a href="index.php" class="inline-flex items-center gap-2 px-8 py-4 bg-corporate-blue text-white rounded-2xl font-bold shadow-lg hover:bg-corporate-blueDark transition-all"><span>🏠</span> Voltar para o Início</a>
        </div>
    </main>
    <?php exit;
}
include 'includes/sidebar.php';

// FILTROS
$filtro_usuario = $_GET['u'] ?? '';
$filtro_acao = $_GET['a'] ?? '';

// Montagem da Query Dinâmica
$query = "SELECT l.*, u.firstname, u.realname 
          FROM logs_acesso l
          LEFT JOIN " . DB_GLPI . ".glpi_users u ON l.user_id = u.id 
          WHERE 1=1";

$params = [];
if ($filtro_usuario) {
    $query .= " AND (u.firstname LIKE ? OR u.realname LIKE ?)";
    $params[] = "%$filtro_usuario%";
    $params[] = "%$filtro_usuario%";
}
if ($filtro_acao) {
    $query .= " AND l.acao LIKE ?";
    $params[] = "%$filtro_acao%";
}

$query .= " ORDER BY l.data_hora DESC LIMIT 100";
$stmt = $pdo_intra->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<main class="flex-1 overflow-y-auto bg-slate-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-black text-navy-900 tracking-tight uppercase italic">Auditoria Navi</h2>
                <p class="text-slate-500 mt-1 font-medium">Rastreamento completo de ações no sistema.</p>
            </div>
            
            <form method="GET" class="flex flex-wrap gap-2">
                <input type="text" name="u" value="<?= $filtro_usuario ?>" placeholder="Colaborador..." 
                       class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold outline-none focus:ring-2 focus:ring-corporate-blue/20">
                
                <select name="a" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold outline-none">
                    <option value="">Todas as Ações</option>
                    <option value="SALVAR" <?= $filtro_acao == 'SALVAR' ? 'selected' : '' ?>>Salvar</option>
                    <option value="EXCLUIR" <?= $filtro_acao == 'EXCLUIR' ? 'selected' : '' ?>>Excluir</option>
                    <option value="NEGADO" <?= $filtro_acao == 'NEGADO' ? 'selected' : '' ?>>Acesso Negado</option>
                    <option value="UPLOAD" <?= $filtro_acao == 'UPLOAD' ? 'selected' : '' ?>>Upload</option>
                </select>

                <button type="submit" class="bg-navy-900 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-800 transition-all">FILTRAR</button>
                <?php if($filtro_usuario || $filtro_acao): ?>
                    <a href="admin_logs.php" class="bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold flex items-center">Limpar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
            <div class="max-h-[700px] overflow-y-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[10px] uppercase tracking-widest font-black text-slate-400">
                            <th class="p-5">Data/Hora</th>
                            <th class="p-5">Colaborador</th>
                            <th class="p-5">Ação & Detalhes</th>
                            <th class="p-5 text-center">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($logs as $log): 
                            $nome_completo = ($log['firstname'] || $log['realname']) ? $log['firstname'] . ' ' . $log['realname'] : 'ID: ' . $log['user_id'];
                            $acao = strtoupper($log['acao']);
                            
                            // Definição inteligente de cores
                            $cor = 'bg-slate-100 text-slate-600';
                            if (strpos($acao, 'EXCLUIR') !== false || strpos($acao, 'NEGADO') !== false) $cor = 'bg-red-50 text-red-600 border border-red-100';
                            elseif (strpos($acao, 'SALVAR') !== false || strpos($acao, 'CRIAR') !== false) $cor = 'bg-emerald-50 text-emerald-600 border border-emerald-100';
                            elseif (strpos($acao, 'UPLOAD') !== false) $cor = 'bg-blue-50 text-blue-600 border border-blue-100';
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-all">
                            <td class="p-5">
                                <span class="text-[11px] font-bold text-slate-400 block mb-1 uppercase tracking-tighter">
                                    <?= date('d M, Y', strtotime($log['data_hora'])) ?>
                                </span>
                                <span class="text-xs font-mono font-black text-slate-700">
                                    <?= date('H:i:s', strtotime($log['data_hora'])) ?>
                                </span>
                            </td>
                            
                            <td class="p-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-navy-900 flex items-center justify-center text-[10px] text-white font-black shadow-sm uppercase">
                                        <?= substr($log['firstname'] ?? 'U', 0, 1) . substr($log['realname'] ?? '', 0, 1) ?>
                                    </div>
                                    <span class="text-xs font-black text-navy-900 uppercase tracking-tighter"><?= $nome_completo ?></span>
                                </div>
                            </td>
                            
                            <td class="p-5">
                                <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest <?= $cor ?>">
                                    <?= $acao ?>
                                </span>
                                <p class="text-xs text-slate-500 mt-2 italic font-medium max-w-md leading-relaxed">
                                    <?= $log['detalhes'] ?>
                                </p>
                            </td>
                            
                            <td class="p-5 text-center">
                                <span class="text-[10px] font-bold text-slate-300 px-2 py-1 border border-slate-100 rounded-lg">
                                    <?= $log['ip'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>