<?php
// api/processar_assinatura.php

require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'msg' => 'Acesso negado.']));
}

header('Content-Type: application/json');

class ProcessadorAssinatura
{
    private PDO $pdo;
    private const MAX_TENTATIVAS = 3;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // -----------------------------------------------------------------
    // Ponto de entrada
    // -----------------------------------------------------------------
    public function assinar(array $post): array
    {
        $envelope_id  = (int)   ($post['envelope_id'] ?? 0);
        $pin_digitado =          trim($post['pin_digitado'] ?? '');
        $user_id      = (int)   ($_SESSION['user_id'] ?? 0);

        if ($envelope_id <= 0)                        return $this->erro('Envelope inválido.');
        if (!preg_match('/^\d{4}$/', $pin_digitado))  return $this->erro('PIN deve ter exatamente 4 dígitos.');

        $this->pdo->beginTransaction();
        try {
            // 1. Carrega o fluxo do usuário com lock para evitar race condition
            $fluxo = $this->buscarFluxo($envelope_id, $user_id);
            if (!$fluxo) {
                $this->pdo->rollBack();
                return $this->erro('Você não é um assinante deste envelope ou já assinou.');
            }

            // 2. Verifica bloqueio por tentativas
            if ($this->estaBloqueado($fluxo)) {
                $this->pdo->rollBack();
                return $this->erro('PIN bloqueado temporariamente. Tente novamente em 15 minutos.');
            }

            // 3. Valida posição no fluxo sequencial
            $envelope = $this->buscarEnvelope($envelope_id);
            if ($envelope['tipo_fluxo'] === 'sequencial' && !$this->ehProximo($envelope_id, $user_id)) {
                $this->pdo->rollBack();
                return $this->erro('Aguarde: não é sua vez de assinar neste fluxo sequencial.');
            }

            // 4. Verifica PIN
            $hash_bcrypt = $this->buscarPinBcrypt($user_id);
            if (!$hash_bcrypt) {
                $this->pdo->rollBack();
                return $this->erro('PIN de assinatura não cadastrado. Acesse "Cadastrar PIN" antes de assinar.');
            }

            if (!password_verify($pin_digitado, $hash_bcrypt)) {
                $this->registrarTentativaFalha($fluxo['id']);
                $this->pdo->commit();
                $restantes = self::MAX_TENTATIVAS - ((int)$fluxo['tentativas_pin'] + 1);
                return $this->erro($restantes > 0
                    ? "PIN incorreto. {$restantes} tentativa(s) restante(s)."
                    : 'PIN bloqueado por excesso de tentativas.'
                );
            }

            // 5. Gera o Lacre Digital e assina
            $ip        = $this->capturarIP();
            $timestamp = date('Y-m-d H:i:s');
            $lacre     = hash('sha256', $envelope['arquivo_hash'] . $user_id . $ip . $timestamp);

            $this->registrarAssinatura($fluxo['id'], $lacre, $ip, $timestamp);

            // 6. Verifica se envelope está 100% concluído
            if ($this->todosAssinaram($envelope_id)) {
                $this->concluirEnvelope($envelope_id);
            }

            $this->pdo->commit();

            registrarLog(
                $this->pdo,
                'ASSINATURA DIGITAL',
                "Usuário assinou o envelope #{$envelope_id}. Lacre: {$lacre}"
            );

            return ['ok' => true, 'msg' => 'Documento assinado com sucesso.', 'lacre' => $lacre];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return $this->erro('Falha interna. Tente novamente.');
        }
    }

    // -----------------------------------------------------------------
    // Busca o registro do fluxo do usuário com SELECT ... FOR UPDATE
    // Garante que nenhuma outra requisição paralela processe o mesmo registro
    // -----------------------------------------------------------------
    private function buscarFluxo(int $envelope_id, int $user_id): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT id, tentativas_pin, bloqueado_ate
            FROM   assinaturas_fluxo
            WHERE  fk_assinatura = :eid
            AND  glpi_user_id  = :uid
            AND  status        = 'pendente'
            FOR UPDATE
        ");
        $stmt->execute([':eid' => $envelope_id, ':uid' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function buscarPinBcrypt(int $user_id): string|false
    {
        $stmt = $this->pdo->prepare("
            SELECT assinatura_pin
            FROM   usuarios_permissoes
            WHERE  usuario_id     = :uid
            AND  assinatura_pin IS NOT NULL
        ");
        $stmt->execute([':uid' => $user_id]);
        return $stmt->fetchColumn();
    }

    // -----------------------------------------------------------------
    // Busca envelope — também com lock para o UPDATE de conclusão
    // -----------------------------------------------------------------
    private function buscarEnvelope(int $envelope_id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT tipo_fluxo, arquivo_hash, status
            FROM   sistemas_assinaturas
            WHERE  id = :eid
            FOR UPDATE
        ");
        $stmt->execute([':eid' => $envelope_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new \RuntimeException('Envelope não encontrado.');
        if ($row['status'] === 'concluido') throw new \RuntimeException('Envelope já concluído.');
        return $row;
    }

    // -----------------------------------------------------------------
    // Consulta a VIEW: usuário é o próximo no fluxo sequencial?
    // -----------------------------------------------------------------
    private function ehProximo(int $envelope_id, int $user_id): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM v_proximo_assinante
            WHERE  fk_assinatura = :eid
              AND  glpi_user_id  = :uid
        ");
        $stmt->execute([':eid' => $envelope_id, ':uid' => $user_id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // -----------------------------------------------------------------
    private function estaBloqueado(array $fluxo): bool
    {
        return !empty($fluxo['bloqueado_ate'])
            && new \DateTime() < new \DateTime($fluxo['bloqueado_ate']);
    }

    // -----------------------------------------------------------------
    // Incrementa tentativa; bloqueia por 15 min ao atingir o limite
    // -----------------------------------------------------------------
    private function registrarTentativaFalha(int $fluxo_id): void
    {
        $this->pdo->prepare("
            UPDATE assinaturas_fluxo
            SET    tentativas_pin = tentativas_pin + 1,
                   bloqueado_ate  = CASE
                       WHEN tentativas_pin + 1 >= :max
                       THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                       ELSE bloqueado_ate
                   END
            WHERE  id = :id
        ")->execute([':max' => self::MAX_TENTATIVAS, ':id' => $fluxo_id]);
    }

    // -----------------------------------------------------------------
    // Grava assinatura e zera tentativas (PIN correto)
    // -----------------------------------------------------------------
    private function registrarAssinatura(int $fluxo_id, string $lacre, string $ip, string $timestamp): void
    {
        $this->pdo->prepare("
            UPDATE assinaturas_fluxo
            SET    status         = 'assinado',
                   lacre_hash     = :lacre,
                   ip_assinatura  = :ip,
                   assinado_em    = :ts,
                   tentativas_pin = 0,
                   bloqueado_ate  = NULL
            WHERE  id = :id
        ")->execute([':lacre' => $lacre, ':ip' => $ip, ':ts' => $timestamp, ':id' => $fluxo_id]);
    }

    // -----------------------------------------------------------------
    // Retorna true se não há mais nenhum 'pendente' no envelope
    // -----------------------------------------------------------------
    private function todosAssinaram(int $envelope_id): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM assinaturas_fluxo
            WHERE  fk_assinatura = :eid AND status = 'pendente'
        ");
        $stmt->execute([':eid' => $envelope_id]);
        return (int) $stmt->fetchColumn() === 0;
    }

    // -----------------------------------------------------------------
    private function concluirEnvelope(int $envelope_id): void
    {
        $this->pdo->prepare("
            UPDATE sistemas_assinaturas SET status = 'concluido'
            WHERE  id = :eid
        ")->execute([':eid' => $envelope_id]);
    }

    // -----------------------------------------------------------------
    // Captura IP real respeitando proxies confiáveis
    // ATENÇÃO: confie em X-Forwarded-For apenas se sua infra garantir
    // que esse header não pode ser forjado pelo cliente
    // -----------------------------------------------------------------
    private function capturarIP(): string
    {
        $candidatos = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',   // proxy/load-balancer
            $_SERVER['HTTP_X_REAL_IP']        ?? '',
            $_SERVER['REMOTE_ADDR']           ?? '',
        ];
        foreach ($candidatos as $ip_raw) {
            // X-Forwarded-For pode vir como "IP_cliente, IP_proxy" — pega o primeiro
            $ip = trim(explode(',', $ip_raw)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
        // Fallback: aceita IP privado (ambiente interno de intranet)
        return trim(explode(',', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')[0]);
    }

    private function erro(string $msg): array
    {
        return ['ok' => false, 'msg' => $msg];
    }
}

// -----------------------------------------------------------------
$processador = new ProcessadorAssinatura($pdo_intra);
echo json_encode($processador->assinar($_POST));