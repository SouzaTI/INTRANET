<?php
declare(strict_types=1);

final class ContratoAuth
{
    private PDO $pdo;
    private int $usuarioId;
    private bool $admin;
    private array $permissoes = [];

    public function __construct(PDO $pdo, int $usuarioId, bool $admin)
    {
        $this->pdo = $pdo;
        $this->usuarioId = $usuarioId;
        $this->admin = $admin;
        $this->carregarPermissoes();
    }

    private function carregarPermissoes(): void
    {
        if ($this->admin) {
            return;
        }

        $sql = "
            SELECT p.codigo,
                   MAX(
                       CASE
                           WHEN up.efeito = 'NEGAR' THEN 0
                           WHEN up.efeito = 'PERMITIR' THEN 1
                           ELSE COALESCE(gp.permitido, 0)
                       END
                   ) AS permitido
              FROM contratos_permissoes p
         LEFT JOIN contratos_usuarios_permissoes up
                ON up.permissao_id = p.id
               AND up.usuario_id = :usuario_id_up
         LEFT JOIN (
                    SELECT cgp.permissao_id, MAX(cgp.permitido) AS permitido
                      FROM contratos_grupos_permissoes cgp
                      JOIN usuarios_grupos ug ON ug.grupo_id = cgp.grupo_id
                     WHERE ug.usuario_id = :usuario_id_gp
                  GROUP BY cgp.permissao_id
                   ) gp ON gp.permissao_id = p.id
          GROUP BY p.id, p.codigo, up.efeito
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':usuario_id_up' => $this->usuarioId,
            ':usuario_id_gp' => $this->usuarioId,
        ]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $this->permissoes[$linha['codigo']] = (bool) $linha['permitido'];
        }
    }

    public function usuarioId(): int
    {
        return $this->usuarioId;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function pode(string $codigo): bool
    {
        return $this->admin || ($this->permissoes[$codigo] ?? false);
    }

    public function exigir(string $codigo): void
    {
        if (!$this->pode($codigo)) {
            throw new RuntimeException('Sem permissão para esta ação.', 403);
        }
    }

    public function podeAcessarContrato(int $contratoId): bool
    {
        if ($this->admin) {
            return true;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
              FROM contratos c
             WHERE c.id = :contrato_id
               AND (
                    c.gestor_id = :usuario_gestor
                    OR EXISTS (
                        SELECT 1
                          FROM contratos_acessos_usuarios cau
                         WHERE cau.contrato_id = c.id
                           AND cau.usuario_id = :usuario_direto
                    )
                    OR EXISTS (
                        SELECT 1
                          FROM contratos_acessos_grupos cag
                          JOIN usuarios_grupos ug ON ug.grupo_id = cag.grupo_id
                         WHERE cag.contrato_id = c.id
                           AND ug.usuario_id = :usuario_grupo
                    )
               )
             LIMIT 1
        ");
        $stmt->execute([
            ':contrato_id' => $contratoId,
            ':usuario_gestor' => $this->usuarioId,
            ':usuario_direto' => $this->usuarioId,
            ':usuario_grupo' => $this->usuarioId,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function exigirAcessoContrato(int $contratoId): void
    {
        $this->exigir('visualizar');
        if (!$this->podeAcessarContrato($contratoId)) {
            throw new RuntimeException('Contrato não encontrado ou não compartilhado com você.', 403);
        }
    }

    public function filtroContratosSql(): array
    {
        if ($this->admin) {
            return ['1=1', []];
        }

        return [
            "(c.gestor_id = :filtro_gestor
              OR EXISTS (
                   SELECT 1 FROM contratos_acessos_usuarios cau
                    WHERE cau.contrato_id = c.id AND cau.usuario_id = :filtro_direto
              )
              OR EXISTS (
                   SELECT 1
                     FROM contratos_acessos_grupos cag
                     JOIN usuarios_grupos ug ON ug.grupo_id = cag.grupo_id
                    WHERE cag.contrato_id = c.id AND ug.usuario_id = :filtro_grupo
              ))",
            [
                ':filtro_gestor' => $this->usuarioId,
                ':filtro_direto' => $this->usuarioId,
                ':filtro_grupo' => $this->usuarioId,
            ],
        ];
    }
}

function contratoCsrfToken(): string
{
    if (empty($_SESSION['contratos_csrf'])) {
        $_SESSION['contratos_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['contratos_csrf'];
}

function contratoValidarCsrf(): void
{
    $recebido = (string) ($_POST['csrf_token'] ?? '');
    if ($recebido === '' || !hash_equals(contratoCsrfToken(), $recebido)) {
        throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.', 419);
    }
}

