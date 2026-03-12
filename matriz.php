<?php
require_once 'config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// 1. Busca os dados no banco 'intranet' usando a conexão PDO global
$sql = "SELECT SETOR, NOME, RAMAL, `E-MAIL` as email, `CELULAR CORPORATIVO` as celular 
        FROM matriz_comunicacao 
        WHERE NOME IS NOT NULL AND TRIM(NOME) != '' 
        ORDER BY NOME ASC";

$stmt = $pdo_intra->query($sql);
$employees_json = [];

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $employees_json[] = [
        'setor' => $row['SETOR'],
        'nome' => $row['NOME'],
        'ramal' => $row['RAMAL'],
        'email' => $row['email'],
        'celular_corporativo' => $row['celular']
    ];
}

// 2. Consulta setores únicos para o modal
$sql_setores = "SELECT DISTINCT SETOR FROM matriz_comunicacao WHERE SETOR IS NOT NULL AND TRIM(SETOR) != '' ORDER BY SETOR ASC";
$setores_lista = $pdo_intra->query($sql_setores)->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    .matriz-main { flex: 1; padding: 2rem; background: #f9fafb; overflow-y: auto; font-family: 'Inter', sans-serif; }
    .matriz-container { max-width: 1300px; margin: 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1); overflow: hidden; border: 1px solid #e5e7eb; }
    .matriz-header { background: #2F75B5; color: white; padding: 2.5rem; text-align: center; }
    .matriz-header h1 { margin: 0; font-size: 1.8rem; font-weight: 700; }
    .matriz-controls { padding: 2rem; border-bottom: 1px solid #e5e7eb; background: #fff; }
    .matriz-table { width: 100%; border-collapse: collapse; border: 1px solid #ccc; }
    .matriz-table th { background: #f3f4f6; padding: 10px; border: 1px solid #ccc; font-size: 0.85rem; text-transform: uppercase; color: #4b5563; }
    .matriz-table td { padding: 10px; border: 1px solid #ccc; font-size: 0.9rem; color: #1f2937; }
    .matriz-table tr:nth-child(even) { background-color: #f7f7f7; }
    .matriz-table tr:hover { background: #e9e9e9; }
    .btn-matriz { padding: 0.8rem 1.2rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; }
    .btn-email { background: #f8ab1a; color: #1f2937; }
    .btn-phone { background: #e74c3c; color: white; }
    .btn-setores { background: #2563eb; color: white; }
    .toast-matriz { position: fixed; top: 10%; left: 50%; transform: translateX(-50%); background: #f8ab1a; padding: 1rem 1.5rem; border-radius: 8px; display: none; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
</style>

<div class="matriz-main">
    <div class="matriz-container">
        <div class="matriz-header">
            <h1>Matriz de Comunicação</h1>
        </div>

        <div class="matriz-controls">
            <input type="text" id="search-input" style="width:100%; padding:12px; border:1px solid #ccc; border-radius:8px; margin-bottom:1.5rem;" placeholder="Buscar por Nome, Setor, E-mail ou Ramal...">
            <div style="display:flex; gap:10px;">
                <button id="setores-btn" class="btn-matriz btn-setores">📂 Setores</button>
                <button id="copy-all-btn" class="btn-matriz btn-email">📧 Copiar Todos os E-mails</button>
                <button id="copy-all-phone-btn" class="btn-matriz btn-phone">📱 Copiar Todos os Celulares</button>
            </div>
        </div>

        <div style="padding:2rem;">
            <table class="matriz-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Setor</th>
                        <th style="width:350px;">E-mail</th>
                        <th style="text-align:center;">Celular Corp.</th>
                        <th style="text-align:center;">Ramal</th>
                        <th style="text-align:center;">Ações</th>
                    </tr>
                </thead>
                <tbody id="table-body"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="setores-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; padding:2rem; border-radius:12px; width:600px; max-width:90%;">
        <h3 style="margin-top:0">Filtrar por Setor</h3>
        <div id="setores-grid" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:20px;"></div>
        <div style="display:flex; justify-content:space-between;">
            <button id="clear-setor-btn" class="btn-matriz" style="background:#e5e7eb">Limpar Filtro</button>
            <button id="close-setor-modal" class="btn-matriz btn-setores">Fechar</button>
        </div>
    </div>
</div>

<div id="toast" class="toast-matriz"></div>

<script>
    const employees = <?php echo json_encode($employees_json); ?>;
    const setoresLista = <?php echo json_encode($setores_lista); ?>;
    let filteredEmployees = employees;
    let filtroSetorAtivo = '';

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.innerText = msg; t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }

    // Fallback de cópia para conexões HTTP
    function fallbackCopy(text, type) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        document.body.appendChild(textArea);
        textArea.focus(); textArea.select();
        try {
            document.execCommand('copy') ? showToast(`${type} copiado!`) : showToast(`Erro ao copiar ${type}`);
        } catch (err) { showToast(`Erro ao copiar ${type}`); }
        document.body.removeChild(textArea);
    }

    function copyContact(content, type) {
        if (!content || content === '-') { showToast(`Nenhum ${type.toLowerCase()} disponível`); return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(content).then(() => showToast(`${type} copiado!`)).catch(() => fallbackCopy(content, type));
        } else { fallbackCopy(content, type); }
    }

    function renderTable() {
        const tbody = document.getElementById('table-body');
        tbody.innerHTML = filteredEmployees.map(emp => `
            <tr>
                <td>${emp.nome}</td>
                <td>${emp.setor}</td>
                <td style="color:#1c75bc;">${emp.email || '-'}</td>
                <td style="text-align:center;">${emp.celular_corporativo || '-'}</td>
                <td style="text-align:center; font-weight:bold;">${emp.ramal || '-'}</td>
                <td style="text-align:center;">
                    <button onclick="copyContact('${emp.email}', 'E-mail')" style="cursor:pointer; border:none; background:none;">📧</button>
                    <button onclick="copyContact('${emp.celular_corporativo}', 'Celular')" style="cursor:pointer; border:none; background:none;">📱</button>
                </td>
            </tr>
        `).join('');
    }

    function filterEmployees() {
        const search = document.getElementById('search-input').value.toLowerCase();
        filteredEmployees = employees.filter(emp => {
            const matchesSearch = emp.nome.toLowerCase().includes(search) || 
                                 emp.setor.toLowerCase().includes(search) ||
                                 (emp.email && emp.email.toLowerCase().includes(search)) ||
                                 (emp.ramal && emp.ramal.toLowerCase().includes(search));
            const matchesSetor = filtroSetorAtivo === '' || emp.setor === filtroSetorAtivo;
            return matchesSearch && matchesSetor;
        });
        renderTable();
    }

    // Botões de cópia em massa corrigidos
    document.getElementById('copy-all-btn').onclick = () => {
        const emails = filteredEmployees.map(e => e.email).filter(e => e && e !== '-').join('; ');
        copyContact(emails, "Todos os e-mails");
    };

    document.getElementById('copy-all-phone-btn').onclick = () => {
        const phones = filteredEmployees.map(e => e.celular_corporativo).filter(p => p && p !== '-').join('; ');
        copyContact(phones, "Todos os celulares");
    };

    // Lógica do Modal de Setores
    document.getElementById('setores-btn').onclick = () => {
        const grid = document.getElementById('setores-grid');
        grid.innerHTML = setoresLista.map(s => `
            <button onclick="setFiltroSetor('${s}')" style="padding:8px; font-size:10px; font-weight:bold; border-radius:4px; border:1px solid #ccc; cursor:pointer; background:${s === filtroSetorAtivo ? '#2563eb' : '#f9fafb'}; color:${s === filtroSetorAtivo ? 'white' : '#1f2937'}">${s}</button>
        `).join('');
        document.getElementById('setores-modal').style.display = 'flex';
    };

    window.setFiltroSetor = (setor) => {
        filtroSetorAtivo = setor; filterEmployees();
        document.getElementById('setores-modal').style.display = 'none';
    };

    document.getElementById('clear-setor-btn').onclick = () => { filtroSetorAtivo = ''; filterEmployees(); document.getElementById('setores-modal').style.display = 'none'; };
    document.getElementById('close-setor-modal').onclick = () => document.getElementById('setores-modal').style.display = 'none';
    document.getElementById('search-input').oninput = filterEmployees;

    renderTable();
</script>

<?php include 'includes/footer.php'; ?>