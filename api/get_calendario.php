<?php
/**
 * MOTOR DO CALENDÁRIO DINÂMICO - INTRANET
 * Responsável por gerar a grade de dias e os marcadores de eventos (AJAX)
 */

// O config.php já inicia a sessão e configura o tempo de 1 hora
require_once '../config.php'; 

// Identifica o usuário logado (Tenta as duas chaves comuns no seu sistema)
$meuId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;

// Pega mês e ano da URL ou define o atual
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

// Objeto de data para navegação
$dataObj = DateTime::createFromFormat('!n-Y', "$mes-$ano");
$proximo = (clone $dataObj)->modify('+1 month');
$anterior = (clone $dataObj)->modify('-1 month');

// Formatação do título (Ex: MARÇO - 2026)
$formatter = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, "MMMM - yyyy");
$tituloMes = mb_strtoupper($formatter->format($dataObj));

// Cálculos da grade
$numDias = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
$primeiroDiaSemana = (int)$dataObj->format('w'); // 0 (Dom) a 6 (Sab)

// Nomes das bases de dados (vêm do config.php)
$db_intra = DB_INTRA;

// Busca eventos do mês (Gerais + Pessoais do usuário logado)
$stmt = $pdo_intra->prepare("
    SELECT DAY(data_evento) as dia, titulo, visibilidade 
    FROM {$db_intra}.agenda_eventos 
    WHERE MONTH(data_evento) = ? 
    AND YEAR(data_evento) = ? 
    AND (visibilidade = 'GERAL' OR usuario_id = ?)
");
$stmt->execute([$mes, $ano, $meuId]);
$eventosNoMes = $stmt->fetchAll(PDO::FETCH_GROUP);
?>

<div class="flex items-center justify-between mb-6 px-2">
    <button onclick="carregarCalendario(<?= $anterior->format('n') ?>, <?= $anterior->format('Y') ?>)" 
            class="w-8 h-8 flex items-center justify-center rounded-full border border-slate-200 text-slate-400 hover:bg-slate-50 transition-colors">❮</button>
    
    <span class="text-navy-900 font-bold text-sm uppercase tracking-wider"><?= $tituloMes ?></span>
    
    <button onclick="carregarCalendario(<?= $proximo->format('n') ?>, <?= $proximo->format('Y') ?>)" 
            class="w-8 h-8 flex items-center justify-center rounded-full border border-slate-200 text-slate-400 hover:bg-slate-50 transition-colors">❯</button>
</div>

<div class="grid grid-cols-7 text-center mb-4">
    <?php foreach (['D','S','T','Q','Q','S','S'] as $dSemana): ?>
        <span class="text-[10px] font-black text-navy-900 opacity-80"><?= $dSemana ?></span>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-7 text-center gap-y-2">
    <?php for($i=0; $i < $primeiroDiaSemana; $i++): ?><span></span><?php endfor; ?>

    <?php for($dia=1; $dia <= $numDias; $dia++): 
        $is_hoje = ($dia == date('j') && $mes == date('n') && $ano == date('Y'));
        $temEvento = isset($eventosNoMes[$dia]);
        
        // Definição de Cores Padrão
        $corFundo = 'transparent';
        $corTexto = 'text-slate-500';
        $shadow = '';

        if ($is_hoje) {
            $corFundo = '#2563eb'; // Azul Forte (Hoje)
            $corTexto = 'text-white';
            $shadow = 'shadow-lg';
        } elseif ($temEvento) {
            // Pega a visibilidade do primeiro evento do dia para definir a cor do círculo
            $primeiroEvento = $eventosNoMes[$dia][0];
            $vis = isset($primeiroEvento['visibilidade']) ? trim($primeiroEvento['visibilidade']) : '';
            
            if (strcasecmp($vis, 'GERAL') === 0) {
                $corFundo = '#f97316'; // Laranja (Público/Feriado)
            } else {
                $corFundo = '#7c3aed'; // Roxo (Pessoal/Privado)
            }
            $corTexto = 'text-white';
        }

        $dataFormatada = $ano . '-' . $mes . '-' . $dia;
    ?>
        <div class="relative group cursor-pointer py-1" onclick="abrirAgendamento('<?= $dataFormatada ?>')">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold transition-all <?= $corTexto ?> <?= $shadow ?>"
                  style="background-color: <?= $corFundo ?> !important;">
                <?= $dia ?>
            </span>
            
            <?php if($temEvento && !$is_hoje && count($eventosNoMes[$dia]) > 1): ?>
                <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-1 h-1 bg-slate-300 rounded-full opacity-50"></div>
            <?php endif; ?>
        </div>
    <?php endfor; ?>
</div>