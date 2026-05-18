<?php 
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api/auth_check.php';

global $pdo_glpi, $pdo_intra;

$nome_exibicao = "Usuário";
$iniciais = "??";

if (isset($_SESSION['user_id'])) {
    // 1. Busca Dados Básicos no GLPI
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
        
        $nome_exibicao = trim($primeiro_nome . " " . $sobrenome);
        $iniciais = strtoupper(substr($primeiro_nome, 0, 1) . substr($sobrenome, 0, 1));
        
        $_SESSION['user_name'] = $nome_exibicao;
        $_SESSION['setor_principal'] = strtoupper($user_data['setor'] ?? 'GERAL');
        
        $meus_perfis = explode(',', $user_data['perfis_ids'] ?? '');
        $is_glpi_admin = in_array('4', $meus_perfis); // Se ele for Super-Admin no GLPI

        // =========================================================================
        // 🛡️ 2. O NOVO CÉREBRO DE PERMISSÕES (RBAC - Híbrido)
        // =========================================================================
        
        // A. Busca as permissões EXCLUSIVAS do usuário
        $stmt_indiv = $pdo_intra->prepare("SELECT * FROM usuarios_permissoes WHERE usuario_id = ?");
        $stmt_indiv->execute([$_SESSION['user_id']]);
        $perm_indiv = $stmt_indiv->fetch(PDO::FETCH_ASSOC) ?: [];

        // B. Busca as permissões HERDADAS de todos os grupos que ele participa
        $stmt_grupo = $pdo_intra->prepare("
            SELECT 
                MAX(g.is_admin) as g_admin, 
                MAX(g.pode_gerenciar_docs) as g_docs, 
                MAX(g.pode_postar_feed) as g_feed, 
                MAX(g.pode_gerenciar_acessos) as g_acessos
            FROM usuarios_grupos ug
            JOIN grupos_intranet g ON ug.grupo_id = g.id
            WHERE ug.usuario_id = ?
        ");
        $stmt_grupo->execute([$_SESSION['user_id']]);
        $perm_grupo = $stmt_grupo->fetch(PDO::FETCH_ASSOC) ?: [];

        // C. A Matemática Final (Individual OU Grupo)
        $is_intra_admin = !empty($perm_indiv['is_admin']) || !empty($perm_grupo['g_admin']);
        $pode_docs      = !empty($perm_indiv['pode_gerenciar_docs']) || !empty($perm_grupo['g_docs']);
        $pode_feed      = !empty($perm_indiv['pode_postar_feed']) || !empty($perm_grupo['g_feed']);
        $pode_acessos   = !empty($perm_indiv['pode_gerenciar_acessos']) || !empty($perm_grupo['g_acessos']);

        // D. Guarda no crachá (Sessão)
        $_SESSION['is_admin'] = (bool)$is_intra_admin;
        $_SESSION['pode_gerenciar_docs']    = ($_SESSION['is_admin'] || $pode_docs);
        $_SESSION['pode_postar_feed']       = ($_SESSION['is_admin'] || $pode_feed);
        $_SESSION['pode_gerenciar_acessos'] = ($_SESSION['is_admin'] || $pode_acessos);

        // =========================================================================
        // 📂 3. MATRIZ DE PASTAS E SETORES
        // =========================================================================
        
        // Pastas dadas pessoa por pessoa (A tabela antiga)
        $stmt_extras_indiv = $pdo_intra->prepare("SELECT pasta_nome FROM permissoes_pastas WHERE user_id = ?");
        $stmt_extras_indiv->execute([$_SESSION['user_id']]);
        $pastas_indiv = $stmt_extras_indiv->fetchAll(PDO::FETCH_COLUMN);

        // Pastas liberadas pelos grupos que ele participa
        $stmt_extras_grupo = $pdo_intra->prepare("
            SELECT gp.pasta_nome 
            FROM usuarios_grupos ug
            JOIN grupos_pastas gp ON ug.grupo_id = gp.grupo_id
            WHERE ug.usuario_id = ?
        ");
        $stmt_extras_grupo->execute([$_SESSION['user_id']]);
        $pastas_grupo = $stmt_extras_grupo->fetchAll(PDO::FETCH_COLUMN);

        // Junta as pastas dele com as pastas do grupo, remove repetições, e salva!
        $todas_pastas = array_unique(array_merge($pastas_indiv, $pastas_grupo));
        $_SESSION['pastas_extras'] = array_map('strtoupper', $todas_pastas);

        // =========================================================================
        // 4. LÓGICA DE PRESENÇA
        // =========================================================================
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
    <title>INTRANET</title>
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

        // 🔥 FUNÇÃO GLOBAL UNIFICADA (Matadora de ReferenceError)[cite: 8, 9]
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar-menu');
            const overlay = document.getElementById('mobile-overlay');
            
            if (!sidebar || !overlay) {
                console.error("Erro Crítico: Elementos do menu (sidebar-menu ou mobile-overlay) não encontrados no DOM.");
                return;
            }

            sidebar.classList.toggle('-translate-x-full');
            
            if (overlay.classList.contains('hidden')) {
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            } else {
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }
    </script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/style.css">
</head>

<body class="h-full bg-slate-100 font-sans antialiased">
    <div class="flex flex-col h-full">
        <header class="sticky top-0 z-50 bg-navy-900 border-b border-navy-700 shadow-lg px-6 py-3">
            <div class="flex items-center justify-between">
                
                <div class="flex items-center gap-4">
                    <!-- BOTAO HAMBURGUER (Mantido para navegação mobile) -->
                    <button onclick="toggleMobileMenu()" class="lg:hidden p-2 -ml-2 text-slate-400 hover:text-white focus:outline-none transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    <!-- 🔥 LOGO SOUZA: Agora maior e sem os textos laterais -->
                    <div class="flex items-center py-1">
                        <img src="img/logo.svg" alt="Logo Souza" class="h-12 md:h-16 w-auto object-contain transition-all">
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
            
            setTimeout(() => {
                const el = document.getElementById(id);
                if(el) {
                    el.classList.add('animate-out', 'fade-out', 'slide-out-to-right');
                    setTimeout(() => el.remove(), 500);
                }
            }, 8000);
        }

        // Função Global para controlar o Menu Mobile
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar-menu');
            const overlay = document.getElementById('mobile-overlay');
            
            if (!sidebar || !overlay) {
                console.warn("Elementos do menu não encontrados.");
                return;
            }

            sidebar.classList.toggle('-translate-x-full');
            
            if (overlay.classList.contains('hidden')) {
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            } else {
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
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
        setInterval(monitorarLogins, 10000);


        </script>

        <div class="flex flex-1 overflow-hidden">