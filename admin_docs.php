<?php
/**
 * =============================================================================
 * PAINEL DE CONTROLE DE DOCUMENTAÇÃO - NAVI PRO
 * =============================================================================
 *
 * Gerenciador de arquivos e pastas para a base de documentação interna.
 *
 * ARQUITETURA DE SEGURANÇA:
 *   - Todas as operações de filesystem passam pela função resolverCaminhoSeguro(),
 *     que usa realpath() para garantir que o destino final esteja dentro de
 *     $RAIZ_DOCS. Isso elimina qualquer vetor de directory traversal.
 *   - Nomes de arquivos e pastas são sanitizados por sanitizarNome() antes
 *     de qualquer uso.
 *   - Toda operação de escrita/exclusão/renomeação chama registrarLog()
 *     ANTES de retornar, tornando a auditoria obrigatória por design.
 *
 * REQUISITOS DE NEGÓCIO:
 *   - Permissão: $_SESSION['pode_gerenciar_docs'] === true
 *   - Log captura: user_id, ip, acao, caminho_completo, timestamp
 *   - Hierarquia de pastas: criação respeita o path_pai informado
 *   - Renomeação de pastas com log de caminho_antigo e caminho_novo
 *
 * DEPENDÊNCIAS:
 *   - config.php  → $pdo_intra, session_start(), função registrarLog()
 *   - Extensão GD (geração de barcode, se aplicável)
 *   - PHP >= 8.0 (str_ends_with, match)
 *
 * @version 2.0
 * @since   2025
 * =============================================================================
 */

require_once 'config.php';

// ---------------------------------------------------------------------------
// 1. CONTROLE DE ACESSO
// ---------------------------------------------------------------------------
// Bloqueia qualquer usuário sem permissão explícita de gestão de docs.
if (!isset($_SESSION['pode_gerenciar_docs']) || $_SESSION['pode_gerenciar_docs'] !== true) {
    header('Location: index.php');
    exit;
}

// ---------------------------------------------------------------------------
// 2. CONSTANTES E VARIÁVEIS GLOBAIS DE SESSÃO
// ---------------------------------------------------------------------------

/** Diretório raiz de documentos — ÚNICO ponto de verdade para validação de paths. */
define('RAIZ_DOCS', realpath(__DIR__ . '/docs') . DIRECTORY_SEPARATOR);

/** Diretório de imagens/mídia. */
define('RAIZ_IMG', realpath(__DIR__ . '/img') . DIRECTORY_SEPARATOR);

// Dados do usuário logado, extraídos da sessão para uso nos logs.
$user_id   = (int)  ($_SESSION['user_id']   ?? 0);
$user_name = (string)($_SESSION['user_name'] ?? 'Sistema');
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Variável de feedback para o template HTML.
$mensagem = '';

// ---------------------------------------------------------------------------
// 3. FUNÇÕES AUXILIARES DE SEGURANÇA
// ---------------------------------------------------------------------------

/**
 * Resolve e valida um caminho, garantindo que esteja dentro do diretório raiz.
 *
 * Usa realpath() para expandir '..', links simbólicos e redundâncias antes
 * de comparar com a raiz. Se o caminho não existir (ex.: nova pasta/arquivo
 * que será criado), constrói o caminho absoluto sem usar realpath() no destino,
 * mas valida que o DIRETÓRIO PAI já existe e está dentro da raiz.
 *
 * @param  string $raiz       Diretório raiz permitido (ex.: RAIZ_DOCS).
 * @param  string $caminho    Caminho relativo ou absoluto a validar.
 * @param  bool   $existente  Se true, o caminho final deve existir (para leitura/exclusão).
 * @return string|false       Caminho absoluto validado, ou false em caso de violação.
 */
function resolverCaminhoSeguro(string $raiz, string $caminho, bool $existente = true): string|false
{
    // Para caminhos que já devem existir, usa realpath() direto.
    if ($existente) {
        $real = realpath($caminho);
        if ($real === false) {
            return false; // Arquivo/pasta não existe.
        }
        // Verifica se começa com a raiz permitida.
        if (strncmp($real, $raiz, strlen($raiz)) !== 0) {
            return false; // Directory traversal detectado.
        }
        return $real;
    }

    // Para caminhos que serão criados: resolve o diretório pai e concatena o nome.
    $pai  = realpath(dirname($caminho));
    $nome = basename($caminho);

    if ($pai === false) {
        return false; // Diretório pai não existe.
    }
    if (strncmp($pai, $raiz, strlen($raiz)) !== 0) {
        return false; // Pai fora da raiz — traversal no nome.
    }

    return $pai . DIRECTORY_SEPARATOR . $nome;
}

/**
 * Sanitiza um nome de arquivo ou pasta.
 *
 * Remove caracteres perigosos, barras, pontos duplos e espaços excessivos.
 * Preserva letras, números, underscores, hífens e pontos simples.
 *
 * @param  string $nome  Nome bruto enviado pelo usuário.
 * @return string        Nome seguro, ou string vazia se inválido.
 */
function sanitizarNome(string $nome): string
{
    // Remove path separators e sequências de pontos (traversal).
    $nome = str_replace(['/', '\\', '..'], '', $nome);

    // MÁGICA AQUI: Mantém letras (com acentos), números, _, -, ponto e ESPAÇOS!
    $nome = preg_replace('/[^\p{L}\p{N}_\-\.\s]/u', '', $nome);
    
    // Colapsa múltiplos pontos em um único.
    $nome = preg_replace('/\.{2,}/', '.', $nome);

    return trim($nome);
}

/**
 * Valida que um nome de pasta não contém extensão (pastas não têm '.').
 * Previne que alguém crie "../../etc" ou "pasta.exe".
 *
 * @param  string $nome  Nome da pasta.
 * @return bool
 */
function nomePastaValido(string $nome): bool
{
    if (empty($nome)) return false;
    // Pastas não devem conter ponto (sem extensão).
    if (strpos($nome, '.') !== false) return false;
    
    // Apenas letras, números, _, - e ESPAÇOS (com suporte a acentos)
    return (bool) preg_match('/^[\p{L}\p{N}_\-\s]+$/u', $nome);
}

// ---------------------------------------------------------------------------
// 4. NÚCLEO DE AUDITORIA — registrarLog() centralizado
// ---------------------------------------------------------------------------
/**
 * Wrapper local que garante a assinatura correta para todas as operações deste módulo.
 *
 * A função registrarLog() original (definida em config.php) pode ter uma
 * assinatura diferente. Este wrapper normaliza a chamada e adiciona o
 * caminho completo nos detalhes de forma padronizada.
 *
 * @param string $acao      Identificador da ação (ex.: 'PASTA_CRIADA').
 * @param array  $detalhes  Array associativo com os dados do evento.
 */
function auditoria(string $acao, array $detalhes): void
{
    global $pdo_intra, $user_id, $user_name, $ip_address;

    // Monta a string de detalhes de forma consistente para todos os logs.
    $detalhe_str = json_encode(array_merge([
        'usuario_nome' => $user_name,
        'usuario_id'   => $user_id,
        'ip'           => $ip_address,
        'timestamp'    => date('Y-m-d H:i:s'),
    ], $detalhes), JSON_UNESCAPED_UNICODE);

    // Chama a função global de log definida em config.php.
    // Assinatura esperada: registrarLog(PDO $pdo, string $acao, string $detalhes, int $user_id, string $ip)
    registrarLog($pdo_intra, $acao, $detalhe_str, $user_id, $ip_address);
}

// ---------------------------------------------------------------------------
// 5. OPERAÇÕES DE FILESYSTEM
// ---------------------------------------------------------------------------
// Cada bloco abaixo representa uma operação. Todas seguem o mesmo padrão:
//   1. Lê e sanitiza os inputs.
//   2. Resolve e valida o caminho via resolverCaminhoSeguro().
//   3. Executa a operação nativa do PHP (mkdir, rename, unlink).
//   4. Chama auditoria() — OBRIGATORIAMENTE — antes de definir $mensagem.

// -----------------------------------------------------------------------
// 5.1 EXCLUIR ARQUIVO DE DOCUMENTO (.md ou .pdf)
// -----------------------------------------------------------------------
if (isset($_GET['excluir_arq'])) {
    /*
     * O caminho chega em base64 para suportar caminhos longos na URL.
     * Mesmo assim, é validado via resolverCaminhoSeguro() — o base64
     * não oferece nenhuma proteção em si.
     */
    $caminho_raw = base64_decode($_GET['excluir_arq'], true);

    if ($caminho_raw !== false) {
        $caminho_real = resolverCaminhoSeguro(RAIZ_DOCS, $caminho_raw, existente: true);

        if ($caminho_real && is_file($caminho_real)) {
            $nome_arquivo = basename($caminho_real);
            unlink($caminho_real);

            auditoria('ARQUIVO_EXCLUIDO', [
                'caminho_completo' => $caminho_real,
                'nome_arquivo'     => $nome_arquivo,
            ]);

            $mensagem = "🗑️ Arquivo '$nome_arquivo' excluído com sucesso!";
        } else {
            $mensagem = '❌ Arquivo inválido ou fora do diretório permitido.';
        }
    }
}

// -----------------------------------------------------------------------
// 5.2 EXCLUIR IMAGEM DA BIBLIOTECA DE MÍDIA
// -----------------------------------------------------------------------
if (isset($_GET['excluir_img'])) {
    $caminho_raw = base64_decode($_GET['excluir_img'], true);

    if ($caminho_raw !== false) {
        $caminho_real = resolverCaminhoSeguro(RAIZ_IMG, $caminho_raw, existente: true);

        if ($caminho_real && is_file($caminho_real)) {
            $nome_img = basename($caminho_real);
            unlink($caminho_real);

            auditoria('MIDIA_EXCLUIDA', [
                'caminho_completo' => $caminho_real,
                'nome_arquivo'     => $nome_img,
            ]);

            $mensagem = "🗑️ Imagem '$nome_img' removida da biblioteca!";
        } else {
            $mensagem = '❌ Imagem inválida ou fora do diretório permitido.';
        }
    }
}

// -----------------------------------------------------------------------
// 5.3 RENOMEAR IMAGEM DA BIBLIOTECA DE MÍDIA
// -----------------------------------------------------------------------
if (isset($_GET['acao']) && $_GET['acao'] === 'renomear_midia') {
    /*
     * Usa basename() nos nomes para garantir que não haja path separators
     * injetados. Em seguida, valida via resolverCaminhoSeguro().
     */
    $nome_antigo = sanitizarNome(basename((string)($_GET['antigo'] ?? '')));
    $nome_novo   = sanitizarNome(basename((string)($_GET['novo']   ?? '')));

    if ($nome_antigo && $nome_novo && $nome_antigo !== $nome_novo) {
        $path_antigo = resolverCaminhoSeguro(RAIZ_IMG, RAIZ_IMG . $nome_antigo, existente: true);
        $path_novo   = resolverCaminhoSeguro(RAIZ_IMG, RAIZ_IMG . $nome_novo,   existente: false);

        if ($path_antigo && $path_novo && !file_exists($path_novo)) {
            rename($path_antigo, $path_novo);

            auditoria('MIDIA_RENOMEADA', [
                'caminho_antigo' => $path_antigo,
                'caminho_novo'   => $path_novo,
            ]);

            header('Location: admin_docs.php?msg=' . urlencode("Imagem renomeada com sucesso!"));
            exit;
        } else {
            $mensagem = '❌ Renomeação inválida: nome de destino já existe ou está fora do diretório permitido.';
        }
    }
}

// -----------------------------------------------------------------------
// 5.4 RENOMEAR PASTA DE DOCUMENTOS
// -----------------------------------------------------------------------
if (isset($_GET['acao']) && $_GET['acao'] === 'renomear_pasta') {
    /*
     * Recebe o path_pai (relativo a RAIZ_DOCS) e os nomes antigo/novo.
     * Exemplo: path_pai=RH, antigo=ADMISSAO, novo=ADMISSAO_2024
     * Resulta em: /docs/RH/ADMISSAO/ → /docs/RH/ADMISSAO_2024/
     */
    $path_pai_raw = trim((string)($_GET['path_pai'] ?? ''), '/\\');
    $nome_antigo  = sanitizarNome(basename((string)($_GET['antigo'] ?? '')));
    $nome_novo    = sanitizarNome(basename((string)($_GET['novo']   ?? '')));

    // Nomes de pasta: somente maiúsculas, números, underscore e hífen.
    $nome_novo_up = strtoupper($nome_novo);

    if ($nome_antigo && nomePastaValido(strtoupper($nome_novo_up))) {
        // Monta o path base seguro do diretório pai.
        $base_pai_raw = RAIZ_DOCS . $path_pai_raw;
        $base_pai     = resolverCaminhoSeguro(RAIZ_DOCS, $base_pai_raw, existente: true);

        if ($base_pai === false) {
            $mensagem = '❌ Diretório pai inválido ou fora do escopo permitido.';
        } else {
            $path_antigo = resolverCaminhoSeguro(RAIZ_DOCS, $base_pai . DIRECTORY_SEPARATOR . $nome_antigo, existente: true);
            $path_novo   = resolverCaminhoSeguro(RAIZ_DOCS, $base_pai . DIRECTORY_SEPARATOR . $nome_novo_up, existente: false);

            if ($path_antigo && is_dir($path_antigo) && $path_novo && !file_exists($path_novo)) {
                rename($path_antigo, $path_novo);

                // Log captura AMBOS os caminhos completos conforme requisito.
                auditoria('PASTA_RENOMEADA', [
                    'caminho_antigo' => $path_antigo,
                    'caminho_novo'   => $path_novo,
                ]);

                header('Location: admin_docs.php?msg=' . urlencode("Pasta renomeada: $nome_antigo → $nome_novo_up"));
                exit;
            } else {
                $mensagem = '❌ Renomeação inválida: pasta destino já existe, origem inexistente, ou traversal detectado.';
            }
        }
    } else {
        $mensagem = '❌ Nome de pasta inválido. Use apenas letras maiúsculas, números, _ e -.';
    }
}

// -----------------------------------------------------------------------
// 5.5 CARREGAR ARQUIVO PARA EDIÇÃO (leitura — sem escrita)
// -----------------------------------------------------------------------
$conteudo_editar = '';
$nome_editar     = '';
$setor_editar    = '';

if (isset($_GET['editar'])) {
    $arquivo_path_raw = base64_decode($_GET['editar'], true);

    if ($arquivo_path_raw !== false) {
        $arquivo_real = resolverCaminhoSeguro(RAIZ_DOCS, $arquivo_path_raw, existente: true);

        if ($arquivo_real && is_file($arquivo_real)) {
            $conteudo_editar = file_get_contents($arquivo_real);
            $nome_editar     = basename($arquivo_real);
            // Setor = nome da pasta imediatamente pai dentro de /docs/.
            $setor_editar    = basename(dirname($arquivo_real));
        }
    }
}

// -----------------------------------------------------------------------
// 5.6 SALVAR OU CRIAR ARQUIVO .MD
// -----------------------------------------------------------------------
if (isset($_POST['salvar_documento'])) {
    // Tira o sanitizarNome do setor para não quebrar as barras (/) das subpastas
    $setor        = trim((string)($_POST['setor_destino'] ?? ''), '/\\');
    $nome_arquivo = sanitizarNome(trim((string)($_POST['nome_arquivo'] ?? '')));
    $conteudo     = (string)($_POST['conteudo_md'] ?? '');

    // Garante extensão .md
    if (!str_ends_with(strtolower($nome_arquivo), '.md')) {
        $nome_arquivo .= '.md';
    }

    $caminho_raw  = RAIZ_DOCS . $setor . DIRECTORY_SEPARATOR . $nome_arquivo;
    $caminho_real = resolverCaminhoSeguro(RAIZ_DOCS, $caminho_raw, existente: false);

    if ($caminho_real && is_dir(dirname($caminho_real))) {
        if (file_put_contents($caminho_real, $conteudo) !== false) {
            $acao_log = isset($_GET['editar']) ? 'ARQUIVO_EDITADO' : 'ARQUIVO_CRIADO';

            auditoria($acao_log, [
                'caminho_completo' => $caminho_real,
                'nome_arquivo'     => $nome_arquivo,
                'setor'            => $setor,
            ]);

            $mensagem     = "✅ Documento '$nome_arquivo' salvo com sucesso!";
            $conteudo_editar = $conteudo;
            $nome_editar     = $nome_arquivo;
            $setor_editar    = $setor;
        } else {
            $mensagem = '❌ Erro ao salvar. Verifique permissões do diretório.';
        }
    } else {
        $mensagem = '❌ Pasta de destino inválida ou fora do escopo permitido.';
    }
}

// -----------------------------------------------------------------------
// 5.7 UPLOAD DE ARQUIVOS (PDF / WORD)
// -----------------------------------------------------------------------
if (!empty($_FILES['arquivo_pdf']['name']) && isset($_POST['setor_pdf'])) {
    // Tira o sanitizarNome do setor para preservar as barras (\ ou /)
    $setor    = trim((string)($_POST['setor_pdf'] ?? ''), '/\\');
    $nome_pdf = sanitizarNome(basename($_FILES['arquivo_pdf']['name']));
    $ext      = strtolower(pathinfo($nome_pdf, PATHINFO_EXTENSION));

    $extensoes_permitidas = ['pdf', 'doc', 'docx'];

    if (!in_array($ext, $extensoes_permitidas)) {
        $mensagem = '❌ ERRO: Apenas arquivos PDF e Word (.doc, .docx) são aceitos!';
    } else {
        $caminho_raw  = RAIZ_DOCS . $setor . DIRECTORY_SEPARATOR . $nome_pdf;
        $caminho_real = resolverCaminhoSeguro(RAIZ_DOCS, $caminho_raw, existente: false);

        if ($caminho_real && is_dir(dirname($caminho_real))) {
            if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $caminho_real)) {
                auditoria('DOCUMENTO_IMPORTADO', [
                    'caminho_completo' => $caminho_real,
                    'nome_arquivo'     => $nome_pdf,
                    'setor'            => $setor,
                ]);
                $mensagem = "📄 Documento '$nome_pdf' importado com sucesso para '$setor'!";
            } else {
                $mensagem = '❌ Erro ao mover o arquivo. Verifique permissões.';
            }
        } else {
            $mensagem = '❌ Setor de destino inválido.';
        }
    }
}

// -----------------------------------------------------------------------
// 5.8 CRIAR NOVA PASTA (com suporte a path_pai hierárquico)
// -----------------------------------------------------------------------
if (isset($_POST['nova_pasta']) && !empty($_POST['nome_pasta'])) {
    /*
     * path_pai é um caminho relativo a RAIZ_DOCS.
     * Exemplos:
     *   path_pai = ""       → cria /docs/NOVA_PASTA/
     *   path_pai = "RH"     → cria /docs/RH/NOVA_PASTA/
     *   path_pai = "RH/TI"  → cria /docs/RH/TI/NOVA_PASTA/
     *
     * O path_pai é validado para garantir que seja um diretório
     * existente dentro de RAIZ_DOCS antes de criar a subpasta.
     */
    $path_pai_raw = trim((string)($_POST['path_pai'] ?? ''), '/\\');
    $nome_nova    = strtoupper(sanitizarNome(trim((string)$_POST['nome_pasta'])));

    if (!nomePastaValido($nome_nova)) {
        $mensagem = '❌ Nome de pasta inválido. Use apenas letras, números, _ e -.';
    } else {
        // Resolve o diretório pai dentro de RAIZ_DOCS.
        if ($path_pai_raw !== '') {
            $path_pai_abs = resolverCaminhoSeguro(RAIZ_DOCS, RAIZ_DOCS . $path_pai_raw, existente: true);
        } else {
            // Sem path_pai: cria diretamente em RAIZ_DOCS.
            $path_pai_abs = rtrim(RAIZ_DOCS, DIRECTORY_SEPARATOR);
        }

        if (!$path_pai_abs || !is_dir($path_pai_abs)) {
            $mensagem = '❌ Diretório pai inválido ou inexistente.';
        } else {
            $nova_pasta_raw  = $path_pai_abs . DIRECTORY_SEPARATOR . $nome_nova;
            $nova_pasta_real = resolverCaminhoSeguro(RAIZ_DOCS, $nova_pasta_raw, existente: false);

            if (!$nova_pasta_real) {
                $mensagem = '❌ Caminho de pasta destino fora do escopo permitido.';
            } elseif (is_dir($nova_pasta_real)) {
                $mensagem = "⚠️ A pasta '$nome_nova' já existe neste diretório.";
            } else {
                if (mkdir($nova_pasta_real, 0755, true)) {
                    // Caminho relativo para exibição amigável no log.
                    $caminho_relativo = str_replace(RAIZ_DOCS, '', $nova_pasta_real);

                    auditoria('PASTA_CRIADA', [
                        'caminho_completo' => $nova_pasta_real,
                        'caminho_relativo' => $caminho_relativo,
                        'path_pai'         => $path_pai_raw ?: '(raiz)',
                        'nome_pasta'       => $nome_nova,
                    ]);

                    $mensagem = "✅ Pasta '$caminho_relativo' criada com sucesso!";
                } else {
                    $mensagem = '❌ Erro ao criar pasta. Verifique permissões do servidor.';
                }
            }
        }
    }
}

// -----------------------------------------------------------------------
// 5.9 UPLOAD DE IMAGEM PARA BIBLIOTECA DE MÍDIA
// -----------------------------------------------------------------------
if (!empty($_FILES['arquivo_img']['name'])) {
    $nome_img = sanitizarNome(basename($_FILES['arquivo_img']['name']));
    // Remove espaços remanescentes por segurança.
    $nome_img = str_replace(' ', '_', $nome_img);

    $caminho_raw  = RAIZ_IMG . $nome_img;
    $caminho_real = resolverCaminhoSeguro(RAIZ_IMG, $caminho_raw, existente: false);

    if ($caminho_real) {
        if (move_uploaded_file($_FILES['arquivo_img']['tmp_name'], $caminho_real)) {
            auditoria('MIDIA_ENVIADA', [
                'caminho_completo' => $caminho_real,
                'nome_arquivo'     => $nome_img,
            ]);

            // Suporte a upload AJAX (retorna texto simples e encerra).
            if (isset($_POST['ajax_upload'])) {
                echo 'success';
                exit;
            }

            $mensagem = "🖼️ Imagem '$nome_img' enviada com sucesso!";
        }
    }
}

// ---------------------------------------------------------------------------
// 6. LISTAGEM DE PASTAS E IMAGENS PARA O TEMPLATE
// ---------------------------------------------------------------------------

/**
 * Escaneia recursivamente o diretório de documentos e retorna
 * uma árvore de pastas com seus arquivos (.md e .pdf).
 *
 * @param  string $diretorio  Caminho absoluto a escanear.
 * @param  string $raiz       RAIZ_DOCS, para calcular caminhos relativos.
 * @return array              Árvore associativa [relativo => [arquivos, subpastas]].
 */
function escanearDocumentos(string $diretorio, string $raiz): array
{
    $resultado = [];
    $entradas  = scandir($diretorio);

    if (!$entradas) return $resultado;

    foreach ($entradas as $entrada) {
        if ($entrada === '.' || $entrada === '..') continue;
        $caminho_abs = $diretorio . DIRECTORY_SEPARATOR . $entrada;

        if (is_dir($caminho_abs)) {
            $relativo = str_replace($raiz, '', $caminho_abs);
            $arquivos = array_values(array_filter(
                glob($caminho_abs . '/*.{md,pdf,doc,docx}', GLOB_BRACE) ?: [],
                'is_file'
            ));
            sort($arquivos);

            $resultado[$relativo] = [
                'abs'        => $caminho_abs,
                'nome'       => $entrada,
                'relativo'   => $relativo,
                'arquivos'   => $arquivos,
                'subpastas'  => escanearDocumentos($caminho_abs, $raiz),
            ];
        }
    }

    ksort($resultado);
    return $resultado;
}

$arvore_docs      = escanearDocumentos(rtrim(RAIZ_DOCS, DIRECTORY_SEPARATOR), RAIZ_DOCS);
$imagens_biblioteca = array_values(array_filter(glob(RAIZ_IMG . '*') ?: [], 'is_file'));

// Lista plana de pastas para os <select> de destino (PDF, .md, etc.)
$lista_pastas_plana = [];
array_walk_recursive($arvore_docs, function($item, $key) use (&$lista_pastas_plana, $arvore_docs) {});

/**
 * Achata a árvore de pastas em uma lista plana de caminhos relativos.
 * Usado para popular os <select> de destino no formulário.
 */
function aplainarPastas(array $arvore, string $prefixo = ''): array
{
    $lista = [];
    foreach ($arvore as $relativo => $dados) {
        $lista[] = $relativo;
        if (!empty($dados['subpastas'])) {
            $lista = array_merge($lista, aplainarPastas($dados['subpastas']));
        }
    }
    return $lista;
}

$lista_pastas_plana = aplainarPastas($arvore_docs);

// Mensagem de retorno de redirect (ex.: após renomear).
if (empty($mensagem) && isset($_GET['msg'])) {
    $mensagem = htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor PRO — NAVI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code&family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .editor-font { font-family: 'Fira Code', monospace; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* Renderização do preview Markdown */
        #preview_md h1 { font-size: 1.75rem; font-weight: 900; color: #0f172a; margin: 1.5rem 0 1rem; border-bottom: 2px solid #f1f5f9; padding-bottom: .5rem; }
        #preview_md h2 { font-size: 1.4rem; font-weight: 800; color: #1e293b; margin: 1.2rem 0 0.8rem; }
        #preview_md h3 { font-size: 1.1rem; font-weight: 700; color: #334155; margin: 1rem 0 0.5rem; }
        #preview_md p  { margin-bottom: 1rem; color: #475569; line-height: 1.7; }
        #preview_md ul { list-style: disc; margin-left: 1.5rem; margin-bottom: 1rem; }
        #preview_md strong { font-weight: 700; color: #0f172a; }
        #preview_md img { max-width: 100%; border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,.05); margin: 1rem 0; }
        #preview_md code { background: #f1f5f9; padding: .15rem .4rem; border-radius: .3rem; font-family: 'Fira Code', monospace; font-size: .85em; }
        #preview_md pre  { background: #0f172a; color: #34d399; padding: 1rem; border-radius: .75rem; overflow-x: auto; margin-bottom: 1rem; }

        .alert-info   { background: #eff6ff; border-left: 4px solid #2563eb; padding: 1rem; border-radius: .5rem; color: #1e40af; font-weight: 600; font-size: .85rem; margin: 1rem 0; }
        .alert-danger { background: #fef2f2; border-left: 4px solid #dc2626; padding: 1rem; border-radius: .5rem; color: #991b1b; font-weight: 600; font-size: .85rem; margin: 1rem 0; }

        /* Hierarquia de pastas */
        .pasta-raiz   { border-left: 3px solid #2563eb; }
        .pasta-filha  { border-left: 3px solid #94a3b8; margin-left: 1rem; }
        .pasta-toggle { cursor: pointer; user-select: none; }
    </style>
</head>
<body class="bg-slate-100 p-4">

<div class="max-w-[1800px] mx-auto space-y-4">

    <!-- ================================================================ -->
    <!-- CABEÇALHO                                                         -->
    <!-- ================================================================ -->
    <header class="flex justify-between items-center bg-slate-900 p-5 rounded-3xl text-white shadow-xl border border-white/5">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-xl shadow-lg shadow-blue-500/20">📝</div>
            <div>
                <h1 class="text-lg font-black tracking-tighter uppercase italic leading-none">Editor PRO</h1>
                <p class="text-blue-400 text-[9px] font-bold uppercase tracking-widest mt-1">Gerenciador de Documentação</p>
            </div>
        </div>
        <div class="flex gap-3 flex-wrap">
            <button onclick="toggleModalPasta()" class="bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 rounded-xl text-[10px] font-black transition-all shadow-lg uppercase tracking-widest">+ Nova Pasta</button>
            <button onclick="toggleModalMidia()" class="bg-orange-500 hover:bg-orange-600 px-5 py-2.5 rounded-xl text-[10px] font-black transition-all shadow-lg uppercase tracking-widest">Biblioteca de Mídia</button>
            <a href="admin_docs.php"
               onclick="localStorage.removeItem('rascunho_navi_novo'); localStorage.removeItem('rascunho_navi_' + (document.getElementById('nome_arquivo')?.value||''));"
               class="bg-white/5 hover:bg-white/10 px-4 py-2.5 rounded-xl text-[10px] font-bold transition-all border border-white/5">
                LIMPAR / NOVO
            </a>
            <a href="index.php" class="bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white px-4 py-2.5 rounded-xl text-[10px] font-bold transition-all border border-red-500/20 uppercase">Sair</a>
        </div>
    </header>

    <!-- Mensagem de feedback -->
    <?php if ($mensagem): ?>
        <div class="p-3 rounded-2xl font-bold text-xs text-center shadow-lg <?= str_contains($mensagem, '❌') ? 'bg-red-600' : 'bg-emerald-600' ?> text-white">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LAYOUT PRINCIPAL: Sidebar + Editor                                -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

        <!-- ------------------------------------------------------------ -->
        <!-- SIDEBAR                                                        -->
        <!-- ------------------------------------------------------------ -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Árvore de documentos -->
            <section class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200">
                <h3 class="text-[10px] font-black text-slate-400 uppercase mb-4 tracking-widest">Documentos</h3>
                <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1 custom-scrollbar">
                    <?php
                    /**
                     * Renderiza recursivamente a árvore de pastas/arquivos.
                     * Função interna — definida aqui para ter acesso a $nome_editar.
                     */
                    function renderizarArvore(array $arvore, string $nome_editar, int $nivel = 0): void
                    {
                        foreach ($arvore as $relativo => $dados) {
                            $nome_pasta = $dados['nome'];
                            $classe_nivel = $nivel === 0 ? 'pasta-raiz' : 'pasta-filha';
                            $id_toggle = 'pasta_' . md5($relativo);
                            ?>
                            <div class="<?= $classe_nivel ?> pl-2 rounded-r-lg mb-1">
                                <!-- Cabeçalho da pasta com ações -->
                                <div class="flex items-center justify-between group py-0.5">
                                    <p class="pasta-toggle text-[9px] font-black text-blue-600 uppercase cursor-pointer hover:text-blue-800 transition-colors"
                                       onclick="togglePastaVisual('<?= $id_toggle ?>')">
                                        📁 <?= htmlspecialchars($nome_pasta, ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <!-- Ações da pasta: renomear -->
                                    <div class="opacity-0 group-hover:opacity-100 flex gap-1 transition-opacity">
                                        <button
                                            onclick="renomearPasta('<?= htmlspecialchars($relativo, ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($nome_pasta, ENT_QUOTES, 'UTF-8') ?>')"
                                            title="Renomear pasta"
                                            class="text-[8px] bg-orange-100 text-orange-600 hover:bg-orange-500 hover:text-white px-1 py-0.5 rounded transition-all">
                                            ✏️
                                        </button>
                                    </div>
                                </div>

                                <!-- Arquivos da pasta -->
                                <ul id="<?= $id_toggle ?>" class="space-y-0.5 ml-1 border-l border-slate-100 pl-2 mt-1">
                                    <?php foreach ($dados['arquivos'] as $arq):
                                        $nome_arq  = basename($arq);
                                        $ext_arq   = strtolower(pathinfo($nome_arq, PATHINFO_EXTENSION));
                                        $link_del  = 'admin_docs.php?excluir_arq=' . base64_encode($arq);
                                        $link_edit = ($ext_arq === 'md')
                                            ? 'admin_docs.php?editar=' . base64_encode($arq)
                                            : '#';
                                        $icone = '📄';
                                        if ($ext_arq === 'md') $icone = '📝';
                                        elseif ($ext_arq === 'pdf') $icone = '📕';
                                        elseif (in_array($ext_arq, ['doc', 'docx'])) $icone = '📘';
                                        $estilo    = ($nome_arq === $nome_editar)
                                            ? 'text-blue-700 font-bold bg-blue-50'
                                            : 'text-slate-500 hover:text-blue-600';
                                    ?>
                                    <li class="flex justify-between items-center group/arq">
                                        <a href="<?= $link_edit ?>"
                                           class="text-[10px] truncate block py-0.5 transition-all rounded px-1 flex-1 <?= $estilo ?>"
                                           title="<?= htmlspecialchars($nome_arq, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $icone ?> <?= htmlspecialchars($nome_arq, ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <a href="<?= $link_del ?>"
                                           onclick="return confirm('Excluir <?= htmlspecialchars($nome_arq, ENT_QUOTES, 'UTF-8') ?> definitivamente?')"
                                           class="text-[9px] bg-red-100 text-red-600 px-1 py-0.5 rounded opacity-0 group-hover/arq:opacity-100 transition-opacity shrink-0"
                                           title="Excluir">🗑️</a>
                                    </li>
                                    <?php endforeach; ?>

                                    <?php if (empty($dados['arquivos']) && empty($dados['subpastas'])): ?>
                                        <li class="text-[9px] text-slate-300 italic py-0.5">pasta vazia</li>
                                    <?php endif; ?>
                                </ul>

                                <!-- Subpastas recursivas -->
                                <?php if (!empty($dados['subpastas'])): ?>
                                    <div class="mt-1">
                                        <?php renderizarArvore($dados['subpastas'], $nome_editar, $nivel + 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                        }
                    }

                    renderizarArvore($arvore_docs, $nome_editar);
                    ?>
                </div>
            </section>

            <!-- Upload de PDF -->
            <section class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200">
                <h3 class="text-[10px] font-black text-red-500 uppercase mb-3 tracking-widest">📄 Importar Arquivo</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                    <select name="setor_pdf" class="w-full bg-slate-50 border border-slate-200 p-2 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-red-500">
                        <?php foreach ($lista_pastas_plana as $rel):
                            echo '<option value="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '">'
                                . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '</option>';
                        endforeach; ?>
                    </select>
                    <input type="file" name="arquivo_pdf" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required
                           class="w-full text-[10px] file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-red-50 file:text-red-600 hover:file:bg-red-100 cursor-pointer">
                    <button type="submit" class="w-full bg-red-600 text-white px-4 py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest shadow-lg hover:bg-red-700 transition-all">
                        Subir Arquivo ⬆️
                    </button>
                </form>
            </section>

        </div><!-- /sidebar -->

        <!-- ------------------------------------------------------------ -->
        <!-- ÁREA DO EDITOR                                                 -->
        <!-- ------------------------------------------------------------ -->
        <div class="lg:col-span-10">
            <form method="POST" id="form_principal" class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-200 space-y-4">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-3">Nome do Arquivo (.md)</label>
                        <input type="text" id="nome_arquivo" name="nome_arquivo" required
                               value="<?= htmlspecialchars($nome_editar, ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="ex: procedimento-admissao.md"
                               class="w-full bg-slate-50 border border-slate-200 p-3 rounded-2xl text-sm outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-3">Pasta de Destino</label>
                        <select name="setor_destino" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-2xl text-sm font-bold">
                            <?php foreach ($lista_pastas_plana as $rel):
                                $sel = ($rel === $setor_editar || basename($rel) === $setor_editar) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '" ' . $sel . '>'
                                    . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '</option>';
                            endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Barra de ferramentas do editor -->
                <div class="flex flex-wrap gap-2 py-2 border-y border-slate-50">
                    <button type="button" onclick="inserirFormato('**','**')" class="px-3 py-2 bg-slate-100 rounded-xl text-[10px] font-black hover:bg-slate-900 hover:text-white transition-all">NEGRITO</button>
                    <button type="button" onclick="inserirFormato('### ','')" class="px-3 py-2 bg-slate-100 rounded-xl text-[10px] font-black hover:bg-slate-900 hover:text-white transition-all">TÍTULO</button>
                    <button type="button" onclick="inserirTemplate('info')"      class="px-4 py-2 bg-blue-100 text-blue-700 rounded-xl text-[10px] font-black hover:bg-blue-600 hover:text-white transition-all uppercase tracking-widest">📘 Info Box</button>
                    <button type="button" onclick="inserirTemplate('danger')"    class="px-4 py-2 bg-red-100 text-red-700 rounded-xl text-[10px] font-black hover:bg-red-600 hover:text-white transition-all uppercase tracking-widest">⚠️ Atenção</button>
                    <button type="button" onclick="inserirTemplate('separador')" class="px-3 py-2 bg-slate-800 text-white rounded-xl text-[10px] font-black hover:bg-black transition-all">LINHA</button>
                </div>

                <!-- Editor + Preview lado a lado -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 h-[650px]">
                    <div class="relative h-full">
                        <textarea id="editor_md" name="conteudo_md" required
                            class="w-full h-full bg-slate-900 text-emerald-400 p-8 rounded-[2.5rem] editor-font text-sm outline-none border-8 border-slate-800 focus:border-blue-600 transition-all leading-relaxed resize-none custom-scrollbar"
                            oninput="atualizarPreview()"><?= htmlspecialchars($conteudo_editar, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="absolute bottom-6 left-6 px-3 py-1 bg-white/5 text-white/20 text-[8px] font-black rounded-full pointer-events-none tracking-[0.3em] uppercase">Markdown</div>
                    </div>
                    <div id="preview_md" class="w-full h-full bg-white p-10 rounded-[2.5rem] border-4 border-slate-50 overflow-y-auto custom-scrollbar shadow-inner prose prose-slate max-w-none"></div>
                </div>

                <input type="hidden" id="user_sessao" value="<?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?>">

                <button type="submit" name="salvar_documento"
                        class="w-full bg-blue-600 text-white py-5 rounded-3xl font-black text-xs tracking-[0.2em] uppercase shadow-xl hover:bg-blue-700 hover:scale-[1.005] transition-all flex items-center justify-center gap-3">
                    Publicar Documentação Markdown Agora 🚀
                </button>
            </form>
        </div>

    </div><!-- /grid principal -->
</div>

<!-- ====================================================================== -->
<!-- MODAL: CRIAR NOVA PASTA (com path_pai)                                  -->
<!-- ====================================================================== -->
<div id="modalPasta" class="hidden fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[1000] flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden">
        <div class="bg-emerald-700 p-6 text-white flex justify-between items-center">
            <div>
                <h3 class="text-lg font-black uppercase tracking-tight">Nova Pasta</h3>
                <p class="text-[10px] text-emerald-200 font-bold uppercase tracking-widest mt-1">Criação hierárquica</p>
            </div>
            <button onclick="toggleModalPasta()" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-2xl">&times;</button>
        </div>
        <form method="POST" class="p-8 space-y-5">
            <div class="space-y-1">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Pasta Pai (onde criar)</label>
                <select name="path_pai" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="">(raiz — /docs/)</option>
                    <?php foreach ($lista_pastas_plana as $rel):
                        echo '<option value="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '">'
                            . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '</option>';
                    endforeach; ?>
                </select>
                <p class="text-[9px] text-slate-400 ml-1 mt-1">
                    Ex.: selecione <strong>RH</strong> e nomeie <strong>ADMISSAO</strong> → cria <code>/docs/RH/ADMISSAO/</code>
                </p>
            </div>
            <div class="space-y-1">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Nome da Nova Pasta</label>
                <input type="text" name="nome_pasta" required
                    placeholder="Ex: FORMS ADMISSÃO"
                    pattern="[A-Za-zÀ-ÿ0-9_\-\s]+"
                    title="Apenas letras, números, espaços, _ e -"
                    class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-sm font-bold uppercase outline-none focus:ring-2 focus:ring-emerald-500">
                <p class="text-[9px] text-slate-400 ml-1">Apenas letras, números, espaços, _ e -. Será convertido para maiúsculas.</p>
            </div>
            <button name="nova_pasta" type="submit"
                    class="w-full bg-emerald-600 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-emerald-700 transition-all">
                Criar Pasta ✅
            </button>
        </form>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL: BIBLIOTECA DE MÍDIA                                              -->
<!-- ====================================================================== -->
<div id="modalMidia" class="hidden fixed inset-0 bg-slate-950/90 backdrop-blur-md z-[2000] flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-5xl rounded-[3.5rem] shadow-2xl overflow-hidden">
        <div class="bg-slate-900 p-8 text-white flex justify-between items-center">
            <div>
                <h3 class="text-xl font-black uppercase italic tracking-tighter leading-none">Gerenciador de Mídia</h3>
                <p class="text-[9px] text-orange-400 font-bold uppercase tracking-[0.2em] mt-2">Upload e biblioteca de imagens</p>
            </div>
            <button onclick="toggleModalMidia()" class="w-12 h-12 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-3xl">&times;</button>
        </div>

        <div class="p-10 grid grid-cols-1 md:grid-cols-12 gap-8">
            <!-- Upload de imagem -->
            <div class="md:col-span-4 space-y-6">
                <form id="form_img_ajax">
                    <label class="w-full flex flex-col items-center px-4 py-12 bg-orange-50 text-orange-600 rounded-[2.5rem] border-2 border-dashed border-orange-200 cursor-pointer hover:bg-orange-100 transition-all group">
                        <span class="text-[10px] font-black uppercase text-center group-hover:scale-110 transition-transform">
                            Arraste ou clique<br>para subir GIF/IMG
                        </span>
                        <input type="file" id="input_img_ajax" name="arquivo_img" class="hidden" accept="image/*">
                    </label>
                    <div id="upload_status" class="hidden text-center text-[10px] font-black text-emerald-500 uppercase italic animate-pulse mt-3">
                        Sincronizando arquivo...
                    </div>
                </form>
            </div>

            <!-- Lista de imagens -->
            <div class="md:col-span-8 space-y-4">
                <input type="text" id="filtro_midia" placeholder="Buscar por nome (ex: login)..."
                       class="w-full pl-6 pr-4 py-4 bg-slate-100 border-none rounded-2xl text-xs font-bold outline-none focus:ring-4 focus:ring-blue-500/10">

                <div class="max-h-[450px] overflow-y-auto custom-scrollbar border border-slate-50 rounded-[2rem] bg-white">
                    <table class="w-full text-left">
                        <tbody id="lista-midia-modal">
                            <?php foreach ($imagens_biblioteca as $img):
                                $n        = basename($img);
                                $link_del = 'admin_docs.php?excluir_img=' . base64_encode($img);
                            ?>
                            <tr class="border-b border-slate-50 hover:bg-blue-50 transition-all group"
                                data-nome="<?= strtolower(htmlspecialchars($n, ENT_QUOTES, 'UTF-8')) ?>">

                                <td class="p-4 w-16" onclick="inserirNoEditor('<?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?>')">
                                    <div class="w-10 h-10 rounded-lg bg-white border border-slate-100 flex items-center justify-center overflow-hidden shadow-sm cursor-pointer">
                                        <img src="img/<?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?>" class="max-w-full max-h-full object-contain" alt="<?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </td>

                                <td class="p-4 cursor-pointer" onclick="inserirNoEditor('<?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?>')">
                                    <p class="text-[11px] font-bold text-slate-700 truncate"><?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-[8px] text-slate-400 font-black uppercase mt-1 tracking-widest"><?= round(filesize($img) / 1024, 1) ?> KB</p>
                                </td>

                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="event.stopPropagation(); renomearMidia('<?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?>')"
                                                title="Renomear"
                                                class="p-2 rounded-lg bg-orange-50 text-orange-600 hover:bg-orange-500 hover:text-white transition-all">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M15.414 2.414a2 2 0 012.828 0L21 5.586a2 2 0 010 2.828l-7 7-4 1 1-4 7-7z"/>
                                            </svg>
                                        </button>
                                        <a href="<?= $link_del ?>"
                                           onclick="event.stopPropagation(); return confirm('Excluir permanentemente?')"
                                           title="Excluir"
                                           class="p-2 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-all">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- JAVASCRIPT                                                               -->
<!-- ====================================================================== -->
<script>
// ---------------------------------------------------------------------------
// Configuração do Marked.js
// ---------------------------------------------------------------------------
marked.setOptions({ gfm: true, breaks: true });

// ---------------------------------------------------------------------------
// Preview do editor Markdown
// ---------------------------------------------------------------------------
function atualizarPreview() {
    const editor  = document.getElementById('editor_md');
    const nomeArq = document.getElementById('nome_arquivo').value;
    const chave   = nomeArq ? 'rascunho_navi_' + nomeArq : 'rascunho_navi_novo';

    localStorage.setItem(chave, editor.value);

    // Expande sintaxe custom :::info e :::danger
    const texto = editor.value
        .replace(/:::info([\s\S]*?):::/g, '<div class="alert-info">$1</div>')
        .replace(/:::danger([\s\S]*?):::/g, '<div class="alert-danger">$1</div>');

    document.getElementById('preview_md').innerHTML = marked.parse(texto);
}

// Restaura rascunho salvo no localStorage ao carregar a página.
window.addEventListener('load', function () {
    const editor  = document.getElementById('editor_md');
    const nomeArq = document.getElementById('nome_arquivo').value;
    const chave   = nomeArq ? 'rascunho_navi_' + nomeArq : 'rascunho_navi_novo';
    const salvo   = localStorage.getItem(chave);

    if (salvo && !editor.value) {
        editor.value = salvo;
    }
    atualizarPreview();
});

// Avisa antes de sair com conteúdo não salvo.
let intencaoDeSalvar = false;
window.addEventListener('beforeunload', function (e) {
    const conteudo = document.getElementById('editor_md').value.trim();
    if (conteudo.length > 0 && !intencaoDeSalvar) {
        e.preventDefault();
        e.returnValue = '';
    }
});
document.getElementById('form_principal').addEventListener('submit', function () {
    intencaoDeSalvar = true;
});

// Limpa o rascunho após salvar com sucesso.
<?php if ($mensagem && str_contains($mensagem, '✅')): ?>
(function () {
    const nomeAtual = document.getElementById('nome_arquivo').value;
    localStorage.removeItem('rascunho_navi_' + nomeAtual);
    localStorage.removeItem('rascunho_navi_novo');
})();
<?php endif; ?>

// ---------------------------------------------------------------------------
// Inserção de formatação e templates no editor
// ---------------------------------------------------------------------------
function inserirFormato(inicio, fim) {
    const editor = document.getElementById('editor_md');
    const start  = editor.selectionStart;
    const end    = editor.selectionEnd;
    const sel    = editor.value.substring(start, end);
    editor.value = editor.value.substring(0, start) + inicio + sel + fim + editor.value.substring(end);
    editor.selectionStart = start + inicio.length;
    editor.selectionEnd   = start + inicio.length + sel.length;
    atualizarPreview();
    editor.focus();
}

function inserirTemplate(tipo) {
    const editor  = document.getElementById('editor_md');
    const user    = document.getElementById('user_sessao').value;
    const dataHoje = new Intl.DateTimeFormat('pt-BR').format(new Date());
    const templates = {
        info:      `:::info Informações\n**Setor:** \n**Responsável:** ${user}\n**Data:** ${dataHoje}\n:::\n\n`,
        danger:    `:::danger ATENÇÃO\nDescreva o aviso aqui.\n:::\n\n`,
        separador: `\n---\n\n`,
    };
    const t   = templates[tipo] || '';
    const pos = editor.selectionStart;
    editor.value = editor.value.substring(0, pos) + t + editor.value.substring(pos);
    atualizarPreview();
    editor.focus();
}

// ---------------------------------------------------------------------------
// Modais
// ---------------------------------------------------------------------------
function toggleModalMidia() {
    document.getElementById('modalMidia').classList.toggle('hidden');
}

function toggleModalPasta() {
    document.getElementById('modalPasta').classList.toggle('hidden');
}

// Colapsa/expande a lista de arquivos de uma pasta na sidebar.
function togglePastaVisual(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
}

// ---------------------------------------------------------------------------
// Inserção de imagem no editor
// ---------------------------------------------------------------------------
function inserirNoEditor(nome) {
    const editor = document.getElementById('editor_md');
    const pos    = editor.selectionStart;
    const codigo = `\n![imagem](img/${nome})\n`;
    editor.value = editor.value.substring(0, pos) + codigo + editor.value.substring(pos);
    toggleModalMidia();
    atualizarPreview();
    editor.focus();
}

// ---------------------------------------------------------------------------
// Renomear imagem de mídia
// ---------------------------------------------------------------------------
function renomearMidia(antigo) {
    const novo = prompt('Novo nome para o arquivo (mantenha a extensão):', antigo);
    if (novo && novo.trim() !== '' && novo !== antigo) {
        window.location.href = `admin_docs.php?acao=renomear_midia&antigo=${encodeURIComponent(antigo)}&novo=${encodeURIComponent(novo)}`;
    }
}

// ---------------------------------------------------------------------------
// Renomear pasta de documentos
// ---------------------------------------------------------------------------
/**
 * Recebe o caminho relativo da pasta (ex.: "RH/ADMISSAO") e seu nome atual.
 * Extrai o path_pai (ex.: "RH") e solicita o novo nome ao usuário,
 * então redireciona para a ação de renomeação no PHP.
 *
 * @param {string} caminhoRelativo  Caminho relativo a RAIZ_DOCS (ex.: "RH/ADMISSAO").
 * @param {string} nomeAtual        Nome atual da pasta (ex.: "ADMISSAO").
 */
function renomearPasta(caminhoRelativo, nomeAtual) {
    const novoNome = prompt(`Novo nome para a pasta "${nomeAtual}":`, nomeAtual);
    if (!novoNome || novoNome.trim() === '' || novoNome === nomeAtual) return;

    // Valida que o novo nome contém apenas caracteres permitidos.
    if (!/^[A-Za-z0-9_\-]+$/.test(novoNome)) {
        alert('Nome inválido. Use apenas letras, números, _ e -.');
        return;
    }

    // O path_pai é tudo antes do último separador no caminhoRelativo.
    // Ex.: "RH/ADMISSAO" → path_pai = "RH" | "RH" → path_pai = ""
    const partes  = caminhoRelativo.split('/').filter(Boolean);
    partes.pop(); // Remove o nome atual.
    const pathPai = partes.join('/');

    window.location.href = [
        'admin_docs.php?acao=renomear_pasta',
        `&path_pai=${encodeURIComponent(pathPai)}`,
        `&antigo=${encodeURIComponent(nomeAtual)}`,
        `&novo=${encodeURIComponent(novoNome)}`,
    ].join('');
}

// ---------------------------------------------------------------------------
// Upload AJAX de imagem
// ---------------------------------------------------------------------------
document.getElementById('input_img_ajax').addEventListener('change', function () {
    const fd = new FormData();
    fd.append('arquivo_img', this.files[0]);
    fd.append('ajax_upload', 'true');

    document.getElementById('upload_status').classList.remove('hidden');

    fetch('admin_docs.php', { method: 'POST', body: fd })
        .then(() => location.reload())
        .catch(() => alert('Erro no upload. Tente novamente.'));
});

// ---------------------------------------------------------------------------
// Filtro de busca na biblioteca de mídia
// ---------------------------------------------------------------------------
document.getElementById('filtro_midia').addEventListener('input', function () {
    const termo = this.value.toLowerCase();
    document.querySelectorAll('#lista-midia-modal tr').forEach(function (tr) {
        tr.style.display = (tr.dataset.nome || '').includes(termo) ? 'table-row' : 'none';
    });
});
</script>
</body>
</html>