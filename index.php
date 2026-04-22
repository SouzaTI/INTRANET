<?php 
require_once 'config.php'; 
include 'includes/header.php'; 

// Tenta pegar o nome de 'usuario_nome' ou 'user_name'. Se não houver nenhum, usa 'Colaborador'
$nome_completo = $_SESSION['usuario_nome'] ?? $_SESSION['user_name'] ?? 'Colaborador Navi';

// Garante que o explode receba uma string válida (evita o erro Fatal)
$partes_nome = explode(' ', trim((string)$nome_completo));
$primeiro_nome = $partes_nome[0];
$segundo_nome = $partes_nome[1] ?? '';

$nome_exibicao = trim($primeiro_nome . ' ' . $segundo_nome);

// Define valores padrão para outras variáveis de sessão que podem causar avisos no Header
$_SESSION['is_admin'] = $_SESSION['is_admin'] ?? false;
$_SESSION['setor_principal'] = $_SESSION['setor_principal'] ?? 'GERAL';
$user_id_logado = $_SESSION['user_id'] ?? 0; // Necessário para a query do feed
// -----------------------------------------------------

// Registro de log de acesso único por sessão
if (!isset($_SESSION['logado_nesta_sessao'])) {
    registrarLog($pdo_intra, 'ACESSO AO PORTAL', 'O usuário carregou a página inicial da Intranet.');
    $_SESSION['logado_nesta_sessao'] = true;
}

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
            ORDER BY c.data_postagem DESC 
            LIMIT 5";

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
        
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="md:col-span-2 relative overflow-hidden rounded-2xl shadow-lg min-h-[200px] border border-slate-200 bg-navy-900 group">
                <img src="img/comunicacao/banner-boas-vindas.png" 
                    class="absolute inset-0 w-full h-full object-cover opacity-90 animate-ken-burns" 
                    alt="Bem-vindo">
                
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full animate-shimmer-effect"></div>

                <div class="relative z-10 p-10 h-full flex flex-col justify-center">
                    <div class="animate-slide-up">
                        <h1 class="text-white text-3xl font-black tracking-tighter mb-1 drop-shadow-2xl">
                            Olá, <?php echo $nome_exibicao; ?>! <span class="inline-block animate-wave">👋</span>
                        </h1>
                        <p class="text-blue-50 text-base font-medium drop-shadow-lg italic opacity-90">
                            Sua central de comunicação corporativa <span class="font-bold text-white uppercase">Navi</span>.
                        </p>
                    </div>
                </div>
            </div>

            <div onclick="abrirModalAniversariantes()" 
                class="card-destaque-img cursor-pointer group rounded-2xl shadow-lg min-h-[250px] h-full border border-slate-200 flex flex-col items-center justify-center p-4 text-center overflow-hidden"
                style="background-image: url('img/comunicacao/aniversariantes_mini.png'); background-size: cover; background-position: center;">
                
                <div class="absolute inset-0 bg-black/40 z-0 group-hover:bg-black/30 transition-colors"></div>

                <div class="relative z-10 w-full flex flex-col items-center justify-center min-h-[140px] pt-10">
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
        </section>

        <div id="modalAniversariantes" onclick="fecharModalAniversariantes()" class="fixed inset-0 z-[999] hidden bg-black/95 backdrop-blur-md flex items-center justify-center p-4 md:p-10 transition-all duration-300">
            <div class="relative max-w-5xl w-full flex flex-col items-center">
                <button class="absolute -top-14 right-0 text-white text-5xl font-light hover:text-amber-400 transition-colors">&times;</button>
                <img src="img/comunicacao/aniversariantes_modal.png" class="w-full h-auto max-h-[85vh] object-contain rounded-2xl shadow-2xl border border-white/10 animate-in zoom-in-95 duration-300" alt="Lista Completa de Aniversariantes">
                <p class="mt-6 text-white/50 text-sm font-medium tracking-widest uppercase italic">Clique em qualquer lugar para fechar</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <div class="lg:col-span-8 space-y-8">
                <div class="relative group overflow-hidden rounded-2xl bg-slate-50 border border-slate-100 shadow-sm min-h-[240px] flex">
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

                <div class="space-y-6">
                    <div class="flex items-center justify-between px-4">
                        <h3 class="text-navy-900 font-black text-xl uppercase tracking-tighter italic">Feed de Notícias</h3>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Últimas 24 horas</span>
                    </div>

                    <div class="grid grid-cols-1 gap-6 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
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
                                        <p class="font-black text-navy-900 italic text-xs">Equipe de <?php echo $com['categoria']; ?> • <span class="text-slate-400 font-medium not-italic"><?php echo date('H', strtotime($com['data_postagem'])); ?>h atrás</span></p>
                                        <h4 class="text-lg font-black text-navy-900 tracking-tight leading-tight"><?php echo $com['titulo']; ?></h4>
                                    </div>
                                </div>
                                <span class="bg-blue-50 text-blue-600 text-[9px] font-black px-3 py-1 rounded-full uppercase">Importante</span>
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
                    </div>                    
                </div>
            </div>

            <div class="lg:col-span-4 space-y-6 sticky top-6">

                <div id="painel-presenca" class="w-full transition-all duration-500">
                    <div class="animate-pulse bg-white rounded-2xl h-64 w-full border border-slate-200"></div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center border border-slate-100">
                            <span class="text-xl">📅</span>
                        </div>
                        <h3 class="text-lg font-black text-navy-900 tracking-tight">Sua agenda</h3>
                    </div>

                    <div id="calendario-ajax" class="border border-slate-100 rounded-2xl p-4 mb-6 min-h-[300px]">
                        <div class="flex justify-center items-center h-full">Carregando...</div>
                    </div>
                    
                    <div id="proximos-eventos" class="space-y-4">
                        </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-6">Sistemas Internos</h3>
                    <div class="grid grid-cols-1 gap-4">
                        <a href="http://192.168.0.63:8080/glpi17/index.php" target="_blank" 
                           class="flex items-center gap-4 p-4 rounded-xl bg-slate-50 hover:bg-blue-50 border border-slate-100 transition-all group">
                            <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-xl group-hover:scale-110 transition-transform">🛠️</div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-navy-900 leading-tight">HELP CHAMADOS</span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter">Suporte</span>
                            </div>
                        </a>
                        <button onclick="abrirModalSistemas()" 
                                class="w-full flex items-center gap-4 p-4 rounded-xl bg-navy-900 hover:bg-blue-700 border border-transparent transition-all group shadow-md">
                            <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-xl group-hover:rotate-12 transition-transform">🚀</div>
                            <div class="flex flex-col text-left text-white">
                                <span class="text-xs font-black leading-tight uppercase">Outros</span>
                                <span class="text-[9px] text-white/50 font-bold uppercase tracking-tighter italic">Navegação</span>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="bg-navy-900 rounded-2xl p-6 text-white shadow-xl border-l-4 border-blue-500 relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500/0 via-blue-500/5 to-blue-500/0 -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                    
                    <div class="flex flex-col h-full justify-between relative z-10">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 italic">Central de Ajuda</p>
                            </div>
                            <p class="font-bold text-sm leading-snug">Dúvidas ou suporte técnico?</p>
                        </div>
                        
                        <div class="mt-5 flex items-end justify-between">
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Ramal Interno</p>
                                <div class="flex items-center gap-2">
                                    <span class="text-2xl font-black text-white tracking-tighter italic">3171</span>
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

            </div> 
        </div>
    </div>
</main>

<div id="modalSistemas" class="fixed inset-0 z-[1000] hidden items-center justify-center p-4 backdrop-blur-xl bg-navy-900/40 transition-all duration-500">
    
    <div class="relative bg-navy-900/90 border border-white/10 w-full max-w-4xl rounded-[2rem] p-8 shadow-2xl animate-in zoom-in-95 duration-300 overflow-hidden">
        
        <button id="btnVoltarModal" onclick="exibirPrincipalSistemas()" class="hidden absolute top-5 left-6 text-blue-400 hover:text-white transition-colors text-xs font-black flex items-center gap-2">
            ⬅️ VOLTAR
        </button>

        <div class="absolute -top-24 -right-24 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl"></div>

        <button onclick="fecharModalSistemas()" class="absolute top-5 right-6 text-white/30 hover:text-white transition-colors text-3xl font-light">&times;</button>

        <div class="mb-8 text-left border-b border-white/5 pb-4">
            <h2 id="tituloModalSistemas" class="text-white text-xl font-black tracking-tighter uppercase italic">Sistemas de Navegação</h2>
            <p id="subtituloModalSistemas" class="text-blue-400 text-[10px] font-bold uppercase tracking-widest">Selecione o sistema desejado</p>
        </div>

        <?php 
            // ARRAY AMPLIADO COM EXEMPLOS E GRUPOS
            $exemplos = [
                // GRUPO TI
                ['nome' => 'T.I Central', 'cor' => 'bg-blue-600', 'icon' => '👨‍💻', 'subitens' => [
                    ['nome' => 'Documentação', 'icon' => '📄', 'link' => '#'],
                    ['nome' => 'Gestão de PCs', 'icon' => '💻', 'link' => '#'],
                    ['nome' => 'Servidores', 'icon' => '🖥️', 'link' => '#'],
                    ['nome' => 'Chamados TI', 'icon' => '🎫', 'link' => '#']
                ]],
                // ITENS INDIVIDUAIS
                ['nome' => 'Portal RH', 'cor' => 'bg-emerald-500', 'icon' => '📂', 'link' => '#'],
                ['nome' => 'Financeiro', 'cor' => 'bg-amber-500', 'icon' => '💰', 'link' => '#'],
                ['nome' => 'Marketing', 'cor' => 'bg-pink-500', 'icon' => '🎨', 'link' => '#'],
                ['nome' => 'Logística', 'cor' => 'bg-orange-500', 'icon' => '📦', 'link' => '#'],
                ['nome' => 'Diretoria', 'cor' => 'bg-purple-500', 'icon' => '👔', 'link' => '#'],
                ['nome' => 'Vendas', 'cor' => 'bg-red-500', 'icon' => '📉', 'link' => '#'],
                ['nome' => 'Suporte', 'cor' => 'bg-cyan-500', 'icon' => '🎧', 'link' => '#'],
                ['nome' => 'BI / Dash', 'cor' => 'bg-yellow-500', 'icon' => '📊', 'link' => '#'],
                ['nome' => 'Expedição', 'cor' => 'bg-slate-500', 'icon' => '🚚', 'link' => '#'],
                ['nome' => 'Jurídico', 'cor' => 'bg-indigo-700', 'icon' => '⚖️', 'link' => '#'],
                ['nome' => 'Qualidade', 'cor' => 'bg-teal-600', 'icon' => '✅', 'link' => '#'],
                ['nome' => 'Frota', 'cor' => 'bg-gray-700', 'icon' => '🚗', 'link' => '#'],
                ['nome' => 'Compras', 'cor' => 'bg-blue-400', 'icon' => '🛒', 'link' => '#'],
                ['nome' => 'Produção', 'cor' => 'bg-red-800', 'icon' => '🏗️', 'link' => '#'],
                ['nome' => 'Estoque', 'cor' => 'bg-lime-600', 'icon' => '🏷️', 'link' => '#'],
                ['nome' => 'E-commerce', 'cor' => 'bg-violet-600', 'icon' => '🌐', 'link' => '#'],
                ['nome' => 'Treinamentos', 'cor' => 'bg-rose-500', 'icon' => '🎓', 'link' => '#'],
            ];
        ?>

        <div id="gridSistemasPrincipal" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar-compact">
            
            <?php foreach ($exemplos as $item): ?>
                <?php if (isset($item['subitens'])): ?>
                    <button onclick='abrirGrupoSistemas(<?php echo json_encode($item); ?>)' class="group flex flex-col items-center justify-center p-3 rounded-2xl hover:bg-white/5 transition-all duration-300">
                        <div class="w-14 h-14 rounded-full <?php echo $item['cor']; ?> flex items-center justify-center text-2xl shadow-lg group-hover:scale-110 transition-all duration-300 relative">
                            <?php echo $item['icon']; ?>
                            <span class="absolute -right-1 -top-1 bg-white text-navy-900 text-[9px] font-black w-5 h-5 rounded-full flex items-center justify-center shadow-md">
                                <?php echo count($item['subitens']); ?>
                            </span>
                        </div>
                        <span class="mt-2 text-white/70 font-bold text-[9px] uppercase tracking-tighter text-center leading-tight group-hover:text-white">
                            <?php echo $item['nome']; ?>
                        </span>
                    </button>
                <?php else: ?>
                    <a href="<?php echo $item['link'] ?? '#'; ?>" class="group flex flex-col items-center justify-center p-3 rounded-2xl hover:bg-white/5 transition-all duration-300">
                        <div class="w-14 h-14 rounded-full <?php echo $item['cor']; ?>/20 border border-<?php echo $item['cor']; ?>/30 flex items-center justify-center text-2xl shadow-lg group-hover:scale-110 group-hover:<?php echo $item['cor']; ?> group-hover:shadow-<?php echo $item['cor']; ?>/40 transition-all duration-300">
                            <?php echo $item['icon']; ?>
                        </div>
                        <span class="mt-2 text-white/70 font-bold text-[9px] uppercase tracking-tighter text-center leading-tight group-hover:text-white">
                            <?php echo $item['nome']; ?>
                        </span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>

        </div>

        <div id="gridSistemasSub" class="hidden grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-4 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar-compact">
            </div>

        <div class="mt-8 pt-4 border-t border-white/5 flex justify-between items-center text-[9px] font-bold text-white/20 uppercase tracking-widest">
            <span>Navi</span>
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

                <button type="submit" class="w-full bg-navy-900 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-all uppercase text-xs tracking-widest mt-2">
                    Confirmar Reserva 🚀
                </button>
            </form>
        </div>
    </div>
</div>

<script>

let slideAtual = 0;
const totalSlides = <?php echo count($banners); ?>;
const container = document.getElementById('carrossel-container');
function moverCarrossel(direcao) {
if (totalSlides <= 1) return;
        slideAtual = (slideAtual + direcao + totalSlides) % totalSlides;
        container.style.transform = `translateX(-${slideAtual * 100}%)`;
    }
if (totalSlides > 1) { setInterval(() => moverCarrossel(1), 7000); }


function abrirGrupoSistemas(grupo) {
    const principal = document.getElementById('gridSistemasPrincipal');
    const sub = document.getElementById('gridSistemasSub');
    const btnVoltar = document.getElementById('btnVoltarModal');
    const titulo = document.getElementById('tituloModalSistemas');
    const subtitulo = document.getElementById('subtituloModalSistemas');

    principal.classList.add('hidden');
    sub.classList.remove('hidden');
    btnVoltar.classList.remove('hidden');

    titulo.innerText = grupo.nome;
    subtitulo.innerText = "Subitens do Grupo";

    sub.innerHTML = '';
    grupo.subitens.forEach(item => {
        sub.innerHTML += `
            <a href="${item.link}" class="group flex flex-col items-center justify-center p-3 rounded-2xl hover:bg-white/5 transition-all duration-300">
                <div class="w-14 h-14 rounded-full bg-white/10 border border-white/20 flex items-center justify-center text-2xl shadow-lg group-hover:scale-110 group-hover:bg-blue-600 transition-all duration-300">
                    ${item.icon}
                </div>
                <span class="mt-2 text-white/70 font-bold text-[9px] uppercase tracking-tighter text-center leading-tight group-hover:text-white">
                    ${item.nome}
                </span>
            </a>
        `;
    });
}

function exibirPrincipalSistemas() {
    document.getElementById('gridSistemasPrincipal').classList.remove('hidden');
    document.getElementById('gridSistemasSub').classList.add('hidden');
    document.getElementById('btnVoltarModal').classList.add('hidden');
    document.getElementById('tituloModalSistemas').innerText = "Sistemas de Navegação";
    document.getElementById('subtituloModalSistemas').innerText = "Selecione o sistema desejado";
}

function abrirModalSistemas() {
    const modal = document.getElementById('modalSistemas');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden'; // Trava o scroll da página ao fundo
}

function fecharModalSistemas() {
    const modal = document.getElementById('modalSistemas');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto'; // Libera o scroll
}

// Fecha com a tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") {
        fecharModalSistemas();
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
        document.addEventListener('keydown', function(event) { if (event.key === "Escape") { fecharModalAniversariantes(); } });

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
    // Efeito visual de carregamento
    container.style.opacity = '0.5';

    fetch(`api/get_calendario.php?mes=${mes}&ano=${ano}`)
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
            container.style.opacity = '1';
        })
        .catch(err => console.error('Erro ao carregar calendário:', err));
}

// Inicializa o calendário no mês atual ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    carregarCalendario(<?= date('n') ?>, <?= date('Y') ?>);
});

function abrirAgendamento(data) {
    document.getElementById('input-data-evento').value = data;
    document.getElementById('data-formatada').innerText = data.split('-').reverse().join('/');
    
    // Busca os horários ocupados para esse dia via AJAX
    fetch(`api/get_horarios_dia.php?data=${data}`)
        .then(res => res.text())
        .then(html => {
            document.getElementById('lista-horarios-dia').innerHTML = html;
        });

    document.getElementById('modalAgendamento').classList.remove('hidden');
    document.getElementById('modalAgendamento').classList.add('flex');
}

// Processar o agendamento via AJAX
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
            // Recarrega o calendário mantendo o mês/ano atual
            const dataSel = document.getElementById('input-data-evento').value.split('-');
            carregarCalendario(parseInt(dataSel[1]), parseInt(dataSel[0]));
            this.reset();
        } else {
            // EXIBE O ERRO DE CONFLITO (Ex: Esta sala já está reservada...)
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
            // Atualiza a lista lateral do modal
            abrirAgendamento(data);
            // Atualiza o calendário ao fundo (bolinhas)
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
        // Bloqueia visualmente mas preenche os valores padrão
        div.style.opacity = '0.5';
        div.style.pointerEvents = 'none';
        
        inputInicio.value = '08:00';
        inputFim.value = '17:48';
        
        // Remove obrigatoriedade manual pois já preenchemos
        inputInicio.required = false;
        inputFim.required = false;
    } else {
        // Libera para edição manual e limpa para o usuário escolher
        div.style.opacity = '1';
        div.style.pointerEvents = 'all';
        
        inputInicio.value = '';
        inputFim.value = '';
        
        inputInicio.required = true;
        inputFim.required = true;
    }
}

function prepararEdicao(evento) {
    // 1. Preenche os campos do modal com os dados existentes
    document.getElementById('edit-id-evento').value = evento.id;
    document.getElementsByName('titulo')[0].value = evento.titulo;
    document.getElementsByName('local_sala')[0].value = evento.local_sala;
    
    // 2. Trata horários
    if (evento.hora_inicio && evento.hora_fim) {
        document.getElementsByName('hora_inicio')[0].value = evento.hora_inicio;
        document.getElementsByName('hora_fim')[0].value = evento.hora_fim;
        document.getElementById('dia_inteiro').checked = false;
        toggleHoras(false);
    } else {
        document.getElementById('dia_inteiro').checked = true;
        toggleHoras(true);
    }

    // 3. Muda o visual do botão
    document.getElementById('btn-confirmar').innerHTML = "Salvar Alterações 💾";
}

// No fecharAgendamento(), certifique-se de resetar o ID e o botão
function fecharAgendamento() {
    document.getElementById('modalAgendamento').classList.add('hidden');
    document.getElementById('formAgenda').reset();
    document.getElementById('edit-id-evento').value = "";
    document.getElementById('btn-confirmar').innerHTML = "Confirmar Reserva 🚀";
}

function atualizarPresenca() {
        // Faz a chamada para o arquivo que criamos
        fetch('lista_online.php')
            .then(response => response.text())
            .then(html => {
                document.getElementById('painel-presenca').innerHTML = html;
            })
            .catch(err => console.warn('Erro ao carregar lista de presença.'));
    }

    // Carrega ao abrir a página
    document.addEventListener('DOMContentLoaded', atualizarPresenca);

    // Atualiza a cada 30 segundos (30000ms) para não sobrecarregar o servidor
    setInterval(atualizarPresenca, 30000);

function toggleCurtida(comunicadoId, btnElement) {
    const fd = new FormData();
    fd.append('acao', 'curtir');
    fd.append('comunicado_id', comunicadoId);

    fetch('api/feed_interacoes.php', {
        method: 'POST',
        body: fd
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'sucesso') {
            // Atualiza o número na tela
            btnElement.querySelector('.contador-curtidas').innerText = data.total;
            
            // Troca o emoji do coração
            if(data.acao === 'curtiu') {
                btnElement.querySelector('.icone-coracao').innerText = '❤️';
            } else {
                btnElement.querySelector('.icone-coracao').innerText = '🤍';
            }
        }
    })
    .catch(err => console.error('Erro ao curtir:', err));
}

// 1. Abre/Fecha a sessão de comentários
function toggleComentarios(comunicadoId) {
    const divComentarios = document.getElementById(`comentarios-post-${comunicadoId}`);
    divComentarios.classList.toggle('hidden');

    // Se acabou de abrir, busca os comentários no banco!
    if (!divComentarios.classList.contains('hidden')) {
        carregarComentarios(comunicadoId, divComentarios.querySelector('.lista-comentarios'));
    }
}

// 2. Busca e desenha os comentários na tela
function carregarComentarios(comunicadoId, containerElement) {
    const fd = new FormData();
    fd.append('acao', 'listar_comentarios');
    fd.append('comunicado_id', comunicadoId);

    fetch('api/feed_interacoes.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'sucesso') {
            // Atualiza o contador de balões do post atual
            const postCard = containerElement.closest('.bg-white');
            postCard.querySelector('.contador-comentarios').innerText = data.comentarios.length;

            if (data.comentarios.length === 0) {
                containerElement.innerHTML = '<p class="text-[10px] text-slate-400 text-center italic py-2">Seja o primeiro a comentar! 💬</p>';
                return;
            }

            // Desenha os balões de cada pessoa
            containerElement.innerHTML = data.comentarios.map(c => `
                <div class="bg-slate-50 rounded-2xl rounded-tl-none p-3 shadow-sm border border-slate-100">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-[9px] font-black text-navy-900 uppercase">${c.nome}</span>
                        <span class="text-[8px] text-slate-400 font-bold">${c.data_hora}</span>
                    </div>
                    <p class="text-xs text-slate-600 font-medium">${c.comentario}</p>
                </div>
            `).join('');
            
            // Rola para o final da listinha se tiver muitos
            containerElement.scrollTop = containerElement.scrollHeight;
        }
    });
}

// 3. Manda um novo comentário pro banco
function enviarComentario(e, comunicadoId, formElement) {
    e.preventDefault();
    const input = formElement.querySelector('input[name="texto_comentario"]');
    const texto = input.value.trim();
    if(!texto) return;

    const fd = new FormData();
    fd.append('acao', 'comentar');
    fd.append('comunicado_id', comunicadoId);
    fd.append('comentario', texto);

    // Trava o input enquanto envia
    input.value = ''; 
    input.disabled = true;

    fetch('api/feed_interacoes.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        input.disabled = false;
        if (data.status === 'sucesso') {
            // Se deu certo, recarrega a lista para mostrar o novo na hora!
            const container = document.getElementById(`comentarios-post-${comunicadoId}`).querySelector('.lista-comentarios');
            carregarComentarios(comunicadoId, container);
        }
    })
    .catch(() => input.disabled = false);
}

</script>

<?php include 'includes/footer.php'; ?>