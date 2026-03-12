<?php
/**
 * LISTA DE HORÁRIOS DO DIA - MODAL AGENDA
 */

// O config.php já inicia a sessão e configura o tempo de 1 hora
require_once '../config.php'; 

// Identifica se é admin para mostrar o botão de excluir
$isAdmin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
$meuId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;

$data = $_GET['data'] ?? date('Y-m-d');

// Usando as constantes definidas no seu config.php
$db_glpi = DB_GLPI; 
$db_intra = DB_INTRA;

// Busca eventos do dia trazendo o nome do colaborador direto do GLPI
$stmt = $pdo_intra->prepare("
    SELECT a.*, u.firstname, u.realname 
    FROM {$db_intra}.agenda_eventos a
    LEFT JOIN {$db_glpi}.glpi_users u ON a.usuario_id = u.id
    WHERE a.data_evento = ? 
    ORDER BY a.hora_inicio ASC
");
$stmt->execute([$data]);
$eventos = $stmt->fetchAll();

if ($eventos) {
    foreach ($eventos as $ev) {
        // Formata a exibição da hora ou "Dia Inteiro" para feriados
        $hora = (!empty($ev['hora_inicio'])) 
                ? substr($ev['hora_inicio'], 0, 5) . ' às ' . substr($ev['hora_fim'], 0, 5) 
                : 'Dia Inteiro';

        // Estilo visual: azul para salas, âmbar para geral
        $tagCor = ($ev['local_sala'] != 'GERAL') ? 'bg-blue-100 text-blue-600' : 'bg-amber-100 text-amber-600';
        
        // Nome do colaborador vindo do GLPI
        $nomeExibicao = trim(($ev['firstname'] ?? '') . ' ' . ($ev['realname'] ?? '')) ?: 'Sistema';
        
        // Permissão de exclusão (Admin ou Dono do agendamento)
        $podeExcluir = ($isAdmin || $ev['usuario_id'] == $meuId);
        

        $podeEditar = ($isAdmin || $ev['usuario_id'] == $meuId);

        echo "
        <div class='p-4 bg-white rounded-2xl border border-slate-100 shadow-sm mb-3 group relative'>
            <div class='flex justify-between items-start mb-2'>
                <span class='text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded $tagCor'>{$ev['local_sala']}</span>
                <div class='flex items-center gap-2'>
                    <span class='text-[10px] font-bold text-slate-400'>$hora</span>
                    <div class='flex gap-1'>";
                        if ($podeEditar) {
                            echo "<button onclick='prepararEdicao(" . json_encode($ev) . ")' class='text-slate-300 hover:text-blue-500 transition-colors text-lg'>✎</button>";
                            echo "<button onclick='excluirEvento({$ev['id']}, \"$data\")' class='text-slate-300 hover:text-red-500 transition-colors text-xl'>&times;</button>";
                        }
        echo "      </div>
                </div>
            </div>
            <p class='text-xs font-bold text-navy-900 mb-1'>{$ev['titulo']}</p>
            </div>";
            
    }
} else {
    echo "<div class='py-20 text-center'>
            <p class='text-slate-400 text-xs italic uppercase tracking-widest'>Nenhum compromisso para este dia.</p>
          </div>";
}