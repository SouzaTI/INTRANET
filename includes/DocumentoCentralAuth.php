<?php
declare(strict_types=1);

final class DocumentoCentralAuth
{
    private PDO $pdo;
    private int $usuarioId;
    private bool $admin;
    private bool $validador;
    private array $setores = [];

    public function __construct(PDO $pdo, int $usuarioId, bool $admin, array $sessao = [])
    {
        $this->pdo = $pdo;
        $this->usuarioId = $usuarioId;
        $this->admin = $admin;
        $this->validador = $admin || !empty($sessao['pode_gerenciar_docs']);

        if ($usuarioId > 0) {
            $stmtPermissoes = $pdo->prepare(
                "SELECT
                    MAX(CASE WHEN origem='USUARIO' THEN is_admin ELSE 0 END) AS usuario_admin,
                    MAX(CASE WHEN origem='USUARIO' THEN pode_docs ELSE 0 END) AS usuario_docs,
                    MAX(CASE WHEN origem='GRUPO' THEN is_admin ELSE 0 END) AS grupo_admin,
                    MAX(CASE WHEN origem='GRUPO' THEN pode_docs ELSE 0 END) AS grupo_docs
                 FROM (
                    SELECT 'USUARIO' AS origem, COALESCE(is_admin,0) AS is_admin,
                           COALESCE(pode_gerenciar_docs,0) AS pode_docs
                      FROM usuarios_permissoes WHERE usuario_id=?
                    UNION ALL
                    SELECT 'GRUPO', COALESCE(g.is_admin,0), COALESCE(g.pode_gerenciar_docs,0)
                      FROM usuarios_grupos ug
                      JOIN grupos_intranet g ON g.id=ug.grupo_id
                     WHERE ug.usuario_id=?
                 ) permissoes"
            );
            $stmtPermissoes->execute([$usuarioId, $usuarioId]);
            $permissoes = $stmtPermissoes->fetch(PDO::FETCH_ASSOC) ?: [];
            $this->admin = $this->admin || !empty($permissoes['usuario_admin']) || !empty($permissoes['grupo_admin']);
            $this->validador = $this->validador || $this->admin
                || !empty($permissoes['usuario_docs']) || !empty($permissoes['grupo_docs']);
        }

        foreach (array_merge(
            [(string) ($sessao['setor_principal'] ?? '')],
            (array) ($sessao['pastas_extras'] ?? [])
        ) as $setor) {
            $normalizado = $this->normalizarSetor((string) $setor);
            if ($normalizado !== '') {
                $this->setores[] = $normalizado;
            }
        }
        $this->setores = array_values(array_unique($this->setores));

        if ($usuarioId > 0) {
            $stmtSetores = $pdo->prepare(
                "SELECT DISTINCT TRIM(g.setor_vinculado)
                   FROM usuarios_grupos ug
                   JOIN grupos_intranet g ON g.id=ug.grupo_id
                  WHERE ug.usuario_id=?
                    AND g.setor_vinculado IS NOT NULL
                    AND TRIM(g.setor_vinculado)<>''"
            );
            $stmtSetores->execute([$usuarioId]);
            foreach ($stmtSetores->fetchAll(PDO::FETCH_COLUMN) as $setor) {
                $normalizado = $this->normalizarSetor((string) $setor);
                if ($normalizado !== '') $this->setores[] = $normalizado;
            }

            if (!$this->setores && defined('DB_GLPI')) {
                $stmtLocal = $pdo->prepare(
                    'SELECT TRIM(l.name) FROM ' . DB_GLPI . '.glpi_users u
                     LEFT JOIN ' . DB_GLPI . '.glpi_locations l ON l.id=u.locations_id
                     WHERE u.id=? LIMIT 1'
                );
                $stmtLocal->execute([$usuarioId]);
                $normalizado = $this->normalizarSetor((string) $stmtLocal->fetchColumn());
                if ($normalizado !== '') $this->setores[] = $normalizado;
            }
            $this->setores = array_values(array_unique($this->setores));
        }

        if (!$this->validador && $usuarioId > 0) {
            $stmt = $pdo->prepare(
                "SELECT 1
                   FROM usuarios_grupos ug
                   JOIN grupos_intranet g ON g.id = ug.grupo_id
                  WHERE ug.usuario_id = ?
                    AND UPPER(TRIM(g.nome)) IN ('FACILITIES & T.I', 'ANALISE DE DADOS')
                  LIMIT 1"
            );
            $stmt->execute([$usuarioId]);
            $this->validador = (bool) $stmt->fetchColumn();
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

    public function isValidador(): bool
    {
        return $this->validador;
    }

    public function setores(): array
    {
        return $this->setores;
    }

    public function setorPrincipal(): string
    {
        return $this->setores[0] ?? '';
    }

    public function podeVer(array $documento): bool
    {
        if ($this->admin || $this->validador) {
            return true;
        }
        if ((int) ($documento['criador_id'] ?? 0) === $this->usuarioId) {
            return true;
        }
        if (($documento['status'] ?? '') !== 'PUBLICADO') {
            return false;
        }

        $setor = $this->normalizarSetor((string) ($documento['setor'] ?? ''));
        return $setor === 'GERAL' || in_array($setor, $this->setores, true);
    }

    public function exigirVer(array $documento): void
    {
        if (!$this->podeVer($documento)) {
            throw new RuntimeException('Você não possui acesso a este documento.', 403);
        }
    }

    public function exigirCriador(array $documento): void
    {
        if (!$this->admin && (int) ($documento['criador_id'] ?? 0) !== $this->usuarioId) {
            throw new RuntimeException('Esta pendência pertence a outro usuário.', 403);
        }
    }

    public function exigirValidador(): void
    {
        if (!$this->validador) {
            throw new RuntimeException('Ação permitida somente para validadores do T.I.', 403);
        }
    }

    private function normalizarSetor(string $setor): string
    {
        $setor = preg_replace('/\s+/u', ' ', trim($setor)) ?? '';
        return mb_strtoupper($setor, 'UTF-8');
    }
}

function documentoCentralCsrfToken(): string
{
    if (empty($_SESSION['documentos_csrf'])) {
        $_SESSION['documentos_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['documentos_csrf'];
}

function documentoCentralValidarCsrf(): void
{
    $esperado = (string) ($_SESSION['documentos_csrf'] ?? '');
    $recebido = (string) ($_POST['csrf_token'] ?? '');
    if ($esperado === '' || $recebido === '' || !hash_equals($esperado, $recebido)) {
        throw new RuntimeException('Sua sessão expirou. Atualize a página e tente novamente.', 419);
    }
}
