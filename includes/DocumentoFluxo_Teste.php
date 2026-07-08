<?php
class DocumentoFluxo {
    const MAX_CICLOS = 3; 
    private $pdo;

    public function __construct($pdo_conexao) {
        $this->pdo = $pdo_conexao;
    }

    /**
     * Controla de forma segura a transição de estados e grava o histórico imutável
     */
    public function transitar(int $doc_id, string $acao, int $usuario_id, string $mensagem = '', array $dados_extras = [], string $arquivo_novo = null): bool {
        try {
            $this->pdo->beginTransaction();

            // 1. Busca o estado atual do documento (FOR UPDATE trava a linha)
            $stmt = $this->pdo->prepare("SELECT status, versao_atual, ciclos_revisao, usuario_id, nome_arquivo FROM docs_fluxo_simples WHERE id = ? FOR UPDATE");
            $stmt->execute([$doc_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                throw new Exception("Documento não encontrado.");
            }

            $estado_atual = $doc['status'];
            $novo_status = $estado_atual;
            $nova_versao = $doc['versao_atual'];
            $novos_ciclos = $doc['ciclos_revisao'];
            $hash = null; // Inicializa variável para o hash

            // 2. Máquina de Estados
            if ($acao === 'assumir' && $estado_atual === 'Pendente T.I') {
                $novo_status = 'Em Análise';
            } 
            elseif ($acao === 'aprovar' && $estado_atual === 'Em Análise') {
                // LÓGICA DO LACRE (ISO 9000 - Integridade)
                $caminho_fisico = __DIR__ . '/../uploads_fluxo/' . $doc['nome_arquivo'];
                $hash = file_exists($caminho_fisico) ? hash_file('sha256', $caminho_fisico) : null;
                
                $novo_status = 'Aprovado';
                
                // Atualiza com os campos de auditoria
                $stmt_up = $this->pdo->prepare("
                    UPDATE docs_fluxo_simples 
                    SET status = ?, hash_arquivo = ?, publicado_em = NOW(), aprovado_por = ? 
                    WHERE id = ?
                ");
                $stmt_up->execute([$novo_status, $hash, $usuario_id, $doc_id]);
            } 
            elseif ($acao === 'recusar' && $estado_atual === 'Em Análise') {
                $novo_status = 'Recusado';
            } 
            elseif ($acao === 'devolver' && $estado_atual === 'Em Análise') {
                if ($doc['ciclos_revisao'] >= self::MAX_CICLOS) {
                    $novo_status = 'Recusado';
                    $mensagem .= " (Recusado automaticamente: Limite de " . self::MAX_CICLOS . " revisões excedido).";
                } else {
                    $novo_status = 'Aguardando Ajustes';
                    $novos_ciclos++;
                }
            } 
            elseif ($acao === 'reenviar' && $estado_atual === 'Aguardando Ajustes') {
                $novo_status = 'Pendente T.I';
                $nova_versao++;
            } else {
                throw new Exception("Transição de estado inválida de '$estado_atual' com a ação '$acao'.");
            }

            // Se a ação não foi 'aprovar' (que já deu update acima), fazemos o update padrão
            if ($acao !== 'aprovar') {
                $sql_update = "UPDATE docs_fluxo_simples SET status = ?, versao_atual = ?, ciclos_revisao = ? WHERE id = ?";
                $this->pdo->prepare($sql_update)->execute([$novo_status, $nova_versao, $novos_ciclos, $doc_id]);
            }

            // Atualiza arquivo se necessário
            if ($arquivo_novo) {
                $this->pdo->prepare("UPDATE docs_fluxo_simples SET nome_arquivo = ? WHERE id = ?")->execute([$arquivo_novo, $doc_id]);
            }

            // 4. Grava o log imutável
            $tipo_acao_historico = strtoupper($acao);
            $json_extras = !empty($dados_extras) ? json_encode($dados_extras) : null;

            $sql_hist = "INSERT INTO docs_fluxo_historico (doc_id, usuario_id, tipo_acao, mensagem, arquivo_novo, dados_extras) VALUES (?, ?, ?, ?, ?, ?)";
            $this->pdo->prepare($sql_hist)->execute([$doc_id, $usuario_id, $tipo_acao_historico, $mensagem, $arquivo_novo, $json_extras]);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Erro no Workflow do Documento: " . $e->getMessage());
            return false;
        }
    }
}