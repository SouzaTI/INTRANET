<?php
/**
 * LISTA DE HORÁRIOS DO DIA - MODAL AGENDA
 */

// O config.php já inicia a sessão e configura o tempo de 1 hora
require_once __DIR__ . '/../config.php';

// Identifica se é admin para mostrar o botão de excluir
$isAdmin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
$meuId = (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0);

if ($meuId <= 0) {
    http_response_code(401);
    echo '<p class="text-slate-400 text-xs italic">Sessão expirada.</p>';
    exit;
}

$data = (string) ($_GET['data'] ?? date('Y-m-d'));
$dataObj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
$errosData = DateTimeImmutable::getLastErrors();
if (!$dataObj || ($errosData !== false && ($errosData['warning_count'] > 0 || $errosData['error_count'] > 0))) {
    http_response_code(422);
    echo '<p class="text-red-500 text-xs">Data inválida.</p>';
    exit;
}
$data = $dataObj->format('Y-m-d');

// Usando as constantes definidas no seu config.php
$db_glpi = DB_GLPI; 
$db_intra = DB_INTRA;

// Busca eventos do dia trazendo o nome do colaborador direto do GLPI
$stmt = $pdo_intra->prepare("
    SELECT a.*, u.firstname, u.realname 
    FROM {$db_intra}.agenda_eventos a
    LEFT JOIN {$db_glpi}.glpi_users u ON a.usuario_id = u.id
    WHERE a.data_evento = ?
      AND (a.visibilidade = 'GERAL' OR a.usuario_id = ?)
    ORDER BY a.hora_inicio ASC
");
$stmt->execute([$data, $meuId]);
$eventos = $stmt->fetchAll();

if ($eventos) {
    foreach ($eventos as $ev) {
        // Formata a exibição da hora ou "Dia Inteiro" para feriados
        $hora = (!empty($ev['hora_inicio'])) 
                ? substr($ev['hora_inicio'], 0, 5) . ' às ' . substr($ev['hora_fim'], 0, 5) 
                : 'Dia Inteiro';

        // Estilo visual: azul para salas, âmbar para geral
        $tagCor = ($ev['local_sala'] != 'GERAL') ? 'bg-blue-100 text-blue-600' : 'bg-amber-100 text-amber-600';
        
        $podeEditar = ($isAdmin || $ev['usuario_id'] == $meuId);
        $localSeguro = htmlspecialchars((string) $ev['local_sala'], ENT_QUOTES, 'UTF-8');
        $horaSegura = htmlspecialchars($hora, ENT_QUOTES, 'UTF-8');
        $tituloSeguro = htmlspecialchars((string) $ev['titulo'], ENT_QUOTES, 'UTF-8');
        $eventoJson = htmlspecialchars(
            json_encode($ev, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ENT_QUOTES,
            'UTF-8'
        );
        $eventoId = (int) $ev['id'];

        echo "
        <div class='p-4 bg-white rounded-2xl border border-slate-100 shadow-sm mb-3 group relative'>
            <div class='flex justify-between items-start mb-2'>
                <span class='text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded $tagCor'>$localSeguro</span>
                <div class='flex items-center gap-2'>
                    <span class='text-[10px] font-bold text-slate-400'>$horaSegura</span>
                    <div class='flex gap-1'>";
                        if ($podeEditar) {
                            echo "<button type='button' data-evento='$eventoJson' onclick='prepararEdicao(JSON.parse(this.dataset.evento))' class='text-slate-300 hover:text-blue-500 transition-colors text-lg' aria-label='Editar compromisso'>✎</button>";
                            echo "<button type='button' onclick='excluirEvento($eventoId, \"$data\")' class='text-slate-300 hover:text-red-500 transition-colors text-xl' aria-label='Excluir compromisso'>&times;</button>";
                        }
        echo "      </div>
                </div>
            </div>
            <p class='text-xs font-bold text-navy-900 mb-1'>$tituloSeguro</p>
            </div>";
            
    }
} else {
    echo "<div class='py-20 text-center'>
            <p class='text-slate-400 text-xs italic uppercase tracking-widest'>Nenhum compromisso para este dia.</p>
          </div>";
}
