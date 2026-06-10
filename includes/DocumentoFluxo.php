<?php
class DocumentoFluxo {
    const MAX_CICLOS = 3; // Limite do Claude para evitar loop infinito de vai-e-vem
    private $pdo;

    public function __construct($pdo_conexao) {
        $this->pdo = $pdo_conexao;
    }

    /**
     * Controla de forma segura a transição de estados e grava o histórico imutável
     */
    public function transitar(int $doc_id, string $acao, int $usuario_id, string $mensagem = '', array $dados_extras = [], string $arquivo_novo = null): bool {
        try {
            // Inicia uma Transação no banco para garantir consistência total
            $this->pdo->beginTransaction();

            // 1. Busca o estado atual do documento travando a linha para evitar concorrência (FOR UPDATE)
            $stmt = $this->pdo->prepare("SELECT status, versao_atual, ciclos_revisao, usuario_id FROM docs_fluxo_simples WHERE id = ? FOR UPDATE");
            $stmt->execute([$doc_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                throw new Exception("Documento não encontrado.");
            }

            $estado_atual = $doc['status'];
            $novo_status = $estado_atual;
            $nova_versao = $doc['versao_atual'];
            $novos_ciclos = $doc['ciclos_revisao'];

            // 2. Máquina de Estados: Define o destino com base na ação executada
            if ($acao === 'assumir' && $estado_atual === 'Pendente T.I') {
                $novo_status = 'Em Análise';
            } elseif ($acao === 'aprovar' && $estado_atual === 'Em Análise') {
                $novo_status = 'Aprovado';
            } elseif ($acao === 'recusar' && $estado_atual === 'Em Análise') {
                $novo_status = 'Recusado';
            } elseif ($acao === 'devolver' && $estado_atual === 'Em Análise') {
                // Valida o limite de ciclos do Claude
                if ($doc['ciclos_revisao'] >= self::MAX_CICLOS) {
                    $novo_status = 'Recusado';
                    $mensagem .= " (Recusado automaticamente: Limite de " . self::MAX_CICLOS . " revisões excedido).";
                } else {
                    $novo_status = 'Aguardando Ajustes';
                    $novos_ciclos++; // Incrementa o ciclo de idas e vindas
                }
            } elseif ($acao === 'reenviar' && $estado_atual === 'Aguardando Ajustes') {
                $novo_status = 'Pendente T.I';
                $nova_versao++; // Incrementa a versão do arquivo (V2, V3...)
            } else {
                throw new Exception("Transição de estado inválida de '$estado_atual' com a ação '$acao'.");
            }

            // 3. Atualiza a tabela principal do documento
            $sql_update = "UPDATE docs_fluxo_simples SET status = ?, versao_atual = ?, ciclos_revisao = ? WHERE id = ?";
            $this->pdo->prepare($sql_update)->execute([$novo_status, $nova_versao, $novos_ciclos, $doc_id]);

            // Se um novo arquivo foi enviado na revisão, atualiza o ponteiro do arquivo atual
            if ($arquivo_novo) {
                $this->pdo->prepare("UPDATE docs_fluxo_simples SET nome_arquivo = ? WHERE id = ?")->execute([$arquivo_novo, $doc_id]);
            }

            // 4. Grava o log imutável na tabela de histórico filha (Audit Trail)
            $tipo_acao_historico = strtoupper($acao);
            $json_extras = !empty($dados_extras) ? json_encode($dados_extras) : null;

            $sql_hist = "INSERT INTO docs_fluxo_historico (doc_id, usuario_id, tipo_acao, mensagem, arquivo_novo, dados_extras) VALUES (?, ?, ?, ?, ?, ?)";
            $this->pdo->prepare($sql_hist)->execute([$doc_id, $usuario_id, $tipo_acao_historico, $mensagem, $arquivo_novo, $json_extras]);

            // Confirma todas as alterações no banco de dados de uma vez só!
            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            // Desfaz qualquer alteração se houver um erro no meio do caminho
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Erro no Workflow do Documento: " . $e->getMessage());
            return false;
        }
    }
}