<?php
require_once 'config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Proteção: Se não for admin, volta para o início
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    ?>
    <main class="flex-1 flex items-center justify-center bg-slate-100 h-screen">
        <div class="text-center p-12 bg-white rounded-3xl shadow-2xl max-w-lg border border-slate-200 mx-4">
            
            <div class="mb-8">
                <img src="img/logo.svg" alt="Nave Logo" class="h-16 mx-auto">
            </div>

            <h1 class="text-4xl font-black text-red-700 mb-4 tracking-tight">
                Acesso Restrito
            </h1>
            
            <p class="text-slate-500 text-lg mb-8 leading-relaxed">
                Olá, <strong><?php echo $_SESSION['user_name']; ?></strong>.<br>
                Você não tem permissão para esta área.<br>
                Por favor, procure a <strong>EQUIPE DE T.I.</strong>
            </p>

            <a href="index.php" class="inline-flex items-center gap-2 px-8 py-4 bg-corporate-blue text-white rounded-2xl font-bold shadow-lg hover:bg-corporate-blueDark transition-all transform hover:scale-105">
                <span>🏠</span> Voltar para o Início
            </a>
        </div>
    </main>
    <?php
    exit;
}

// 1. Busca todos os usuários ativos do GLPI
$stmt_users = $pdo_glpi->query("
    SELECT u.id, u.name as login, u.firstname, u.realname, l.name as setor 
    FROM glpi_users u 
    LEFT JOIN glpi_locations l ON u.locations_id = l.id 
    WHERE u.is_deleted = 0 AND u.is_active = 1
    ORDER BY u.firstname ASC
");
$usuarios = $stmt_users->fetchAll();

// 2. Lê as pastas físicas disponíveis em /docs
$diretorio_docs = __DIR__ . '/docs/';
$pastas_fisicas = [];
if (is_dir($diretorio_docs)) {
    $dirs = scandir($diretorio_docs);
    foreach ($dirs as $d) {
        if ($d !== '.' && $d !== '..' && is_dir($diretorio_docs . $d)) {
            $pastas_fisicas[] = strtoupper($d);
        }
    }
}
?>

<main class="flex-1 overflow-y-auto bg-slate-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-2xl font-bold text-navy-900">Gestão de Acessos</h2>
                <p class="text-slate-500">Controle quem pode ver pastas de outros departamentos.</p>
            </div>
            <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-slate-200">
                <span class="text-xs font-bold text-slate-400 uppercase">Total de Usuários:</span>
                <span class="ml-2 font-bold text-corporate-blue"><?php echo count($usuarios); ?></span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Colaborador</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Setor GLPI</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Pastas Extras Liberadas</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($usuarios as $user): 
                        // Busca as permissões atuais deste usuário no banco intranet
                        $stmt_perm = $pdo_intra->prepare("SELECT pasta_nome FROM permissoes_pastas WHERE user_id = ?");
                        $stmt_perm->execute([$user['id']]);
                        $perms_atuais = $stmt_perm->fetchAll(PDO::FETCH_COLUMN);
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-slate-900"><?php echo $user['firstname'] . ' ' . $user['realname']; ?></div>
                            <div class="text-xs text-slate-400">@<?php echo $user['login']; ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <span class="px-2 py-1 bg-slate-100 rounded text-[10px] font-bold"><?php echo strtoupper($user['setor'] ?? 'SEM SETOR'); ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1">
                                <?php if(empty($perms_atuais)): ?>
                                    <span class="text-xs text-slate-300 italic">Nenhuma pasta extra</span>
                                <?php else: ?>
                                    <?php foreach($perms_atuais as $p): ?>
                                        <span class="px-2 py-0.5 bg-blue-50 text-corporate-blue border border-blue-100 rounded-full text-[10px] font-bold">
                                            <?php echo $p; ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button onclick="abrirModal(<?php echo $user['id']; ?>, '<?php echo $user['firstname']; ?>', <?php echo htmlspecialchars(json_encode($perms_atuais)); ?>)" 
                                    class="text-corporate-blue hover:bg-blue-50 p-2 rounded-lg transition-all">
                                ⚙️ Gerenciar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div id="modalPermissao" class="fixed inset-0 bg-navy-900/50 backdrop-blur-sm z-[100] hidden items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <form action="api/processar_permissao.php" method="POST">
            <input type="hidden" name="user_id" id="modal_user_id">
            <div class="p-6 border-b border-slate-100">
                <h3 class="text-lg font-bold text-navy-900">Gerenciar Pastas Extras</h3>
                <p class="text-sm text-slate-500" id="modal_user_name"></p>
            </div>
            <div class="p-6 max-h-[400px] overflow-y-auto">
                <p class="text-[10px] font-bold text-slate-400 uppercase mb-4">Selecione as pastas para liberar:</p>
                <div class="grid grid-cols-1 gap-2">
                    <?php foreach($pastas_fisicas as $pasta): ?>
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:bg-slate-50 cursor-pointer transition-all">
                        <input type="checkbox" name="pastas[]" value="<?php echo $pasta; ?>" class="w-4 h-4 text-corporate-blue rounded border-slate-300">
                        <span class="text-sm font-medium text-slate-700"><?php echo $pasta; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="p-6 bg-slate-50 flex gap-3">
                <button type="button" onclick="fecharModal()" class="flex-1 px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-corporate-blue text-white rounded-lg text-sm font-bold shadow-lg hover:bg-corporate-blueDark transition-all">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal(id, nome, perms) {
    document.getElementById('modal_user_id').value = id;
    document.getElementById('modal_user_name').innerText = "Colaborador: " + nome;
    
    // Marca os checkboxes das permissões que o cara já tem
    const checks = document.querySelectorAll('input[name="pastas[]"]');
    checks.forEach(c => {
        c.checked = perms.includes(c.value);
    });

    document.getElementById('modalPermissao').classList.remove('hidden');
    document.getElementById('modalPermissao').classList.add('flex');
}

function fecharModal() {
    document.getElementById('modalPermissao').classList.add('hidden');
    document.getElementById('modalPermissao').classList.remove('flex');
}
</script>

<?php include 'includes/footer.php'; ?>