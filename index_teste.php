<?php 
require_once 'config.php'; 
require_once 'api/auth_check.php';
include 'includes/header.php'; 

// 1. Pega o nome bruto da sessão
$nome_completo = $_SESSION['usuario_nome'] ?? $_SESSION['user_name'] ?? 'Colaborador';

// 2. Transforma em array e define o que ignorar (Conectivos)
$palavras = explode(' ', trim((string)$nome_completo));
$conectivos = ['DE', 'DA', 'DO', 'DAS', 'DOS'];

// 3. O primeiro nome é sempre a primeira posição
$primeiro_nome = mb_convert_case($palavras[0], MB_CASE_TITLE, "UTF-8");
$segundo_nome = '';

// 4. Lógica para achar o sobrenome real (pulando os conectivos)
for ($i = 1; $i < count($palavras); $i++) {
    $palavra_atual = mb_strtoupper($palavras[$i], 'UTF-8');
    
    // Se a palavra for um conectivo, pula para a próxima
    if (in_array($palavra_atual, $conectivos)) {
        continue;
    }
    
    // Achou o primeiro sobrenome real? Guarda e para o laço
    $segundo_nome = mb_convert_case($palavras[$i], MB_CASE_TITLE, "UTF-8");
    break;
}

$nome_exibicao = trim($primeiro_nome . ' ' . $segundo_nome);

// --- MANTENDO SUAS CONFIGURAÇÕES ATUAIS (NÃO REMOVER) ---
$_SESSION['is_admin'] = $_SESSION['is_admin'] ?? false;
$_SESSION['setor_principal'] = $_SESSION['setor_principal'] ?? 'GERAL';
$user_id_logado = $_SESSION['user_id'] ?? 0; 

if (!isset($_SESSION['logado_nesta_sessao'])) {
    registrarLog($pdo_intra, 'ACESSO AO PORTAL', 'O usuário carregou a página inicial da Intranet.');
    $_SESSION['logado_nesta_sessao'] = true;
}
// -----------------------------------------------------
include 'includes/sidebar.php'; 

$hoje = date('Y-m-d');

// Busca apenas o que deve estar no ar HOJE
$stmt = $pdo_intra->prepare("SELECT * FROM banners_marketing 
                             WHERE ativo = 1 
                             AND :hoje BETWEEN data_inicio AND data_fim 
                             ORDER BY id DESC");
$stmt->execute(['hoje' => $hoje]);
$banners = $stmt->fetchAll();

// 2. Busca Comunicados Ativos com contadores REAIS de curtidas e comentários
$sql_feed = "SELECT c.*, 
            (SELECT COUNT(*) FROM feed_curtidas WHERE comunicado_id = c.id) as total_curtidas,
            (SELECT COUNT(*) FROM feed_comentarios WHERE comunicado_id = c.id) as total_comentarios,
            (SELECT COUNT(*) FROM feed_curtidas WHERE comunicado_id = c.id AND user_id = ?) as ja_curtiu
            FROM comunicados c 
            WHERE c.ativo = 1 
            AND c.data_postagem >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) /* A MÁGICA DA EXPIRAÇÃO AQUI */
            ORDER BY c.data_postagem DESC 
            LIMIT 10"; // Aumentei o limite para 10, já que as antigas vão sumir sozinhas

$stmt_feed = $pdo_intra->prepare($sql_feed);
$stmt_feed->execute([$user_id_logado]);
$comunicados = $stmt_feed->fetchAll();

$sistemas_permitidos = [1, 2];
$aniversariantes = [];
$caminho_lista = 'img/comunicacao/aniversariantes_lista.txt';
$mes_atual = date('m'); // Pega o mês atual com zero à esquerda (ex: 03)

if (file_exists($caminho_lista)) {
    $linhas = file($caminho_lista, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($linhas as $linha) {
        // Verifica se a linha tem o separador ";" (padrão ou convertido do Excel)
        if (strpos($linha, ';') !== false) {
            list($nome, $data_bruta) = explode(';', $linha);
            
            $nome = trim($nome);
            $data_curta = substr(trim($data_bruta), 0, 5); // Garante DD/MM
            $partes_data = explode('/', $data_curta);
            $mes_aniv = $partes_data[1] ?? '';

            // SÓ ADICIONA SE FOR DO MÊS ATUAL
            if ($mes_aniv == $mes_atual) {
                $aniversariantes[] = [
                    'nome' => mb_strtoupper($nome, 'UTF-8'),
                    'data' => $data_curta
                ];
            }
        }
    }
}
// Se após ler o arquivo o array continuar vazio (não tem niver no mês),
// nós criamos um item padrão para o HTML ter o que ler.
if (empty($aniversariantes)) {
    $aniversariantes[] = [
        'nome' => 'FELIZ ANIVERSÁRIO!',
        'data' => '--/--'
    ];
}
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 md:p-8">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- ============================================================ -->
        <!-- [BLOCO: BOAS VINDAS]                                         -->
        <!-- ============================================================ -->
        <section id="bloco-boas-vindas" class="relative overflow-hidden rounded-2xl shadow-lg min-h-[120px] border border-slate-200 bg-navy-900 group mb-2">
            <img src="img/comunicacao/banner-boas-vindas.png" 
                class="absolute inset-0 w-full h-full object-cover opacity-90 animate-ken-burns" 
                alt="Bem-vindo">
            
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full animate-shimmer-effect"></div>

            <!-- Padding de p-10 baixou para p-6 -->
            <div class="relative z-10 p-6 h-full flex flex-col justify-center">
                <div class="animate-slide-up">
                    <!-- Fonte levemente reduzida para não ficar muito carregado -->
                    <h1 class="text-white text-xl md:text-2xl font-black tracking-tighter mb-0.5 drop-shadow-2xl whitespace-normal leading-tight">
                        Olá, <?php echo $nome_exibicao; ?>! <span class="inline-block animate-wave">👋</span>
                    </h1>
                    <p class="text-blue-50 text-xs font-medium drop-shadow-lg italic opacity-90">
                        Sua central de comunicação corporativa <span class="font-bold text-white uppercase">Intranet</span>.
                    </p>
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- MODAL: ANIVERSARIANTES (Solto fora do grid)                  -->
        <!-- ============================================================ -->
        <div id="modalAniversariantes" onclick="fecharModalAniversariantes()" class="fixed inset-0 z-[999] hidden bg-black/95 backdrop-blur-md flex items-center justify-center p-4 md:p-10 transition-all duration-300">
            <div class="relative max-w-5xl w-full flex flex-col items-center">
                <button class="absolute -top-14 right-0 text-white text-5xl font-light hover:text-amber-400 transition-colors">&times;</button>
                <img src="img/comunicacao/aniversariantes_modal.png" class="w-full h-auto max-h-[85vh] object-contain rounded-2xl shadow-2xl border border-white/10 animate-in zoom-in-95 duration-300" alt="Lista Completa de Aniversariantes">
                <p class="mt-6 text-white/50 text-sm font-medium tracking-widest uppercase italic">Clique em qualquer lugar para fechar</p>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- GRID PRINCIPAL: DIVISÃO DE COLUNAS                           -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- -------------------------------------------------------- -->
            <!-- COLUNA ESQUERDA (lg:col-span-8)                          -->
            <!-- -------------------------------------------------------- -->
            <div class="lg:col-span-8 space-y-8">
                
                <!-- [BLOCO: CARROSSEL DE BANNERS] -->
                <div id="bloco-carrossel" class="relative group overflow-hidden rounded-2xl bg-slate-50 border border-slate-100 shadow-sm min-h-[240px] flex">
                    <?php if (count($banners) > 1): ?>
                        <button onclick="moverCarrossel(-1)" class="absolute left-4 top-1/2 -translate-y-1/2 z-20 bg-black/20 hover:bg-black/50 backdrop-blur-md p-4 rounded-full text-white transition-all opacity-0 group-hover:opacity-100 shadow-lg">❮</button>
                        <button onclick="moverCarrossel(1)" class="absolute right-4 top-1/2 -translate-y-1/2 z-20 bg-black/20 hover:bg-black/50 backdrop-blur-md p-4 rounded-full text-white transition-all opacity-0 group-hover:opacity-100 shadow-lg">❯</button>
                    <?php endif; ?>

                    <div id="carrossel-container" class="flex transition-transform duration-700 ease-in-out w-full">
                        <?php foreach ($banners as $banner): ?>
                            <div class="min-w-full h-full">
                                <img src="<?php echo $banner['imagem_path']; ?>" class="w-full h-full object-cover block" alt="Banner Corporativo">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- [BLOCO: FEED DE NOTÍCIAS] -->
                <div id="bloco-feed" class="space-y-6">
                    <div class="flex items-center justify-between px-4">
                        <h3 class="text-navy-900 font-black text-xl uppercase tracking-tighter italic">Feed de Notícias</h3>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Última Semana</span>
                    </div>

                    <div class="grid grid-cols-1 gap-6 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
                        <?php if (empty($comunicados)): ?>
                            <div class="bg-white rounded-2xl p-10 shadow-sm border border-slate-100 text-center flex flex-col items-center justify-center transition-all hover:shadow-md h-64">
                                <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center text-3xl mb-4 shadow-inner">📬</div>
                                <h4 class="text-lg font-black text-navy-900 tracking-tight mb-2">Nada de novo por aqui!</h4>
                                <p class="text-slate-500 text-sm font-medium leading-relaxed">Estamos aguardando novas atualizações.<br>Pode deixar que informaremos você em breve!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($comunicados as $com): 
                                $cor_setor = ['TI' => 'bg-blue-500', 'RH' => 'bg-emerald-500', 'Marketing' => 'bg-amber-500'][$com['categoria']] ?? 'bg-slate-500';
                            ?>
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 hover:shadow-md transition-all">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 <?php echo $cor_setor; ?> rounded-xl flex items-center justify-center text-white font-black shadow-md">
                                            <?php echo substr($com['categoria'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <p class="font-black text-navy-900 italic text-xs">
                                                <?php 
                                                if (in_array($com['categoria'], ['IMPORTANTE', 'AVISO GERAL'])) {
                                                    echo "📢 Comunicado Oficial";
                                                } else {
                                                    echo "Equipe de <span class='uppercase'>" . $com['categoria'] . "</span>";
                                                }
                                                ?> 
                                                • <span class="text-slate-400 font-medium not-italic text-[10px]"><?php echo date('d/m H:i', strtotime($com['data_postagem'])); ?></span>
                                            </p>
                                            <h4 class="text-lg font-black text-navy-900 tracking-tight leading-tight"><?php echo $com['titulo']; ?></h4>
                                        </div>
                                    </div>
                                    
                                    <span class="bg-slate-100 text-slate-500 border border-slate-200 text-[9px] font-black px-3 py-1 rounded-full uppercase">
                                        <?php echo $com['categoria']; ?>
                                    </span>
                                </div>
                                <p class="text-slate-500 text-sm leading-relaxed mb-6"><?php echo $com['resumo']; ?></p>
                                <div class="pt-4 border-t border-slate-50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-6">
                                            <button onclick="toggleCurtida(<?php echo $com['id']; ?>, this)" 
                                                    class="flex items-center gap-2 transition-colors <?php echo $com['ja_curtiu'] ? 'text-rose-500' : 'text-slate-400'; ?> hover:text-red-500">
                                                <span class="text-lg icone-coracao"><?php echo $com['ja_curtiu'] ? '❤️' : '🤍'; ?></span> 
                                                <span class="text-xs font-bold contador-curtidas"><?php echo $com['total_curtidas']; ?></span>
                                            </button>

                                            <button onclick="toggleComentarios(<?php echo $com['id']; ?>)" class="flex items-center gap-2 text-slate-400 hover:text-blue-500 transition-colors">
                                                <span class="text-lg">💬</span> 
                                                <span class="text-xs font-bold contador-comentarios"><?php echo $com['total_comentarios']; ?></span>
                                            </button>
                                        </div>
                                        <button class="text-slate-400 hover:text-navy-900 text-lg">🔖</button>
                                    </div>

                                    <div id="comentarios-post-<?php echo $com['id']; ?>" class="hidden mt-4 pt-4 border-t border-slate-50">
                                        <div class="lista-comentarios space-y-3 mb-4 max-h-40 overflow-y-auto custom-scrollbar-compact pr-2">
                                            <p class="text-center text-[10px] text-slate-400 italic">Carregando...</p>
                                        </div>
                                        <form onsubmit="enviarComentario(event, <?php echo $com['id']; ?>, this)" class="flex gap-2 relative">
                                            <input type="text" name="texto_comentario" placeholder="Escreva um comentário..." required autocomplete="off" 
                                                class="flex-1 bg-slate-50 border border-slate-100 rounded-xl pl-4 pr-12 py-3 text-xs outline-none focus:ring-2 focus:ring-blue-500 text-slate-700">
                                            <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 bg-blue-600 text-white rounded-lg flex items-center justify-center hover:bg-navy-900 transition-colors shadow-md">
                                                <svg class="w-3 h-3 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?> 
                    </div>                    
                </div>
            </div>

            <!-- -------------------------------------------------------- -->
            <!-- COLUNA DIREITA (lg:col-span-4)                           -->
            <!-- -------------------------------------------------------- -->
            <div class="lg:col-span-4 space-y-6 sticky top-6">

                <!-- [BLOCO: ANIVERSARIANTES] -->
                <div id="bloco-aniversariantes" onclick="abrirModalAniversariantes()" 
                    class="card-destaque-img cursor-pointer group rounded-2xl shadow-lg min-h-[220px] border border-slate-200 flex flex-col items-center justify-center p-4 text-center overflow-hidden relative"
                    style="background-image: url('img/comunicacao/aniversariantes_mini.png'); background-size: cover; background-position: center;">
                    
                    <div class="absolute inset-0 bg-black/40 z-0 group-hover:bg-black/30 transition-colors"></div>

                    <div class="relative z-10 w-full flex flex-col items-center justify-center pt-8">
                        <div id="ticker-aniversariante" class="w-full transition-all duration-700 ease-in-out transform translate-y-0 opacity-1">
                            <h4 id="nome-aniv" class="text-white font-black text-xl md:text-2xl leading-tight tracking-tighter uppercase mb-2 px-2 drop-shadow-2xl">
                                <?php echo $aniversariantes[0]['nome']; ?>
                            </h4>
                            
                            <span id="data-aniv" class="inline-block text-white font-black text-4xl tracking-tighter border-t-2 border-amber-500 pt-2 px-6 drop-shadow-md">
                                <?php echo $aniversariantes[0]['data']; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- [BLOCO: CALENDÁRIO COMPACTO] -->
                <div id="bloco-calendario" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-2">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-xl bg-slate-50 flex items-center justify-center border border-slate-100">
                            <span class="text-base">📅</span>
                        </div>
                        <h3 class="text-xs font-black text-navy-900 tracking-tight">Sua agenda</h3>
                    </div>

                    <div id="calendario-ajax" class="border border-slate-100 rounded-2xl p-3 mb-4 min-h-[250px] text-sm">
                        <div class="flex justify-center items-center h-full">Carregando...</div>
                    </div>
                    
                    <div id="proximos-eventos" class="space-y-3">
                        <!-- O JS preenche aqui -->
                    </div>
                </div>

                <!-- [BLOCO: SISTEMAS INTERNOS] -->
                <div id="bloco-sistemas" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-3">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-5">Sistemas Internos</h3>
                    <div class="grid grid-cols-1 gap-3">
                        <a href="http://192.168.0.63:8080/glpi17/index.php" target="_blank" 
                           class="flex items-center gap-4 p-3 rounded-xl bg-slate-50 hover:bg-blue-50 border border-slate-100 transition-all group">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-lg group-hover:scale-110 transition-transform">🛠️</div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-navy-900 leading-tight">HELP CHAMADOS</span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter">Suporte</span>
                            </div>
                        </a>
                        <button onclick="abrirModalSistemas()" 
                                class="w-full flex items-center gap-4 p-3 rounded-xl bg-navy-900 hover:bg-blue-700 border border-transparent transition-all group shadow-md">
                            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center text-lg group-hover:rotate-12 transition-transform">🚀</div>
                            <div class="flex flex-col text-left text-white">
                                <span class="text-xs font-black leading-tight uppercase">Outros</span>
                                <span class="text-[9px] text-white/50 font-bold uppercase tracking-tighter italic">Navegação</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- [BLOCO: CENTRAL DE AJUDA] -->
                <div id="bloco-central-ajuda" class="bg-navy-900 rounded-2xl p-5 text-white shadow-xl border-l-4 border-blue-500 relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500/0 via-blue-500/5 to-blue-500/0 -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                    
                    <div class="flex flex-col h-full justify-between relative z-10">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 italic">Central de Ajuda</p>
                            </div>
                            <p class="font-bold text-sm leading-snug">Dúvidas ou suporte técnico?</p>
                        </div>
                        
                        <div class="mt-4 flex items-end justify-between">
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Ramal Interno</p>
                                <div class="flex items-center gap-2">
                                    <span class="text-xl font-black text-white tracking-tighter italic">3171</span>
                                    <span class="text-blue-500 text-xs animate-bounce">📞</span>
                                </div>
                            </div>
                            
                            <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/40 group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- [BLOCO: PROJETOS ATIVOS] -->
                <?php
                $projetos_ativos = [];
                try {
                    $pdo_proj = $pdo_projetos; 
                    $stmtProj = $pdo_proj->query("SELECT * FROM projetos WHERE data_virada IS NOT NULL ORDER BY data_virada ASC");
                    $projetos_ativos = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($projetos_ativos as $key => $proj) {
                        $stmtProg = $pdo_proj->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN s.status = 'concluido' THEN 1 ELSE 0 END) as concluidas FROM subtarefas s INNER JOIN fases f ON s.fk_fase = f.id WHERE f.fk_projeto = ?");
                        $stmtProg->execute([$proj['id']]);
                        $progData = $stmtProg->fetch(PDO::FETCH_ASSOC);
                        $total_t = (int)$progData['total'];
                        $projetos_ativos[$key]['progresso'] = $total_t > 0 ? round(((int)$progData['concluidas'] / $total_t) * 100) : 0;
                    }
                } catch(Exception $e) {}
                ?>

                <?php if (count($projetos_ativos) > 0): 
                    $proj_principal = $projetos_ativos[0]; 
                ?>
                <div id="bloco-projetos" onclick="abrirModalProjetos()" class="space-y-4 cursor-pointer group relative transition-all hover:scale-[1.02]">
                    <div class="absolute -top-3 right-4 bg-blue-600 text-white text-[9px] font-black px-3 py-1 rounded-full shadow-lg z-20 group-hover:bg-blue-500 transition-colors uppercase tracking-widest border border-blue-400">
                        Ver Todos (<?php echo count($projetos_ativos); ?>)
                    </div>

                    <div class="bg-navy-900 rounded-2xl p-5 shadow-lg border border-slate-800 flex flex-col justify-center relative z-10">
                        <div class="flex justify-between items-start mb-3">
                            <div class="overflow-hidden pr-2">
                                <h3 class="text-blue-400 font-black text-[10px] uppercase tracking-widest">Projeto em Destaque</h3>
                                <span class="text-white font-black italic text-base truncate block"><?php echo mb_strtoupper($proj_principal['nome_projeto'], 'UTF-8'); ?></span>
                            </div>
                            <span class="text-emerald-400 font-black text-2xl leading-none drop-shadow-[0_0_5px_rgba(52,211,153,0.8)]"><?php echo $proj_principal['progresso']; ?>%</span>
                        </div>
                        <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden mt-1">
                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-1000" style="width: <?php echo $proj_principal['progresso']; ?>%"></div>
                        </div>
                    </div>

                    <?php if (isset($proj_principal['status_implantacao']) && $proj_principal['status_implantacao'] === 'TRAVADO'): ?>
                        <div class="bg-red-500 rounded-2xl p-4 shadow-lg border border-red-600 flex flex-col justify-center relative overflow-hidden animate-pulse">
                            <div class="absolute -right-4 -bottom-4 opacity-[0.15] text-7xl grayscale">🛑</div>
                            <div class="flex items-center gap-2 mb-1 relative z-10">
                                <span class="text-white text-sm">⚠️</span>
                                <span class="text-white font-black text-[10px] uppercase tracking-widest">PROCESSO EM ANDAMENTO</span>
                            </div>
                            <p class="text-red-50 text-[10px] font-bold leading-tight relative z-10 line-clamp-2">
                                <?php echo $proj_principal['motivo_bloqueio'] ?: 'Aguardando liberação da diretoria.'; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="bg-slate-900 rounded-2xl p-4 shadow-lg border border-slate-800 text-center flex flex-col justify-center">
                        <p class="text-slate-500 text-[9px] font-black uppercase tracking-[0.2em] mb-3">Contagem para Virada</p>
                        <div class="grid grid-cols-4 gap-2 text-white cronometro-dinamico" data-virada="<?php echo $proj_principal['data_virada']; ?>">
                            <div class="bg-slate-800 rounded-xl py-2"><span class="c-dias block font-black text-lg">00</span><span class="text-[8px] text-slate-500 uppercase">Dias</span></div>
                            <div class="bg-slate-800 rounded-xl py-2"><span class="c-horas block font-black text-lg">00</span><span class="text-[8px] text-slate-500 uppercase">Hrs</span></div>
                            <div class="bg-slate-800 rounded-xl py-2"><span class="c-mins block font-black text-lg">00</span><span class="text-[8px] text-slate-500 uppercase">Min</span></div>
                            <div class="bg-slate-800 rounded-xl py-2"><span class="c-segs block font-black text-lg text-blue-400">00</span><span class="text-[8px] text-slate-500 uppercase">Seg</span></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- [BLOCO: PRESENÇA / EQUIPE ONLINE] -->
                <div id="bloco-equipe-online" class="w-full transition-all duration-500">
                    <div id="painel-presenca">
                        <div class="animate-pulse bg-white rounded-2xl h-64 w-full border border-slate-200"></div>
                    </div>
                </div>

            </div> 
        </div>
    </div>
</main>

<div id="modalSistemas" class="fixed inset-0 z-[1000] hidden items-center justify-center p-4 backdrop-blur-xl bg-navy-900/40 transition-all duration-500">
  <div class="modal-sistemas-painel relative w-full max-w-6xl rounded-[2rem] p-8 animate-in zoom-in-95 duration-300 overflow-hidden">
        
        <button id="btnVoltarModal" onclick="exibirPrincipalSistemas()" class="hidden absolute top-6 left-6 text-blue-400 hover:text-white hover:scale-105 transition-all text-xs font-black flex items-center gap-2 z-30 bg-white/5 border border-white/10 px-4 py-2 rounded-xl backdrop-blur-md">
            ⬅️ VOLTAR
        </button>

        <div class="absolute -top-24 -right-24 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl"></div>
       
        <button onclick="fecharModalSistemas()" class="absolute top-5 right-6 text-white/30 hover:text-white transition-colors text-3xl font-light z-30">&times;</button>

         <div class="modal-sistemas-header mb-8 flex flex-col md:flex-row justify-between items-start md:items-center border-b border-cyan-400/10 pb-4 mt-4 md:mt-0">
            <div>
                <h2 id="tituloModalSistemas" class="text-white text-xl font-black tracking-tighter uppercase italic">Sistemas de Navegação</h2>
                <p id="subtituloModalSistemas" class="text-blue-400 text-[10px] font-bold uppercase tracking-widest">Sistemas e ferramentas autorizados para seu perfil</p>
            </div>
           
            <!-- Pesquisa tecnológica de sistemas -->
        <div class="pesquisa-sistemas-wrapper mt-3 md:mt-0 md:mr-8">
            <div class="pesquisa-sistemas">
                <span class="pesquisa-sistemas__icone" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="6"></circle>
                        <path d="M16 16L21 21"></path>
                    </svg>
                </span>

                <input
                    type="text"
                    id="inputBuscaSistemas"
                    oninput="filtrarSistemas()"
                    placeholder="Buscar sistema ou módulo..."
                    autocomplete="off"
                >

                <button
                    type="button"
                    class="pesquisa-sistemas__limpar"
                    onclick="limparBuscaSistemas()"
                    title="Limpar pesquisa"
                    aria-label="Limpar pesquisa"
                >
                    &times;
                </button>
            </div>
        </div>

        </div>

        <?php 
            $sistemas_permitidos = [];
            
            // RBAC: Resgata as permissões associadas ao usuário
            if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
                $stmt_sys = $pdo_intra->query("SELECT * FROM sistemas_lista ORDER BY nome");
                $sistemas_permitidos = $stmt_sys->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt_sys = $pdo_intra->prepare("
                    SELECT DISTINCT sl.* FROM sistemas_lista sl
                    LEFT JOIN permissoes_sistemas ps ON sl.id = ps.sistema_id AND ps.user_id = ?
                    LEFT JOIN grupos_sistemas gs ON sl.id = gs.sistema_id
                    LEFT JOIN usuarios_grupos ug ON gs.grupo_id = ug.grupo_id AND ug.usuario_id = ?
                    WHERE ps.user_id IS NOT NULL OR ug.usuario_id IS NOT NULL
                    ORDER BY sl.nome
                ");
                $stmt_sys->execute([$user_id_logado, $user_id_logado]);
                $sistemas_permitidos = $stmt_sys->fetchAll(PDO::FETCH_ASSOC);
            }

            $sistemas_raiz = [];
            $sistemas_filhos = [];

            foreach ($sistemas_permitidos as $sys) {
                if (!empty($sys['pai_id'])) {
                    $sistemas_filhos[$sys['pai_id']][] = $sys;
                } else {
                    $sistemas_raiz[] = $sys;
                }
            }
        ?>

       <div id="gridSistemasPrincipal" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-7 max-h-[62vh] overflow-y-auto pr-4 custom-scrollbar-compact animate-in fade-in duration-300">
                <?php if (empty($sistemas_raiz)): ?>
            <div class="col-span-full text-center py-10 text-white/40 text-xs font-bold uppercase tracking-widest">
                ⚠️ NENHUM ACESSO LIBERADO PARA SEU PERFIL.
            </div>
       <?php else: ?>

            <?php
            // Converte as cores cadastradas no banco em cores para o efeito neon
            $cores_neon = [
                'bg-blue-600'    => '#22d3ee',
                'bg-amber-500'   => '#f59e0b',
                'bg-amber-600'   => '#d97706',
                'bg-emerald-500' => '#10b981',
                'bg-emerald-600' => '#059669',
                'bg-purple-500'  => '#a855f7',
                'bg-purple-600'  => '#9333ea',
                'bg-slate-500'   => '#64748b',
                'bg-slate-600'   => '#475569',
                'bg-slate-800'   => '#1e293b',
                'bg-red-500'     => '#ef4444',
                'bg-red-600'     => '#dc2626'
            ];
            ?>

            <?php foreach ($sistemas_raiz as $sys):
                $is_grupo = ($sys['url'] === '#');
                $cor_neon = $cores_neon[$sys['cor']] ?? '#22d3ee';
            ?>

                <?php if ($is_grupo):
                    $sub_json = isset($sistemas_filhos[$sys['id']])
                        ? json_encode(
                            $sistemas_filhos[$sys['id']],
                            JSON_HEX_APOS | JSON_HEX_QUOT
                        )
                        : '[]';
                ?>

                    <div
                        onclick='abrirPastaSistemas(
                            <?php echo json_encode($sys['nome']); ?>,
                            <?php echo $sub_json; ?>
                        )'
                        class="sistema-card sistema-card-neon"
                        style="--neon-cor: <?php echo $cor_neon; ?>;"
                        data-nome="<?php echo strtoupper(htmlspecialchars($sys['nome'], ENT_QUOTES, 'UTF-8')); ?>"
                        data-subitens='<?php echo htmlspecialchars($sub_json, ENT_QUOTES, 'UTF-8'); ?>'
                    >
                        <span class="sistema-card-neon__tipo">Módulo</span>
                        <span class="sistema-card-neon__status"></span>

                        <div class="sistema-card-neon__icone">
                            <?php echo $sys['icone']; ?>
                        </div>

                        <span class="sistema-card-neon__nome">
                            <?php echo htmlspecialchars($sys['nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                <?php else: ?>

                    <a
                        href="<?php echo htmlspecialchars($sys['url'], ENT_QUOTES, 'UTF-8'); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="sistema-card sistema-card-neon"
                        style="--neon-cor: <?php echo $cor_neon; ?>;"
                        data-nome="<?php echo strtoupper(htmlspecialchars($sys['nome'], ENT_QUOTES, 'UTF-8')); ?>"
                    >
                        <span class="sistema-card-neon__tipo">Sistema</span>

                        <div class="sistema-card-neon__icone">
                            <?php echo $sys['icone']; ?>
                        </div>

                        <span class="sistema-card-neon__nome">
                            <?php echo htmlspecialchars($sys['nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <div id="gridSistemasSub" class="hidden grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar-compact animate-in slide-in-from-right-5 duration-300"></div>

        <div class="mt-8 pt-4 border-t border-white/5 flex justify-between items-center text-[9px] font-bold text-white/20 uppercase tracking-widest">
            <span>Launchpad de Aplicações</span>
            <span>Comercial Souza Atacado</span>
        </div>
    </div>
</div>


<div id="modalAgendamento" class="fixed inset-0 z-[1100] hidden items-center justify-center p-4 backdrop-blur-md bg-navy-900/40">
    <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-4xl flex flex-col md:flex-row overflow-hidden animate-in zoom-in-95 duration-300">
        
        <div class="w-full md:w-1/2 bg-slate-50 p-8 border-r border-slate-100">
            <h3 class="text-navy-900 font-black text-lg uppercase mb-4 italic">Agenda do Dia</h3>
            <div id="lista-horarios-dia" class="space-y-3 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar-compact">
                <p class="text-slate-400 text-xs italic">Carregando compromissos...</p>
            </div>
        </div>

        <div class="w-full md:w-1/2 p-8">
            <button onclick="fecharAgendamento()" class="absolute top-6 right-6 text-slate-400 hover:text-navy-900 text-2xl">&times;</button>
            
            <div class="mb-6">
                <h3 class="text-navy-900 font-black text-xl uppercase italic">Reservar Horário</h3>
                <p class="text-slate-400 text-[10px] font-bold uppercase">Data: <span id="data-formatada" class="text-blue-600"></span></p>
            </div>

            <form id="formAgenda" class="space-y-4">
                <input type="hidden" name="id_evento" id="edit-id-evento" value="">
                <input type="hidden" name="data_evento" id="input-data-evento">
                
                <input type="text" name="titulo" required placeholder="Título da Reunião ou Evento" 
                    class="w-full bg-slate-50 border border-slate-100 rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-blue-500">

                <select name="local_sala" class="w-full bg-slate-50 border border-slate-100 rounded-xl p-3 text-sm">
                    <option value="GERAL">Aviso/Evento Geral</option>
                    <option value="SALA_01">Sala de Reunião P1</option>
                    <option value="SALA_02">Sala de Reunião P2</option>
                    <option value="SALA_03">Auditório P1</option>
                </select>

                <div class="flex items-center gap-2 px-2">
                    <input type="checkbox" id="dia_inteiro" name="dia_inteiro" value="1" onchange="toggleHoras(this.checked)" class="w-4 h-4 text-blue-600 rounded">
                    <label for="dia_inteiro" class="text-[11px] font-black text-slate-500 uppercase cursor-pointer">Evento de Dia Inteiro</label>
                </div>

                <div id="campos_hora" class="grid grid-cols-2 gap-4 transition-all duration-300">
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Início</label>
                        <input type="time" name="hora_inicio" id="h_inicio" class="w-full bg-slate-50 border border-slate-100 rounded-xl p-3 text-sm">
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Fim</label>
                        <input type="time" name="hora_fim" id="h_fim" class="w-full bg-slate-50 border border-slate-100 rounded-xl p-3 text-sm">
                    </div>
                </div>

                <?php if($_SESSION['is_admin']): ?>
                <div class="space-y-1">
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Visibilidade do Evento</label>
                    <select name="visibilidade" class="w-full bg-slate-50 border border-slate-100 rounded-xl p-3 text-[11px] font-bold">
                        <option value="PESSOAL">🔒 PESSOAL (SÓ EU VEJO)</option>
                        <option value="GERAL">🌍 PÚBLICO (TODOS VEEM)</option>
                    </select>
                </div>
                <?php endif; ?>

                <button id="btn-confirmar" type="submit" class="w-full bg-navy-900 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-all uppercase text-xs tracking-widest mt-2">
                    Confirmar Reserva 🚀
                </button>
            </form>
        </div>
    </div>
</div>

<div id="modalProjetos" class="fixed inset-0 z-[1200] hidden items-center justify-center p-4 backdrop-blur-xl bg-navy-900/60 transition-all duration-500">
    <div class="relative bg-navy-900 border border-white/10 w-full max-w-5xl rounded-[2rem] p-8 shadow-2xl animate-in zoom-in-95 duration-300 overflow-hidden flex flex-col max-h-[90vh]">
        
        <button onclick="fecharModalProjetos()" class="absolute top-5 right-6 text-white/30 hover:text-white transition-colors text-3xl font-light z-20">&times;</button>

        <div class="mb-8 text-left border-b border-white/5 pb-4 shrink-0">
            <h2 class="text-white text-xl font-black tracking-tighter uppercase italic">Status das Implantações</h2>
            <p class="text-blue-400 text-[10px] font-bold uppercase tracking-widest">Cronogramas e Gargalos de Sistemas</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 overflow-y-auto custom-scrollbar-compact pr-2 flex-1 pb-4">
            <?php if(!empty($projetos_ativos)): foreach ($projetos_ativos as $proj): ?>
            <div class="bg-slate-900 border border-white/5 rounded-2xl p-5 flex flex-col group hover:border-blue-500/50 transition-colors h-full">
                
                <div class="mb-4">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-white font-black italic uppercase leading-tight truncate pr-2"><?php echo mb_strtoupper($proj['nome_projeto'], 'UTF-8'); ?></h3>
                        <span class="text-emerald-400 font-black text-xl leading-none"><?php echo $proj['progresso']; ?>%</span>
                    </div>
                    <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-blue-500 h-1.5 rounded-full" style="width: <?php echo $proj['progresso']; ?>%"></div>
                    </div>
                </div>

                <div class="mt-auto space-y-3">
                    
                    <?php if (isset($proj['status_implantacao']) && $proj['status_implantacao'] === 'TRAVADO'): ?>
                        <div class="bg-red-500/20 rounded-xl p-3 border border-red-500/40 relative overflow-hidden animate-pulse">
                            <span class="text-red-500 font-black text-[9px] uppercase tracking-widest block mb-1 flex items-center gap-1">
                                ⚠️ EM ANDAMENTO
                            </span>
                            <p class="text-red-100 text-[10px] font-medium leading-tight line-clamp-2" title="<?php echo htmlspecialchars($proj['motivo_bloqueio']); ?>">
                                <?php echo $proj['motivo_bloqueio'] ?: 'Aguardando liberação.'; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="bg-slate-800/50 rounded-xl p-3 border border-white/5 text-center">
                        <p class="text-slate-500 text-[8px] font-black uppercase tracking-widest mb-2">Tempo Restante para Virada</p>
                        <div class="flex justify-center gap-2 text-white cronometro-dinamico" data-virada="<?php echo $proj['data_virada']; ?>">
                            <div class="text-center">
                                <span class="c-dias block font-black text-sm">00</span>
                                <span class="text-[7px] text-slate-500 uppercase">D</span>
                            </div>
                            <span class="text-slate-600">:</span>
                            <div class="text-center">
                                <span class="c-horas block font-black text-sm">00</span>
                                <span class="text-[7px] text-slate-500 uppercase">H</span>
                            </div>
                            <span class="text-slate-600">:</span>
                            <div class="text-center">
                                <span class="c-mins block font-black text-sm">00</span>
                                <span class="text-[7px] text-slate-500 uppercase">M</span>
                            </div>
                            <span class="text-blue-500">:</span>
                            <div class="text-center">
                                <span class="c-segs block font-black text-sm text-blue-400">00</span>
                                <span class="text-[7px] text-slate-500 uppercase">S</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

        <div id="tooltip-rapida" 
            style="display: none; position: fixed; z-index: 9999; background: #ffffff; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); max-width: 280px; pointer-events: none;">
        </div>

<script>
// ==========================================
// SCRIPTS GERAIS DA TELA
// ==========================================

let slideAtual = 0;
const totalSlides = <?php echo count($banners); ?>;
const container = document.getElementById('carrossel-container');
function moverCarrossel(direcao) {
if (totalSlides <= 1) return;
        slideAtual = (slideAtual + direcao + totalSlides) % totalSlides;
        container.style.transform = `translateX(-${slideAtual * 100}%)`;
    }
if (totalSlides > 1) { setInterval(() => moverCarrossel(1), 7000); }

    function desenharConexoesSistemas(grid) {
        if (!grid) return;

        // Remove a rede anterior antes de redesenhar
        const redeAnterior = grid.querySelector('.rede-sistemas-svg');

        if (redeAnterior) {
            redeAnterior.remove();
        }

        const cards = Array.from(
            grid.querySelectorAll(':scope > .sistema-card-neon')
        ).filter(card => card.style.display !== 'none');

        if (cards.length < 2) return;

        const estiloGrid = window.getComputedStyle(grid);
        const colunas = estiloGrid.gridTemplateColumns
            .split(' ')
            .filter(Boolean)
            .length;

        const largura = grid.scrollWidth;
        const altura = grid.scrollHeight;

        const svgNS = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(svgNS, 'svg');

        svg.classList.add('rede-sistemas-svg');
        svg.setAttribute('width', largura);
        svg.setAttribute('height', altura);
        svg.setAttribute('viewBox', `0 0 ${largura} ${altura}`);

        const gridRect = grid.getBoundingClientRect();

        function posicaoCard(card) {
            const rect = card.getBoundingClientRect();

            return {
                esquerda: rect.left - gridRect.left + grid.scrollLeft,
                direita: rect.right - gridRect.left + grid.scrollLeft,
                topo: rect.top - gridRect.top + grid.scrollTop,
                base: rect.bottom - gridRect.top + grid.scrollTop,
                centroX: rect.left - gridRect.left + grid.scrollLeft + rect.width / 2,
                centroY: rect.top - gridRect.top + grid.scrollTop + rect.height / 2
            };
        }

        function criarCaminho(d, roxo = false) {
            const path = document.createElementNS(svgNS, 'path');

            path.setAttribute('d', d);
            path.classList.add('rede-sistemas-linha');

            if (roxo) {
                path.classList.add('rede-sistemas-linha-roxa');
            }

            svg.appendChild(path);
        }

        function criarPonto(x, y) {
            const ponto = document.createElementNS(svgNS, 'circle');

            ponto.setAttribute('cx', x);
            ponto.setAttribute('cy', y);
            ponto.setAttribute('r', 3);
            ponto.classList.add('rede-sistemas-ponto');

            svg.appendChild(ponto);
        }

        cards.forEach((card, indice) => {
            const atual = posicaoCard(card);

            // Liga o card ao próximo card da mesma linha
            const existeCardDireita =
                (indice + 1) < cards.length &&
                ((indice + 1) % colunas !== 0);

            if (existeCardDireita) {
                const direita = posicaoCard(cards[indice + 1]);
                const meioX = (atual.direita + direita.esquerda) / 2;

                criarCaminho(
                    `M ${atual.direita} ${atual.centroY}
                    H ${meioX}
                    V ${direita.centroY}
                    H ${direita.esquerda}`,
                    indice % 2 !== 0
                );

                criarPonto(meioX, atual.centroY);
            }

            // Liga o card ao card da linha inferior
            const indiceInferior = indice + colunas;

            if (indiceInferior < cards.length) {
                const inferior = posicaoCard(cards[indiceInferior]);
                const meioY = (atual.base + inferior.topo) / 2;

                criarCaminho(
                    `M ${atual.centroX} ${atual.base}
                    V ${meioY}
                    H ${inferior.centroX}
                    V ${inferior.topo}`,
                    indice % 2 === 0
                );

                criarPonto(atual.centroX, meioY);
            }
        });

        grid.prepend(svg);
    }


function abrirPastaSistemas(nomePasta, subitens) {
    const gridPrincipal = document.getElementById('gridSistemasPrincipal');
    const gridSub = document.getElementById('gridSistemasSub');
    const btnVoltar = document.getElementById('btnVoltarModal');
    const titulo = document.getElementById('tituloModalSistemas');
    const subtitulo = document.getElementById('subtituloModalSistemas');

    // Cores do banco convertidas para hexadecimal
    const coresNeon = {
        'bg-blue-600': '#22d3ee',
        'bg-amber-500': '#f59e0b',
        'bg-amber-600': '#d97706',
        'bg-emerald-500': '#10b981',
        'bg-emerald-600': '#059669',
        'bg-purple-500': '#a855f7',
        'bg-purple-600': '#9333ea',
        'bg-slate-500': '#64748b',
        'bg-slate-600': '#475569',
        'bg-slate-800': '#1e293b',
        'bg-red-500': '#ef4444',
        'bg-red-600': '#dc2626'
    };

    // Esconde os sistemas principais e mostra os filhos
    gridPrincipal.classList.add('hidden');
    gridSub.classList.remove('hidden');
    btnVoltar.classList.remove('hidden');

    // Atualiza o cabeçalho
    titulo.innerText = nomePasta;
    subtitulo.innerText = 'Módulo interno • Aplicações liberadas para seu perfil';

    // Caso o módulo ainda não tenha sistemas vinculados
    if (!subitens || subitens.length === 0) {
        gridSub.innerHTML = `
            <div class="col-span-full text-center py-12">
                <span class="text-3xl block mb-3">📭</span>
                <p class="text-[10px] text-white/30 font-black uppercase tracking-widest">
                    Nenhuma aplicação vinculada a este módulo.
                </p>
            </div>
        `;
        return;
    }

    // Monta os sistemas filhos no mesmo padrão neon
    gridSub.innerHTML = subitens.map(item => {
        const corNeon = coresNeon[item.cor] || '#22d3ee';

        return `
            <a
                href="${item.url}"
                target="_blank"
                rel="noopener noreferrer"
                class="sistema-card-neon"
                style="--neon-cor: ${corNeon};"
            >
                <span class="sistema-card-neon__tipo">Sistema</span>

                <div class="sistema-card-neon__icone">
                    ${item.icone}
                </div>

                <span class="sistema-card-neon__nome">
                    ${item.nome}
                </span>
            </a>
        `;
    }).join('');

    requestAnimationFrame(function () {
    desenharConexoesSistemas(gridSub);
});
}

function exibirPrincipalSistemas() {
    const gridPrincipal = document.getElementById('gridSistemasPrincipal');
    const gridSub = document.getElementById('gridSistemasSub');

    gridPrincipal.classList.remove('hidden');
    gridSub.classList.add('hidden');

    document.getElementById('btnVoltarModal').classList.add('hidden');
    document.getElementById('tituloModalSistemas').innerText = 'Sistemas de Navegação';
    document.getElementById('subtituloModalSistemas').innerText = 'Selecione o sistema desejado';

    requestAnimationFrame(function () {
        desenharConexoesSistemas(gridPrincipal);
    });
}

function abrirModalSistemas() {
    const modal = document.getElementById('modalSistemas');
    const gridPrincipal = document.getElementById('gridSistemasPrincipal');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    // Aguarda o modal ficar visível e os cards receberem suas posições
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            desenharConexoesSistemas(gridPrincipal);
        });
    });
}

function fecharModalSistemas() {
    const modal = document.getElementById('modalSistemas');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
}

// SCRIPTS DO MODAL DE PROJETOS
function abrirModalProjetos() {
    document.getElementById('modalProjetos').classList.remove('hidden');
    document.getElementById('modalProjetos').classList.add('flex');
    document.body.style.overflow = 'hidden';
}
function fecharModalProjetos() {
    document.getElementById('modalProjetos').classList.add('hidden');
    document.getElementById('modalProjetos').classList.remove('flex');
    document.body.style.overflow = 'auto';
}

document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") {
        fecharModalSistemas();
        fecharModalProjetos();
        fecharModalAniversariantes();
    }
});

function abrirModalAniversariantes() {
    const modal = document.getElementById('modalAniversariantes');
    modal.classList.remove('hidden'); modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}
function fecharModalAniversariantes() {
    const modal = document.getElementById('modalAniversariantes');
    modal.classList.add('hidden'); modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
}

const listaAniversariantes = <?php echo json_encode($aniversariantes); ?>;
let indexAtual = 0;
function trocarAniversariante() {
    const container = document.getElementById('ticker-aniversariante');
    const nomeEl = document.getElementById('nome-aniv');
    const dataEl = document.getElementById('data-aniv');
    container.style.opacity = '0';
    container.style.transform = 'translateY(-15px)';
    setTimeout(() => {
        indexAtual = (indexAtual + 1) % listaAniversariantes.length;
        nomeEl.innerText = listaAniversariantes[indexAtual].nome;
        dataEl.innerText = listaAniversariantes[indexAtual].data;
        container.style.transform = 'translateY(15px)';
        setTimeout(() => {
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        }, 50);
    }, 700);
}
if(listaAniversariantes.length > 0) { setInterval(trocarAniversariante, 5000); }

function carregarCalendario(mes, ano) {
    const container = document.getElementById('calendario-ajax');
    container.style.opacity = '0.5';

    fetch(`api/get_calendario.php?mes=${mes}&ano=${ano}`)
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
            container.style.opacity = '1';
        })
        .catch(err => console.error('Erro ao carregar calendário:', err));
}

document.addEventListener('DOMContentLoaded', function() {
    carregarCalendario(<?= date('n') ?>, <?= date('Y') ?>);
    atualizarPresenca();
});

function abrirAgendamento(data) {
    document.getElementById('input-data-evento').value = data;
    document.getElementById('data-formatada').innerText = data.split('-').reverse().join('/');
    
    fetch(`api/get_horarios_dia.php?data=${data}`)
        .then(res => res.text())
        .then(html => {
            document.getElementById('lista-horarios-dia').innerHTML = html;
        });

    document.getElementById('modalAgendamento').classList.remove('hidden');
    document.getElementById('modalAgendamento').classList.add('flex');
}

document.getElementById('formAgenda').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('api/salvar_evento.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            fecharAgendamento();
            const dataSel = document.getElementById('input-data-evento').value.split('-');
            carregarCalendario(parseInt(dataSel[1]), parseInt(dataSel[0]));
            this.reset();
        } else {
            alert(data.error); 
        }
    });
};

function excluirEvento(id, data) {
    if(!confirm('Deseja realmente cancelar este agendamento?')) return;

    fetch('api/excluir_evento.php', {
        method: 'POST',
        body: new URLSearchParams({ 'id': id })
    })
    .then(res => res.json())
    .then(dataRes => {
        if(dataRes.success) {
            abrirAgendamento(data);
            const d = data.split('-');
            carregarCalendario(parseInt(d[1]), parseInt(d[0]));
        } else {
            alert('Erro: ' + dataRes.error);
        }
    });
}

function toggleHoras(marcado) {
    const div = document.getElementById('campos_hora');
    const inputInicio = document.querySelector('input[name="hora_inicio"]');
    const inputFim = document.querySelector('input[name="hora_fim"]');
    
    if (marcado) {
        div.style.opacity = '0.5';
        div.style.pointerEvents = 'none';
        inputInicio.value = '08:00';
        inputFim.value = '17:48';
        inputInicio.required = false;
        inputFim.required = false;
    } else {
        div.style.opacity = '1';
        div.style.pointerEvents = 'all';
        inputInicio.value = '';
        inputFim.value = '';
        inputInicio.required = true;
        inputFim.required = true;
    }
}

function prepararEdicao(evento) {
    document.getElementById('edit-id-evento').value = evento.id;
    document.getElementsByName('titulo')[0].value = evento.titulo;
    document.getElementsByName('local_sala')[0].value = evento.local_sala;
    
    if (evento.hora_inicio && evento.hora_fim) {
        document.getElementsByName('hora_inicio')[0].value = evento.hora_inicio;
        document.getElementsByName('hora_fim')[0].value = evento.hora_fim;
        document.getElementById('dia_inteiro').checked = false;
        toggleHoras(false);
    } else {
        document.getElementById('dia_inteiro').checked = true;
        toggleHoras(true);
    }

    document.getElementById('btn-confirmar').innerHTML = "Salvar Alterações 💾";
}

function fecharAgendamento() {
    document.getElementById('modalAgendamento').classList.add('hidden');
    document.getElementById('formAgenda').reset();
    document.getElementById('edit-id-evento').value = "";
    document.getElementById('btn-confirmar').innerHTML = "Confirmar Reserva 🚀";
}

function atualizarPresenca() {
    fetch('lista_online.php')
        .then(response => response.text())
        .then(html => { document.getElementById('painel-presenca').innerHTML = html; })
        .catch(err => console.warn('Erro ao carregar lista de presença.'));
}
setInterval(atualizarPresenca, 30000);

function toggleCurtida(comunicadoId, btnElement) {
    const fd = new FormData();
    fd.append('acao', 'curtir');
    fd.append('comunicado_id', comunicadoId);

    fetch('api/feed_interacoes.php', { method: 'POST', body: fd })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'sucesso') {
            btnElement.querySelector('.contador-curtidas').innerText = data.total;
            btnElement.querySelector('.icone-coracao').innerText = data.acao === 'curtiu' ? '❤️' : '🤍';
        }
    })
    .catch(err => console.error('Erro ao curtir:', err));
}

function toggleComentarios(comunicadoId) {
    const divComentarios = document.getElementById(`comentarios-post-${comunicadoId}`);
    divComentarios.classList.toggle('hidden');
    if (!divComentarios.classList.contains('hidden')) {
        carregarComentarios(comunicadoId, divComentarios.querySelector('.lista-comentarios'));
    }
}

function carregarComentarios(comunicadoId, containerElement) {
    const fd = new FormData();
    fd.append('acao', 'listar_comentarios');
    fd.append('comunicado_id', comunicadoId);

    fetch('api/feed_interacoes.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'sucesso') {
            const postCard = containerElement.closest('.bg-white');
            postCard.querySelector('.contador-comentarios').innerText = data.comentarios.length;

            if (data.comentarios.length === 0) {
                containerElement.innerHTML = '<p class="text-[10px] text-slate-400 text-center italic py-2">Seja o primeiro a comentar! 💬</p>';
                return;
            }

            containerElement.innerHTML = data.comentarios.map(c => `
                <div class="bg-slate-50 rounded-2xl rounded-tl-none p-3 shadow-sm border border-slate-100">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-[9px] font-black text-navy-900 uppercase">${c.nome}</span>
                        <span class="text-[8px] text-slate-400 font-bold">${c.data_hora}</span>
                    </div>
                    <p class="text-xs text-slate-600 font-medium">${c.comentario}</p>
                </div>
            `).join('');
            
            containerElement.scrollTop = containerElement.scrollHeight;
        }
    });
}

function enviarComentario(e, comunicadoId, formElement) {
    e.preventDefault();
    const input = formElement.querySelector('input[name="texto_comentario"]');
    const texto = input.value.trim();
    if(!texto) return;

    const fd = new FormData();
    fd.append('acao', 'comentar');
    fd.append('comunicado_id', comunicadoId);
    fd.append('comentario', texto);

    input.value = ''; 
    input.disabled = true;

    fetch('api/feed_interacoes.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        input.disabled = false;
        if (data.status === 'sucesso') {
            const container = document.getElementById(`comentarios-post-${comunicadoId}`).querySelector('.lista-comentarios');
            carregarComentarios(comunicadoId, container);
        }
    })
    .catch(() => input.disabled = false);
}

// MOTOR DE CRONÔMETROS MÚLTIPLOS (Para o Modal e para a Barra Lateral)
setInterval(function() {
    const agora = new Date().getTime();
    
    document.querySelectorAll('.cronometro-dinamico').forEach(el => {
        const dataAlvo = new Date(el.getAttribute('data-virada')).getTime();
        const distancia = dataAlvo - agora;

        if (distancia < 0) {
            el.innerHTML = "<div class='w-full text-emerald-400 font-black text-sm uppercase tracking-widest animate-pulse py-1 text-center'>IMPLANTADO! 🚀</div>";
            el.classList.remove('cronometro-dinamico');
            return;
        }

        el.querySelector('.c-dias').innerText = Math.floor(distancia / (1000 * 60 * 60 * 24)).toString().padStart(2, '0');
        el.querySelector('.c-horas').innerText = Math.floor((distancia % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)).toString().padStart(2, '0');
        el.querySelector('.c-mins').innerText = Math.floor((distancia % (1000 * 60 * 60)) / (1000 * 60)).toString().padStart(2, '0');
        el.querySelector('.c-segs').innerText = Math.floor((distancia % (1000 * 60)) / 1000).toString().padStart(2, '0');
    });
}, 1000);

function limparBuscaSistemas() {
    const input = document.getElementById('inputBuscaSistemas');

    input.value = '';
    filtrarSistemas();
    input.focus();

    requestAnimationFrame(function () {
        desenharConexoesSistemas(
            document.getElementById('gridSistemasPrincipal')
        );
    });
}

function normalizarTextoBusca(texto) {
    return String(texto)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toUpperCase()
        .trim();
}

 function filtrarSistemas() {
    const input = document.getElementById('inputBuscaSistemas');
    const gridPrincipal = document.getElementById('gridSistemasPrincipal');
    const filtro = normalizarTextoBusca(input.value);

    const coresNeon = {
        'bg-blue-600': '#22d3ee',
        'bg-amber-500': '#f59e0b',
        'bg-amber-600': '#d97706',
        'bg-emerald-500': '#10b981',
        'bg-emerald-600': '#059669',
        'bg-purple-500': '#a855f7',
        'bg-purple-600': '#9333ea',
        'bg-slate-500': '#64748b',
        'bg-slate-600': '#475569',
        'bg-slate-800': '#1e293b',
        'bg-red-500': '#ef4444',
        'bg-red-600': '#dc2626'
    };

    // Remove as linhas anteriores antes de remontar o resultado
    const redeAnterior = gridPrincipal.querySelector('.rede-sistemas-svg');

    if (redeAnterior) {
        redeAnterior.remove();
    }

    // Remove resultados e mensagens criados pela pesquisa anterior
    gridPrincipal
        .querySelectorAll('.card-dinamico-busca, .mensagem-busca-vazia')
        .forEach(elemento => elemento.remove());

    // Seleciona somente os cards originais da grade principal
    const cardsOriginais = Array.from(
        gridPrincipal.querySelectorAll(':scope > .sistema-card')
    );

    // Pesquisa vazia: restaura todos os cards
    if (filtro === '') {
        cardsOriginais.forEach(card => {
            card.style.display = '';
        });

        requestAnimationFrame(function () {
            desenharConexoesSistemas(gridPrincipal);
        });

        return;
    }

    // Esconde os cards originais antes de pesquisar
    cardsOriginais.forEach(card => {
        card.style.display = 'none';
    });

    let totalEncontrado = 0;

    cardsOriginais.forEach(card => {
        const nomeSistema = normalizarTextoBusca(
            card.getAttribute('data-nome') || ''
        );

        const subitensJson = card.getAttribute('data-subitens') || '';

        // Se o próprio sistema ou módulo corresponder à pesquisa
        if (nomeSistema.includes(filtro)) {
            card.style.display = '';
            totalEncontrado++;
        }

        // Pesquisa sistemas que estão dentro dos módulos
        if (subitensJson) {
            try {
                const subitens = JSON.parse(subitensJson);

                subitens.forEach(sub => {
                    const nomeSubitem = normalizarTextoBusca(sub.nome || '');

                    if (!nomeSubitem.includes(filtro)) {
                        return;
                    }

                    const corNeon = coresNeon[sub.cor] || '#22d3ee';

                    const novoCard = document.createElement('a');

                    novoCard.href = sub.url;
                    novoCard.target = '_blank';
                    novoCard.rel = 'noopener noreferrer';

                    novoCard.className =
                        'sistema-card-neon card-dinamico-busca';

                    novoCard.style.setProperty('--neon-cor', corNeon);

                    novoCard.innerHTML = `
                        <span class="sistema-card-neon__tipo">
                            Sistema
                        </span>

                        <div class="sistema-card-neon__icone">
                            ${sub.icone || '🖥️'}
                        </div>

                        <span class="sistema-card-neon__nome">
                            ${sub.nome || 'Sistema'}
                        </span>
                    `;

                    gridPrincipal.appendChild(novoCard);
                    totalEncontrado++;
                });
            } catch (erro) {
                console.error(
                    'Erro ao interpretar os sistemas do módulo:',
                    erro
                );
            }
        }
    });

    // Quando não encontrar nenhum resultado
    if (totalEncontrado === 0) {
        const mensagem = document.createElement('div');

        mensagem.className =
            'mensagem-busca-vazia col-span-full text-center py-12';

        mensagem.innerHTML = `
            <span class="text-3xl block mb-3">🔍</span>

            <p class="text-white/60 text-xs font-black uppercase tracking-widest">
                Nenhum sistema encontrado
            </p>

            <p class="text-white/30 text-[10px] mt-2">
                Tente pesquisar usando outro nome.
            </p>
        `;

        gridPrincipal.appendChild(mensagem);
        return;
    }

    // Aguarda os resultados aparecerem e desenha somente as conexões necessárias
    requestAnimationFrame(function () {
        desenharConexoesSistemas(gridPrincipal);
    });
}
   
</script>

<?php
// Consulta rápida para ver se o usuário já fez o tour
$stmt_tut = $pdo_intra->prepare("SELECT tutorial_visto FROM usuarios_permissoes WHERE usuario_id = ?");
$stmt_tut->execute([$user_id_logado]);
$tut = $stmt_tut->fetch();
$tutorial_visto = $tut ? (int)$tut['tutorial_visto'] : 0;
?>

<?php include 'includes/footer.php'; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js"></script>

<style>
    /* Oculta o controle de avanço na etapa de interação obrigatória */
    .driver-popover-next-btn.ocultar-botao {
        display: none !important;
    }
    
    /* Configuração estrutural do Mascote Interno para Ganho de Espaço */
    .container-mascote-tour {
        display: flex;
        align-items: center;
        gap: 16px;
        min-height: 120px;
    }
    .mascote-tour-interno {
        height: 130px; /* Aumentado consideravelmente para dar destaque */
        width: auto;
        flex-shrink: 0;
        object-fit: contain;
    }
    .texto-tour-interno {
        flex-1;
        font-size: 13px;
        line-height: 1.5;
    }

    /* Altera o fundo do balão para cinza claro, destacando as luvas brancas */
    .driver-popover {
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
    }

    /* Garante que a setinha do balão também mude de cor para acompanhar o fundo */
    .driver-popover-arrow-side-left { border-right-color: #f8fafc !important; }
    .driver-popover-arrow-side-right { border-left-color: #f8fafc !important; }
    .driver-popover-arrow-side-top { border-bottom-color: #f8fafc !important; }
    .driver-popover-arrow-side-bottom { border-top-color: #f8fafc !important; }
        /* =========================================================
        MODAL DE SISTEMAS — VISUAL TECNOLÓGICO NEON
        ========================================================= */

        #modalSistemas {
            background:
                radial-gradient(circle at 15% 20%, rgba(34, 211, 238, 0.12), transparent 30%),
                radial-gradient(circle at 85% 75%, rgba(168, 85, 247, 0.14), transparent 32%),
                rgba(2, 6, 23, 0.82);
        }

        #modalSistemas .modal-sistemas-painel {
            position: relative;
            background:
                linear-gradient(145deg, rgba(15, 23, 42, 0.98), rgba(2, 6, 23, 0.98));
            border: 1px solid rgba(103, 232, 249, 0.22);
            box-shadow:
                0 0 0 1px rgba(168, 85, 247, 0.08),
                0 0 35px rgba(34, 211, 238, 0.10),
                0 30px 80px rgba(0, 0, 0, 0.55);
        }

        #modalSistemas .modal-sistemas-painel::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            border-radius: inherit;
            background:
                linear-gradient(90deg, transparent 49%, rgba(34, 211, 238, 0.025) 50%, transparent 51%),
                linear-gradient(0deg, transparent 49%, rgba(168, 85, 247, 0.025) 50%, transparent 51%);
            background-size: 40px 40px;
            mask-image: linear-gradient(to bottom, black, transparent 80%);
        }

        #modalSistemas .modal-sistemas-header {
            position: relative;
            z-index: 2;
        }

        #inputBuscaSistemas {
            background: rgba(15, 23, 42, 0.78);
            border: 1px solid rgba(34, 211, 238, 0.20);
            box-shadow: inset 0 0 16px rgba(34, 211, 238, 0.03);
        }

        #inputBuscaSistemas:focus {
            border-color: rgba(34, 211, 238, 0.75);
            box-shadow:
                0 0 0 3px rgba(34, 211, 238, 0.10),
                0 0 22px rgba(34, 211, 238, 0.12);
        }

        /* Card principal, card filho e resultado da pesquisa */
        .sistema-card-neon {
            --neon-cor: #22d3ee;

            position: relative;
            min-height: 126px;
            padding: 14px 10px;
            overflow: hidden;
            isolation: isolate;
            cursor: pointer;
            text-decoration: none;

            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;

            border-radius: 16px;
            border: 1px solid color-mix(in srgb, var(--neon-cor) 32%, transparent);
            background:
                linear-gradient(145deg, rgba(30, 41, 59, 0.88), rgba(15, 23, 42, 0.94));

            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.04),
                0 10px 25px rgba(0, 0, 0, 0.24);

            transition:
                transform 0.25s ease,
                border-color 0.25s ease,
                box-shadow 0.25s ease,
                background 0.25s ease;
        }

        .sistema-card-neon::before {
            content: "";
            position: absolute;
            width: 90px;
            height: 90px;
            top: -50px;
            right: -45px;
            z-index: -1;
            border-radius: 999px;
            background: var(--neon-cor);
            opacity: 0.12;
            filter: blur(22px);
            transition: opacity 0.25s ease;
        }

        .sistema-card-neon::after {
            content: "";
            position: absolute;
            left: 15%;
            right: 15%;
            bottom: 0;
            height: 1px;
            background: linear-gradient(
                90deg,
                transparent,
                var(--neon-cor),
                transparent
            );
            opacity: 0.55;
        }

        .sistema-card-neon:hover {
            transform: translateY(-5px);
            border-color: color-mix(in srgb, var(--neon-cor) 75%, white 8%);
            background:
                linear-gradient(145deg, rgba(30, 41, 59, 0.98), rgba(15, 23, 42, 1));

            box-shadow:
                0 0 0 1px color-mix(in srgb, var(--neon-cor) 18%, transparent),
                0 0 24px color-mix(in srgb, var(--neon-cor) 20%, transparent),
                0 18px 34px rgba(0, 0, 0, 0.36);
        }

        .sistema-card-neon:hover::before {
            opacity: 0.25;
        }

        /* Área do ícone */
        .sistema-card-neon__icone {
            position: relative;
            width: 52px;
            height: 52px;
            flex-shrink: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 14px;
            border: 1px solid color-mix(in srgb, var(--neon-cor) 60%, transparent);
            background:
                radial-gradient(circle, color-mix(in srgb, var(--neon-cor) 16%, transparent), transparent 68%),
                rgba(2, 6, 23, 0.72);

            color: white;
            font-size: 24px;

            box-shadow:
                inset 0 0 18px color-mix(in srgb, var(--neon-cor) 10%, transparent),
                0 0 16px color-mix(in srgb, var(--neon-cor) 12%, transparent);

            transition:
                transform 0.25s ease,
                box-shadow 0.25s ease;
        }

        .sistema-card-neon:hover .sistema-card-neon__icone {
            transform: scale(1.08);
            box-shadow:
                inset 0 0 22px color-mix(in srgb, var(--neon-cor) 18%, transparent),
                0 0 22px color-mix(in srgb, var(--neon-cor) 28%, transparent);
        }

        /* Nome do sistema */
        .sistema-card-neon__nome {
            position: relative;
            z-index: 2;
            margin-top: 11px;

            color: rgba(226, 232, 240, 0.78);
            font-size: 9px;
            font-weight: 800;
            line-height: 1.2;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.025em;

            transition: color 0.25s ease;
        }

        .sistema-card-neon:hover .sistema-card-neon__nome {
            color: #ffffff;
        }

        /* Indicador das pastas */
        .sistema-card-neon__status {
            position: absolute;
            top: 9px;
            right: 9px;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--neon-cor);
            box-shadow: 0 0 10px var(--neon-cor);
        }

        .sistema-card-neon__tipo {
            position: absolute;
            top: 8px;
            left: 9px;

            color: color-mix(in srgb, var(--neon-cor) 80%, white);
            font-size: 7px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        /* Ajustes para telas menores */
        @media (max-width: 640px) {
            .sistema-card-neon {
                min-height: 112px;
                padding: 12px 7px;
            }

            .sistema-card-neon__icone {
                width: 46px;
                height: 46px;
                font-size: 21px;
            }

            .sistema-card-neon__nome {
                font-size: 8px;
            }
        }

       /* Rede de conexões entre os cards */
        #gridSistemasPrincipal,
        #gridSistemasSub {
            position: relative;
            isolation: isolate;
        }

        .rede-sistemas-svg {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 0;
            overflow: visible;
            pointer-events: none;
        }
        .rede-sistemas-linha {
            fill: none;
            stroke: #22d3ee;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 7 7;
            opacity: 0.72;
            animation: redeSistemasMovimento 14s linear infinite;
        }

        .rede-sistemas-linha-roxa {
            stroke: #a855f7;
        }

        .rede-sistemas-ponto {
            fill: #67e8f9;
            opacity: 0.95;
        }
      
        #gridSistemasPrincipal > .sistema-card-neon,
        #gridSistemasSub > .sistema-card-neon {
            position: relative;
            z-index: 2;
        }

        @keyframes redeSistemasMovimento {
            from {
                stroke-dashoffset: 0;
            }

            to {
                stroke-dashoffset: -100;
            }
        }

        @media (max-width: 640px) {
            .rede-sistemas-linha {
                opacity: 0.40;
                stroke-width: 1.5;
            }
        }

        /* =========================================================
        PESQUISA TECNOLÓGICA DE SISTEMAS
        ========================================================= */

        .pesquisa-sistemas-wrapper {
            position: relative;
            z-index: 35;
        }

        .pesquisa-sistemas {
            position: relative;
            width: 290px;
            height: 42px;

            display: flex;
            align-items: center;

            border: 1px solid rgba(34, 211, 238, 0.35);
            border-radius: 12px;

            background:
                linear-gradient(145deg, rgba(15, 23, 42, 0.94), rgba(2, 6, 23, 0.94));

            box-shadow:
                inset 0 0 18px rgba(34, 211, 238, 0.035),
                0 0 0 1px rgba(168, 85, 247, 0.03);

            transition:
                border-color 0.25s ease,
                box-shadow 0.25s ease,
                transform 0.25s ease;
        }

        .pesquisa-sistemas:focus-within {
            border-color: rgba(34, 211, 238, 0.9);
            box-shadow:
                0 0 0 3px rgba(34, 211, 238, 0.08),
                0 0 22px rgba(34, 211, 238, 0.15),
                inset 0 0 20px rgba(34, 211, 238, 0.05);
            transform: translateY(-1px);
        }

        .pesquisa-sistemas::after {
            content: "";
            position: absolute;
            left: 18%;
            right: 18%;
            bottom: -1px;
            height: 1px;

            background: linear-gradient(
                90deg,
                transparent,
                #22d3ee,
                #a855f7,
                transparent
            );

            opacity: 0.8;
        }

        .pesquisa-sistemas__icone {
            width: 18px;
            height: 18px;
            margin-left: 13px;
            flex-shrink: 0;
            color: #67e8f9;
        }

        .pesquisa-sistemas__icone svg {
            width: 100%;
            height: 100%;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
        }

        .pesquisa-sistemas input {
            width: 100%;
            height: 100%;
            padding: 0 38px 0 11px;

            color: #f8fafc;
            font-size: 11px;
            font-weight: 600;

            border: none;
            outline: none;
            background: transparent;
        }

        .pesquisa-sistemas input::placeholder {
            color: rgba(148, 163, 184, 0.65);
        }

        .pesquisa-sistemas__limpar {
            position: absolute;
            top: 50%;
            right: 9px;
            transform: translateY(-50%);

            width: 25px;
            height: 25px;

            display: flex;
            align-items: center;
            justify-content: center;

            color: rgba(148, 163, 184, 0.65);
            font-size: 18px;
            line-height: 1;

            border: 1px solid transparent;
            border-radius: 7px;
            background: transparent;

            transition: all 0.2s ease;
        }

        .pesquisa-sistemas__limpar:hover {
            color: white;
            border-color: rgba(168, 85, 247, 0.35);
            background: rgba(168, 85, 247, 0.12);
        }

        @media (max-width: 640px) {
            .pesquisa-sistemas {
                width: 100%;
            }

            .pesquisa-sistemas-wrapper {
                width: 100%;
            }
        }

        #gridSistemasPrincipal,
        #gridSistemasSub {
            overflow-x: hidden !important;
        }

</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tutorialVisto = <?php echo (int)$tutorial_visto; ?>;

    if (tutorialVisto === 0) {
        const driver = window.driver.js.driver;
        let chatObserver = null;

        const driverObj = driver({
            showProgress: true,
            animate: true,
            nextBtnText: 'Próximo ➔',
            prevBtnText: '⬅ Voltar',
            doneBtnText: 'Começar! 🚀', // Botão final alterado
            
            onDestroyStarted: () => {
                if (chatObserver) chatObserver.disconnect();
                
                // Agora o último passo é o índice 7 (8ª tela)
                if (driverObj.getActiveIndex() === 7) {
                    marcarTutorialComoVisto();
                }
                
                driverObj.destroy();
            },
            steps: [
                { 
                    popover: { 
                        title: 'Bem-vindo à Intranet! 🎉', 
                        description: `
                            <div class="container-mascote-tour">
                                <img src="assets/1.png" class="mascote-tour-interno">
                                <div class="texto-tour-interno">
                                    Apresentação técnica das ferramentas de produtividade da plataforma.<br><br>
                                    <b>Nota:</b> Para desativar este guia nos próximos acessos, conclua o passo a passo.
                                </div>
                            </div>`
                    } 
                },
                { 
                    element: '#tour-inicio', 
                    popover: { 
                        title: '🏠 Painel Central', 
                        description: `
                            <div class="container-mascote-tour">
                                <img src="assets/3.png" class="mascote-tour-interno">
                                <div class="texto-tour-interno">
                                    Centralização de comunicados internos, murais institucionais e cronogramas de atividades corporativas.
                                </div>
                            </div>`, 
                        side: "right", 
                        align: 'start' 
                    } 
                },
                { 
                    element: '#tour-matriz', 
                    popover: { 
                        title: '📞 Matriz de Comunicação', 
                        description: `
                            <div class="container-mascote-tour">
                                <img src="assets/1.png" class="mascote-tour-interno">
                                <div class="texto-tour-interno">
                                    Consulta unificada de contatos internos, listagem oficial de ramais, e-mails e telefones de colaboradores.
                                </div>
                            </div>`, 
                        side: "right", 
                        align: 'start' 
                    } 
                },
                { 
                    element: '#tour-cursos', 
                    popover: { 
                        title: '🎓 Capacitação Profissional', 
                        description: `
                            <div class="container-mascote-tour">
                                <img src="assets/1.png" class="mascote-tour-interno">
                                <div class="texto-tour-interno">
                                    Conexão direta com a Academia Winthor, centralizando as trilhas de aprendizado vinculadas ao setor.
                                </div>
                            </div>`, 
                        side: "right", 
                        align: 'start' 
                    } 
                },
                { 
                    element: '#tour-documentacao', 
                    popover: { 
                        title: '📂 Repositório de Documentos', 
                        description: `
                            <div class="container-mascote-tour">
                                <img src="assets/3.png" class="mascote-tour-interno">
                                <div class="texto-tour-interno">
                                    Central de arquivos estruturada com validação automatizada de permissões por departamento.
                                </div>
                            </div>`, 
                        side: "right", 
                        align: 'start' 
                    } 
                },
                { 
                    element: '#tour-btn-chat', 
                    popover: { 
                        title: '💬 Comunicação Instantânea', 
                        description: `
                            <div class="container-mascote-tour">
                                <img src="assets/1.png" class="mascote-tour-interno">
                                <div class="texto-tour-interno">
                                    👉 <b>CLIQUE NO ÍCONE DO CHAT PARA CONTINUAR</b><br><br>
                                    É obrigatório realizar a abertura do Messenger corporativo no botão indicado ao lado.
                                </div>
                            </div>`, 
                        side: "left", 
                        align: 'end' 
                    },
                    onHighlighted: () => {
                        const nextBtn = document.querySelector('.driver-popover-next-btn');
                        if (nextBtn) nextBtn.style.display = 'none';

                        const janelaChat = document.getElementById('janela-chat');
                        if (janelaChat) {
                            chatObserver = new MutationObserver((mutations) => {
                                mutations.forEach((mutation) => {
                                    if (mutation.attributeName === 'class' && !janelaChat.classList.contains('hidden')) {
                                        chatObserver.disconnect(); 
                                        setTimeout(() => {
                                            driverObj.moveNext();
                                        }, 500); 
                                    }
                                });
                            });
                            chatObserver.observe(janelaChat, { attributes: true });
                        }
                    },
                    onDeselected: () => {
                        const nextBtn = document.querySelector('.driver-popover-next-btn');
                        if (nextBtn) nextBtn.style.display = '';
                    }
                },
                { 
                    element: '#janela-chat', 
                    popover: { 
                        title: 'Interface Operacional', 
                        description: `
                            <div class="container-mascote-tour">
                                <img src="assets/4.png" class="mascote-tour-interno">
                                <div class="texto-tour-interno">
                                    Módulo de chat ativo para interações em tempo real entre colaboradores e canais departamentais.
                                </div>
                            </div>`, 
                        side: "left", 
                        align: 'start' 
                    } 
                },
                // NOVO PASSO: Celebração e Conclusão!
                { 
                    popover: { 
                        title: 'Parabéns! 🏆', 
                        description: `
                            <div class="container-mascote-tour">
                                <img src="assets/3.png" class="mascote-tour-interno">
                                <div class="texto-tour-interno">
                                    Você concluiu o tutorial básico com sucesso!<br><br>
                                    Aproveite ao máximo a nova Intranet. Clique em <b>"Começar"</b> para finalizar.
                                </div>
                            </div>`
                    } 
                }
            ]
        });

        setTimeout(() => { driverObj.drive(); }, 1000);
    }

    function marcarTutorialComoVisto() {
        const fd = new FormData();
        fd.append('acao', 'marcar_visto');
        
        fetch('ajax_tutorial.php', { method: 'POST', body: fd })
            .then(res => res.text()) // Agora pegamos o texto que o PHP vai devolver
            .then(texto => {
                console.log('RESPOSTA DO BANCO DE DADOS:', texto);
            })
            .catch(err => console.error('Erro no fetch:', err));
    }
});

function mostrarMiniAgenda(elemento, data) {
    const tooltip = document.getElementById('tooltip-rapida');
    if (!tooltip) return;

    // Posiciona a caixinha do lado do dia que o mouse passou por cima
    const rect = elemento.getBoundingClientRect();
    tooltip.style.left = (rect.left + window.scrollX + 40) + 'px';
    tooltip.style.top = (rect.top + window.scrollY - 10) + 'px';
    
    // Texto temporário enquanto carrega
    tooltip.innerHTML = `<div class="text-xs text-slate-400">Carregando informações...</div>`;
    tooltip.style.display = 'block';

    // Faz uma requisição para trazer os dados do dia que o mouse está em cima
    // (Ajuste o caminho da API conforme a estrutura do seu projeto)
    fetch(`api/get_horarios_dia.php?data=${data}`)
        .then(response => response.text())
        .then(html => {
            // Se o mouse ainda estiver em cima da data, atualiza o HTML da caixinha com as informações reais
            if (tooltip.style.display === 'block') {
                tooltip.innerHTML = html;
            }
        })
        .catch(err => {
            tooltip.innerHTML = `<div class="text-xs text-red-500">Erro ao carregar dados.</div>`;
        });
}

function esconderMiniAgenda() {
    const tooltip = document.getElementById('tooltip-rapida');
    if (tooltip) {
        tooltip.style.display = 'none';
    }
}

</script>