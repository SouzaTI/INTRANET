<?php 
require_once __DIR__ . '/../config.php';

global $pdo_glpi, $pdo_intra;

$nome_exibicao = "Usuário";
$iniciais = "??";

if (isset($_SESSION['user_id'])) {
    // 1. Busca Dados no GLPI
    $stmt = $pdo_glpi->prepare("
        SELECT u.firstname, u.realname, l.name as setor, GROUP_CONCAT(p.id) as perfis_ids
        FROM glpi_users u 
        LEFT JOIN glpi_locations l ON u.locations_id = l.id 
        LEFT JOIN glpi_profiles_users pu ON u.id = pu.users_id
        LEFT JOIN glpi_profiles p ON pu.profiles_id = p.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();

    if ($user_data) {
        $primeiro_nome = $user_data['firstname'] ?: 'Usuário';
        $sobrenome = $user_data['realname'] ?: '';
        
        // Define o nome completo para exibição e para o banco de dados
        $nome_exibicao = trim($primeiro_nome . " " . $sobrenome);
        
        // Define as iniciais (Primeira letra do nome + Primeira letra do sobrenome)
        $iniciais = strtoupper(substr($primeiro_nome, 0, 1) . substr($sobrenome, 0, 1));
        
        $_SESSION['user_name'] = $nome_exibicao;
        $_SESSION['setor_principal'] = strtoupper($user_data['setor'] ?? 'GERAL');
        
        // --- 2. LÓGICA DE ADMIN E GESTÃO ---
        $meus_perfis = explode(',', $user_data['perfis_ids'] ?? '');
        
        $stmt_intra = $pdo_intra->prepare("SELECT is_admin, pode_gerenciar_docs FROM usuarios_config WHERE user_id = ?");
        $stmt_intra->execute([$_SESSION['user_id']]);
        $config_intra = $stmt_intra->fetch();

        $is_intra_admin = (bool)($config_intra['is_admin'] ?? false);
        $pode_docs_local = (bool)($config_intra['pode_gerenciar_docs'] ?? false);

        $_SESSION['is_admin'] = ($is_intra_admin || in_array('4', $meus_perfis));
        $_SESSION['pode_gerenciar_docs'] = ($_SESSION['is_admin'] || $pode_docs_local);

        // 3. Pastas Extras
        $stmt_extras = $pdo_intra->prepare("SELECT pasta_nome FROM permissoes_pastas WHERE user_id = ?");
        $stmt_extras->execute([$_SESSION['user_id']]);
        $_SESSION['pastas_extras'] = $stmt_extras->fetchAll(PDO::FETCH_COLUMN);

        // --- NOVA LÓGICA DE PRESENÇA (Update Silencioso) ---
        // Aqui usamos o $nome_exibicao para gravar nome e sobrenome na lista online
        $stmt_p = $pdo_intra->prepare("
            INSERT INTO controle_presenca (usuario_id, nome_usuario, status, ultima_atividade) 
            VALUES (?, ?, 'ONLINE', NOW()) 
            ON DUPLICATE KEY UPDATE 
                nome_usuario = VALUES(nome_usuario), 
                status = 'ONLINE', 
                ultima_atividade = NOW()
        ");
        $stmt_p->execute([$_SESSION['user_id'], $nome_exibicao]);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAVI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        navy: { 900: '#0f172a', 800: '#1e293b', 700: '#334155' },
                        corporate: { blue: '#2563eb', blueDark: '#1d4ed8' },
                        status: { online: '#22c55e' }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/style.css">
</head>
<body class="h-full bg-slate-100 font-sans antialiased">
    <div class="flex flex-col h-full">
        <header class="sticky top-0 z-50 bg-navy-900 border-b border-navy-700 shadow-lg px-6 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-corporate-blue to-corporate-blueDark flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white tracking-tight leading-none">NAVI</h1>
                        <p class="text-[10px] text-slate-400 uppercase tracking-widest mt-1">Portal Corporativo</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 pl-4 border-l border-navy-700">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-medium text-white"><?php echo $nome_exibicao; ?></p>
                        
                        <div class="flex items-center justify-end gap-1.5 mt-0.5">
                             <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">
                                <?php echo $_SESSION['is_admin'] ? 'Administrador' : $_SESSION['setor_principal']; ?>
                            </p>
                            <span class="w-[1px] h-2 bg-navy-700 mx-1"></span>
                            <div class="flex items-center gap-1">
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                                </span>
                                <span class="text-[9px] font-black text-emerald-500 uppercase tracking-tighter">Trabalhando</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="relative group">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white font-semibold text-sm cursor-pointer border-2 border-transparent group-hover:border-white/20 transition-all">
                            <?php echo $iniciais; ?>
                        </div>
                        
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-2xl py-2 invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all transform scale-95 group-hover:scale-100 z-[100]">
                            <div class="px-4 py-2 border-b border-slate-100">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Sessão Ativa</p>
                            </div>
                            <a href="logout.php" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors font-semibold">
                                <span>🚪</span> Sair do Portal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div id="toast-container" class="fixed bottom-8 right-8 z-[9999] pointer-events-none flex flex-col gap-3"></div>

       <script>
        let loginsAvisados = [];

        function dispararPopupLogin(nome) {
            const container = document.getElementById('toast-container');
            const id = 'toast-' + Math.random().toString(36).substr(2, 9);
            
            const toastHTML = `
                <div id="${id}" class="bg-navy-900 text-white p-5 rounded-[1.5rem] shadow-2xl border-l-4 border-emerald-500 flex items-center gap-4 animate-in slide-in-from-right duration-500 pointer-events-auto min-w-[320px]">
                    <div class="w-12 h-12 bg-emerald-500/10 rounded-2xl flex items-center justify-center text-2xl">⚡</div>
                    <div>
                        <p class="text-[10px] font-black text-emerald-500 uppercase tracking-[0.2em] leading-none mb-1">Acesso Detetado</p>
                        <p class="text-sm font-bold text-white">${nome} entrou no posto!</p>
                    </div>
                    <button onclick="this.parentElement.remove()" class="ml-auto text-white/20 hover:text-white text-xl">&times;</button>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', toastHTML);
            
            // Som de notificação (opcional - remova se não quiser áudio)
            // new Audio('assets/sounds/notify.mp3').play().catch(() => {});

            setTimeout(() => {
                const el = document.getElementById(id);
                if(el) {
                    el.classList.add('animate-out', 'fade-out', 'slide-out-to-right');
                    setTimeout(() => el.remove(), 500);
                }
            }, 8000);
        }

        function monitorarLogins() {
            fetch('check_novos_acessos.php')
                .then(res => res.json())
                .then(usuarios => {
                    usuarios.forEach(user => {
                        if (!loginsAvisados.includes(user.nome_usuario)) {
                            dispararPopupLogin(user.nome_usuario);
                            loginsAvisados.push(user.nome_usuario);
                        }
                    });
                });
        }

        // Verifica a cada 10 segundos
        setInterval(monitorarLogins, 10000);
        </script>

        <div class="flex flex-1 overflow-hidden">