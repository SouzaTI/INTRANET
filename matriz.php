<?php
require_once 'config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// 1. Query Segura usando PDO e a trava ativo = 1
$sql = "SELECT SETOR, NOME, RAMAL, `E-MAIL` as email, `CELULAR CORPORATIVO` as celular_corporativo 
        FROM matriz_comunicacao
        WHERE NOME IS NOT NULL 
        AND TRIM(NOME) != '' 
        AND ativo = 1
        ORDER BY NOME ASC";

$stmt = $pdo_intra->query($sql);
$employees_json = [];

if ($stmt && $stmt->rowCount() > 0) {
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $employees_json[] = [
            'setor' => $row['SETOR'],
            'nome' => $row['NOME'],
            'ramal' => $row['RAMAL'],
            'email' => $row['email'],
            'celular_corporativo' => $row['celular_corporativo']
        ];
    }
}

$sql_setores = "SELECT DISTINCT SETOR FROM matriz_comunicacao WHERE SETOR IS NOT NULL AND TRIM(SETOR) != '' AND ativo = 1 ORDER BY SETOR ASC";
$setores_lista = $pdo_intra->query($sql_setores)->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
  /* Escopo fechado para não quebrar a Intranet */
  :root {
    --primary-blue: #1c75bc; 
    --secondary-yellow: #f8ab1a; 
    --btn-phone-bg: #e74c3c; 
    --text-dark: #1f2937;
    --text-medium: #4b5563;
    --bg-light: #f9fafb;
    --bg-surface: #ffffff;
    --border-color: #ccc; 
  }
  
  .matriz-page-wrapper {
    box-sizing: border-box;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    padding: 2rem 1rem;
    min-height: 100%;
  }

  .matriz-page-wrapper * { box-sizing: border-box; }

  .my-matriz-container {
    max-width: 1300px;
    margin: 0 auto;
    background: rgba(255, 255, 255, 0.95); 
    border-radius: 16px;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25);
    overflow: hidden;
  }

  .my-matriz-header {
    background: var(--primary-blue);
    color: white;
    padding: 2rem;
    text-align: center;
  }

  .my-matriz-header h1 { margin: 0 0 0.5rem 0; font-size: 2.2rem; font-weight: 700; line-height: 1.2; }
  
  .my-matriz-controls {
    padding: 2rem;
    background: var(--bg-surface);
    border-bottom: 1px solid #e5e7eb;
    display: flex; 
    flex-direction: column; 
    gap: 1.5rem; 
  }

  .my-matriz-search-bar {
    flex: 1; 
    width: 100%;
    display: flex;
  }

  .my-matriz-search-input {
    flex: 1; min-width: 250px; padding: 0.875rem 1rem 0.875rem 3rem;
    border: 1px solid #d1d5db; 
    border-radius: 10px; font-size: 1rem;
    transition: all 0.2s; color: var(--text-dark);
    background: white url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%231c75bc" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>') no-repeat 1rem center;
  }

  .my-matriz-search-input:focus {
    outline: none; border-color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(28, 117, 188, 0.3); 
  }

  .my-matriz-btn {
    padding: 0.875rem 1.5rem; border: none; border-radius: 10px;
    font-size: 1rem; font-weight: 600; cursor: pointer;
    transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem;
  }
  
  .my-matriz-btn-primary { background: var(--primary-blue); color: white; }
  .my-matriz-btn-primary:hover { background: #1a6099; }
  .my-matriz-btn-primary svg { stroke: white; }
  
  .my-matriz-btn-copy-email { background: var(--secondary-yellow); color: var(--text-dark); }
  .my-matriz-btn-copy-email:hover { background: #e39617; }
  
  .my-matriz-btn-copy-phone { background: var(--btn-phone-bg); color: white; }
  .my-matriz-btn-copy-phone:hover { background: #cc3333; }

  .my-matriz-btn-small { padding: 0.4rem 0.5rem; font-size: 0.8rem; border-radius: 6px; font-weight: 600; }
  .my-matriz-btn-small-email { background: var(--primary-blue); color: white; }
  .my-matriz-btn-small-email:hover { background: #1a6099; }
  .my-matriz-btn-small-phone { background: var(--btn-phone-bg); color: white; }
  .my-matriz-btn-small-phone:hover { background: #cc3333; }

  .my-matriz-action-buttons { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: flex-start; }

  .my-matriz-content { padding: 2rem; }
  .my-matriz-table-container { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border-color); }

  .my-matriz-table { width: 100%; border-collapse: collapse; background: white; border: none; }
  .my-matriz-table thead { background: var(--bg-light); }
  .my-matriz-table th {
    padding: 0.6rem 0.8rem; text-align: left; font-weight: 700; color: var(--text-medium);
    font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em;
    border: 1px solid var(--border-color); 
  }
  .my-matriz-table td {
    padding: 0.6rem 0.8rem; border: 1px solid var(--border-color); 
    color: var(--text-dark); vertical-align: middle; 
  }
  .my-matriz-table th.email-cell, .my-matriz-table td.email-cell { width: 450px; max-width: 450px; word-break: break-all; white-space: normal; color: var(--primary-blue); font-weight: 500; }
  .my-matriz-table tbody tr:nth-child(even) { background-color: #f7f7f7; }
  .my-matriz-table tbody tr:hover { background: #e9e9e9; } 

  .ramal-cell, .phone-cell { text-align: center; }
  .actions-cell { display: flex; flex-direction: row; gap: 4px; justify-content: center; }

  .my-matriz-empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-medium); display: none; }
  .my-matriz-empty-state h3 { color: var(--text-dark); font-size: 1.5rem; margin-top: 1rem; }
  .my-matriz-empty-state svg { width: 48px; height: 48px; margin: 0 auto; stroke: var(--text-medium); }
  
  .my-matriz-toast {
    position: fixed; top: 10%; left: 50%; transform: translateX(-50%); 
    background: var(--secondary-yellow); color: var(--text-dark); 
    padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    display: none; align-items: center; gap: 0.75rem;
    animation: fadeIn 0.3s ease; z-index: 10000;
    min-width: 250px; text-align: center; font-weight: bold;
  }
  .my-matriz-toast.show { display: flex; }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

  .my-matriz-modal {
      display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0, 0, 0, 0.6); z-index: 9999; align-items: center; justify-content: center;
      padding: 1rem;
  }
  .my-matriz-modal.show { display: flex; }
  .my-matriz-modal-content {
      background: white; border-radius: 12px; padding: 2rem; max-width: 90%;
      width: 700px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  }
  .my-matriz-modal-content h2 { margin-top: 0; margin-bottom: 1.5rem; color: var(--text-dark); font-size: 1.5rem; font-weight: bold; }
  
  .my-matriz-btn-cancel { background: #e5e7eb; color: var(--text-medium); }
  .my-matriz-btn-cancel:hover { background: #d1d5db; }

  @media (max-width: 768px) {
    .actions-cell { flex-direction: row; gap: 8px; }
    .my-matriz-modal-content { padding: 1rem; }
  }
</style>

<main class="flex-1 overflow-y-auto w-full" style="background: url('background.png') no-repeat center center; background-size: 100% 100%; background-attachment: fixed;">
  <div class="matriz-page-wrapper">
      <div class="my-matriz-container">
       <div class="my-matriz-header">
        <h1 id="page-title">Matriz de Comunicação</h1>
       </div>
       <div class="my-matriz-controls">
        <div class="my-matriz-search-bar">
          <input type="text" id="search-input" class="my-matriz-search-input" placeholder="Buscar por Nome, Setor, E-mail ou Ramal...">
        </div>
        <div class="my-matriz-action-buttons">
          <button id="setores-btn" class="my-matriz-btn my-matriz-btn-primary">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
              <span>Setores</span> 
          </button>

          <button id="copy-all-btn" class="my-matriz-btn my-matriz-btn-copy-email">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect> <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            <span>Copiar Todos os E-mails</span> 
          </button>
          
          <button id="copy-all-phone-btn" class="my-matriz-btn my-matriz-btn-copy-phone"> 
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12" y2="18"></line></svg>
            <span>Copiar Todos os Celulares</span> 
          </button>
        </div>
       </div>
       <div class="my-matriz-content">
        <div class="my-matriz-table-container">
         <table id="employees-table" class="my-matriz-table">
          <thead>
           <tr>
            <th>Nome</th>
            <th>Setor</th>
            <th class="email-cell">E-mail</th>
            <th class="phone-cell">Celular Corp.</th>
            <th class="ramal-cell">Ramal</th>
            <th>Ações</th>
           </tr>
          </thead>
          <tbody id="table-body">
          </tbody>
         </table>
        </div>
        <div id="empty-state" class="my-matriz-empty-state">
         <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2a4 4 0 0 0 4 4h8a4 4 0 0 0 4-4z"></path> <circle cx="9" cy="7" r="4"></circle> <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path> <path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
         <h3>Nenhum colaborador encontrado</h3>
         <p>Verifique o termo de busca.</p>
        </div>
       </div>
      </div>
  </div>
</main>

<div id="setores-modal" class="my-matriz-modal">
  <div class="my-matriz-modal-content">
    <h2>Filtrar por Setor</h2>
    <div id="setores-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; margin-bottom: 1.5rem;">
        </div>
    <div style="display: flex; justify-content: space-between;">
        <button type="button" id="clear-setor-btn" class="my-matriz-btn my-matriz-btn-cancel">Limpar Filtro</button>
        <button type="button" id="close-setor-modal" class="my-matriz-btn my-matriz-btn-primary">Fechar</button>
    </div>
  </div>
</div>

<div id="toast" class="my-matriz-toast">
 <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg><span id="toast-message"></span>
</div>

<script>
  function showToast(message) {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    if (toast && toastMessage) {
      toastMessage.textContent = message;
      toast.classList.add('show');
      setTimeout(() => { toast.classList.remove('show'); }, 3000);
    }
  }
  
  function fallbackCopyTextToClipboard(text, type) {
      const textArea = document.createElement("textarea");
      textArea.value = text;
      textArea.style.position = "fixed";
      textArea.style.top = 0;
      textArea.style.left = 0;
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      try {
          const successful = document.execCommand('copy');
          if (successful) {
              showToast(`${type} copiado!`);
          } else {
              showToast(`Erro ao copiar ${type}`);
          }
      } catch (err) {
          showToast(`Erro ao copiar ${type}.`);
      }
      document.body.removeChild(textArea);
  }

  function copyContact(content, type) {
      if (!content || content.trim() === '' || content === '-') {
          showToast(`Nenhum ${type.toLowerCase()} para copiar`);
          return;
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(content).then(() => {
              showToast(`${type} copiado!`);
          }).catch(err => {
              fallbackCopyTextToClipboard(content, type);
          });
      } else {
          fallbackCopyTextToClipboard(content, type);
      }
  }

  document.addEventListener('DOMContentLoaded', function() {
      let employees = <?php echo json_encode($employees_json); ?>; 
      let filteredEmployees = employees;
      let filtroSetorAtivo = ''; 
      const setoresLista = <?php echo json_encode($setores_lista); ?>;
      
      function copyAll(isEmail) {
          const dataKey = isEmail ? 'email' : 'celular_corporativo';
          const typeLabel = isEmail ? 'E-mails' : 'Celulares';
          
          const validData = filteredEmployees 
              .map(emp => emp[dataKey])
              .filter(data => data && data.trim() !== '' && data !== '-');
          
          if (validData.length === 0) {
               showToast(`Nenhum ${typeLabel.toLowerCase()} para copiar no filtro atual.`);
               return;
          }
          const contentToCopy = validData.join('; ');
          if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(contentToCopy).then(() => {
                  showToast(`${validData.length} ${typeLabel} copiados!`);
              }).catch(err => { fallbackCopyTextToClipboard(contentToCopy, `${validData.length} ${typeLabel}`); });
          } else {
              fallbackCopyTextToClipboard(contentToCopy, `${validData.length} ${typeLabel}`);
          }
      }
      
      function aplicarFiltroSetor(setor) {
          filtroSetorAtivo = setor;
          document.getElementById('search-input').value = '';
          filterEmployees();
          document.getElementById('setores-modal').classList.remove('show');
          showToast(setor === '' ? 'Filtro de setor removido.' : `Filtro aplicado: ${setor}`);
      }
      
      function renderSetoresModal() {
          const grid = document.getElementById('setores-grid');
          grid.innerHTML = ''; 
          setoresLista.forEach(setor => {
              const isActive = setor === filtroSetorAtivo;
              const btn = document.createElement('button');
              btn.textContent = setor;
              btn.className = `my-matriz-btn my-matriz-btn-small ${isActive ? 'my-matriz-btn-copy-email' : 'my-matriz-btn-small-email'}`; 
              btn.style.width = '100%';
              btn.onclick = () => { aplicarFiltroSetor(isActive ? '' : setor); };
              grid.appendChild(btn);
          });
      }

      function renderTable() {
          const tbody = document.getElementById('table-body');
          const emptyState = document.getElementById('empty-state');
          
          if (filteredEmployees.length === 0) {
              tbody.innerHTML = '';
              emptyState.style.display = 'block';
              return;
          }
          emptyState.style.display = 'none';
          tbody.innerHTML = filteredEmployees.map(emp => {
              const ramal = emp.ramal || '-';
              const celular = emp.celular_corporativo || '-';
              const email = emp.email || '-';
              const setor = emp.setor || '-'; 
              return `
                  <tr>
                  <td>${emp.nome}</td>
                  <td>${setor}</td>
                  <td class="email-cell">${email}</td>
                  <td class="phone-cell">${celular}</td> 
                  <td class="ramal-cell">${ramal}</td>
                  <td class="actions-cell">
                      <button class="my-matriz-btn my-matriz-btn-small my-matriz-btn-small-email" onclick="copyContact('${email}', 'E-mail')">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                      </button>
                      <button class="my-matriz-btn my-matriz-btn-small my-matriz-btn-small-phone" onclick="copyContact('${celular}', 'Celular')">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12" y2="18"></line></svg>
                      </button>
                  </td>
                  </tr>
              `;
          }).join('');
      }

      function filterEmployees() {
          const searchTerm = document.getElementById('search-input').value.toLowerCase();
          let listaParaFiltrar = employees;
          if (filtroSetorAtivo) {
              listaParaFiltrar = employees.filter(emp => emp.setor.toLowerCase() === filtroSetorAtivo.toLowerCase());
          }
          filteredEmployees = listaParaFiltrar.filter(emp => {
              return (emp.nome && emp.nome.toLowerCase().includes(searchTerm)) ||
                  (emp.setor && emp.setor.toLowerCase().includes(searchTerm)) ||
                  (emp.email && emp.email.toLowerCase().includes(searchTerm)) ||
                  (emp.ramal && emp.ramal.toLowerCase().includes(searchTerm)) ||
                  (emp.celular_corporativo && emp.celular_corporativo.toLowerCase().includes(searchTerm));
          });
          renderTable();
      }

      renderTable();
      document.getElementById('search-input').addEventListener('input', filterEmployees);
      document.getElementById('copy-all-btn').addEventListener('click', () => copyAll(true));
      document.getElementById('copy-all-phone-btn').addEventListener('click', () => copyAll(false));
      
      document.getElementById('setores-btn').addEventListener('click', () => {
          renderSetoresModal();
          document.getElementById('setores-modal').classList.add('show');
      });
      document.getElementById('close-setor-modal').addEventListener('click', () => {
          document.getElementById('setores-modal').classList.remove('show');
      });
      document.getElementById('clear-setor-btn').addEventListener('click', () => {
          aplicarFiltroSetor('');
      });
  }); 
</script>

<?php include 'includes/footer.php'; ?>