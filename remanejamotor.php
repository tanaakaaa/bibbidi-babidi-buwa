<?php
declare(strict_types=1);

namespace Remanejamento;

use PDO;

final class RemanejaMotor
{
    private PDO $pdo;

    private const DIAS_CRITICO  = 30;
    private const DIAS_URGENTE  = 60;
    private const DIAS_ATENCAO  = 90;

    private const PRIORIDADE_CRITICO = 1;
    private const PRIORIDADE_URGENTE = 2;
    private const PRIORIDADE_ATENCAO = 3;
    private const PRIORIDADE_NORMAL  = 5;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getLotesVencendo(): array
    {
        $sql = <<<SQL
            SELECT
                e.id                        AS estoque_id,
                u.id                        AS unidade_id,
                u.nome                      AS unidade_nome,
                u.codigo                    AS unidade_codigo,
                i.id                        AS insumo_id,
                i.nome                      AS insumo_nome,
                i.codigo                    AS codigo_insumo,
                i.categoria,
                i.unidade_medida,
                e.lote,
                e.quantidade,
                e.data_validade,
                DATEDIFF(e.data_validade, CURDATE()) AS dias_para_vencer,
                i.estoque_minimo,
                GREATEST(0, e.quantidade - i.estoque_minimo) AS excedente
            FROM estoque e
            JOIN unidades u ON u.id = e.unidade_id
            JOIN insumos  i ON i.id = e.insumo_id
            WHERE
                e.data_validade IS NOT NULL
                AND e.quantidade > 0
                AND DATEDIFF(e.data_validade, CURDATE()) BETWEEN 1 AND i.dias_vencimento_alerta
                AND u.ativo = 1
                AND i.ativo = 1
            HAVING excedente > 0
            ORDER BY dias_para_vencer ASC, excedente DESC
        SQL;

        return $this->pdo->query($sql)->fetchAll();
    }

    public function getUnidadesComDeficit(): array
    {
        $sql = <<<SQL
            SELECT
                u.id                        AS unidade_id,
                u.nome                      AS unidade_nome,
                u.codigo                    AS unidade_codigo,
                i.id                        AS insumo_id,
                i.nome                      AS insumo_nome,
                i.unidade_medida,
                COALESCE(SUM(e.quantidade), 0) AS estoque_atual,
                i.estoque_minimo,
                i.estoque_critico,
                i(0, i.estoque_minimo - COALESCE(SUM(e.quantidade), 0)) AS deficit,
                CASE
                    WHEN COALESCE(SUM(e.quantidade), 0) <= i.estoque_critico THEN 'CRITICO'
                    WHEN COALESCE(SUM(e.quantidade), 0) <  i.estoque_minimo  THEN 'ABAIXO_MINIMO'
                    ELSE 'NORMAL'
                END AS nivel_urgencia
            FROM unidades u
            CROSS JOIN insumos i
            LEFT JOIN estoque e ON e.unidade_id = u.id AND e.insumo_id = i.id
            WHERE u.ativo = 1 AND i.ativo = 1
            GROUP BY u.id, u.nome, u.codigo, i.id, i.nome, i.unidade_medida,
                     i.estoque_minimo, i.estoque_critico
            HAVING
                nivel_urgencia IN ('CRITICO', 'ABAIXO_MINIMO')
            ORDER BY
                FIELD(nivel_urgencia, 'CRITICO', 'ABAIXO_MINIMO'),
                deficit DESC
        SQL;

        return $this->pdo->query($sql)->fetchAll();
    }

    public function getEstoqueConsolidado(): array
    {
        $sql = <<<SQL
            SELECT
                u.id        AS unidade_id,
                u.nome      AS unidade_nome,
                u.codigo    AS unidade_codigo,
                i.id        AS insumo_id,
                i.nome      AS insumo_nome,
                i.unidade_medida,
                i.estoque_minimo,
                i.estoque_critico,
                SUM(e.quantidade) AS total_estoque,
                MIN(e.data_validade) AS validade_mais_proxima,
                COUNT(DISTINCT e.lote) AS num_lotes
            FROM estoque e
            JOIN unidades u ON u.id = e.unidade_id
            JOIN insumos  i ON i.id = e.insumo_id
            WHERE u.ativo = 1 AND i.ativo = 1
            GROUP BY u.id, u.nome, u.codigo, i.id, i.nome, i.unidade_medida,
                     i.estoque_minimo, i.estoque_critico
            ORDER BY u.nome, i.nome
        SQL;

        return $this->pdo->query($sql)->fetchAll();
    }

    public function executar(): array
    {
        $origens  = $this->getLotesVencendo();
        $destinos = $this->getUnidadesComDeficit();

        $destinosPorInsumo = [];
        foreach ($destinos as $destino) {
            $destinosPorInsumo[$destino['insumo_id']][] = $destino;
        }

        $sugestoes      = [];
        $totalGeradas   = 0;
        $totalNovas     = 0;
        $totalDuplicadas= 0;

        foreach ($origens as $origem) {
            $insumoId   = (int) $origem['insumo_id'];
            $excedente  = (int) $origem['excedente'];

            if (!isset($destinosPorInsumo[$insumoId])) {
                continue;
            }

            foreach ($destinosPorInsumo[$insumoId] as &$destino) {
                if ($destino['unidade_id'] === $origem['unidade_id']) {
                    continue;
                }

                $deficit = (int) $destino['deficit'];
                if ($deficit <= 0 || $excedente <= 0) {
                    continue;
                }

                $qtdSugerida  = min($excedente, $deficit);
                $diasVencer   = (int) $origem['dias_para_vencer'];
                $nivelDestino = $destino['nivel_urgencia'];

                $prioridade = $this->calcularPrioridade($diasVencer, $nivelDestino);
                $motivo     = $this->determinarMotivo($diasVencer, $nivelDestino);

                if ($this->sugestaoJaExiste((int) $origem['estoque_id'], (int) $destino['unidade_id'])) {
                    $totalDuplicadas++;
                    continue;
                }

                $sugestaoId = $this->persistirSugestao([
                    'insumo_id'           => $insumoId,
                    'estoque_origem_id'   => (int) $origem['estoque_id'],
                    'unidade_origem_id'   => (int) $origem['unidade_id'],
                    'unidade_destino_id'  => (int) $destino['unidade_id'],
                    'quantidade_sugerida' => $qtdSugerida,
                    'prioridade'          => $prioridade,
                    'motivo'              => $motivo,
                    'dias_para_vencer'    => $diasVencer,
                ]);

                $sugestoes[] = [
                    'id'                  => $sugestaoId,
                    'insumo_nome'         => $origem['insumo_nome'],
                    'codigo_insumo'       => $origem['codigo_insumo'],
                    'unidade_medida'      => $origem['unidade_medida'],
                    'categoria'           => $origem['categoria'],
                    'lote'                => $origem['lote'],
                    'data_validade'       => $origem['data_validade'],
                    'dias_para_vencer'    => $diasVencer,
                    'unidade_origem'      => $origem['unidade_nome'],
                    'codigo_origem'       => $origem['unidade_codigo'],
                    'unidade_destino'     => $destino['unidade_nome'],
                    'codigo_destino'      => $destino['unidade_codigo'],
                    'quantidade_sugerida' => $qtdSugerida,
                    'estoque_destino'     => (int) $destino['estoque_atual'],
                    'minimo_destino'      => (int) $destino['estoque_minimo'],
                    'prioridade'          => $prioridade,
                    'motivo'              => $motivo,
                    'nivel_destino'       => $nivelDestino,
                ];

                $excedente -= $qtdSugerida;
                $destino['deficit'] -= $qtdSugerida;

                $totalGeradas++;
                $totalNovas++;

                if ($excedente <= 0) {
                    break;
                }
            }
            unset($destino);
        }

        usort($sugestoes, fn ($a, $b) => $a['prioridade'] <=> $b['prioridade']);

        return [
            'sugestoes'         => $sugestoes,
            'total_geradas'     => $totalGeradas,
            'total_novas'       => $totalNovas,
            'total_duplicadas'  => $totalDuplicadas,
        ];
    }

    public function getSugestoesPendentes(): array
    {
        $sql = <<<SQL
            SELECT
                r.id,
                r.prioridade,
                r.motivo,
                r.dias_para_vencer,
                r.quantidade_sugerida,
                r.status,
                r.gerado_em,
                i.nome          AS insumo_nome,
                i.codigo        AS codigo_insumo,
                i.categoria,
                i.unidade_medida,
                e.lote,
                e.data_validade,
                e.quantidade    AS estoque_lote_origem,
                uo.nome         AS unidade_origem,
                uo.codigo       AS codigo_origem,
                uo.cidade       AS cidade_origem,
                ud.nome         AS unidade_destino,
                ud.codigo       AS codigo_destino,
                ud.cidade       AS cidade_destino
            FROM remanejamentos r
            JOIN insumos   i  ON i.id  = r.insumo_id
            JOIN estoque   e  ON e.id  = r.estoque_origem_id
            JOIN unidades  uo ON uo.id = r.unidade_origem_id
            JOIN unidades  ud ON ud.id = r.unidade_destino_id
            WHERE r.status = 'SUGERIDO'
            ORDER BY r.prioridade ASC, r.gerado_em DESC
        SQL;

        return $this->pdo->query($sql)->fetchAll();
    }

    public function getKpis(): array
    {
        $kpis = [];

        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM remanejamentos WHERE status = 'SUGERIDO'"
        );
        $kpis['total_sugestoes'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM remanejamentos WHERE status = 'SUGERIDO' AND prioridade = 1"
        );
        $kpis['sugestoes_criticas'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->query(<<<SQL
            SELECT COUNT(DISTINCT u.id)
            FROM unidades u
            JOIN insumos i ON i.ativo = 1
            LEFT JOIN estoque e ON e.unidade_id = u.id AND e.insumo_id = i.id
            WHERE u.ativo = 1
            GROUP BY u.id, i.id
            HAVING COALESCE(SUM(e.quantidade), 0) <= i.estoque_critico
              AND i.estoque_critico > 0
        SQL);
        $kpis['unidades_criticas'] = count($stmt->fetchAll());

        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM estoque
             WHERE data_validade IS NOT NULL
               AND DATEDIFF(data_validade, CURDATE()) BETWEEN 1 AND 30
               AND quantidade > 0"
        );
        $kpis['lotes_vencendo_30d'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM unidades WHERE ativo = 1");
        $kpis['total_unidades'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM insumos WHERE ativo = 1");
        $kpis['total_insumos'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(e.quantidade * e.preco_unitario), 0)
             FROM estoque e
             WHERE e.data_validade IS NOT NULL
               AND DATEDIFF(e.data_validade, CURDATE()) BETWEEN 1 AND 90
               AND e.quantidade > 0
               AND e.preco_unitario IS NOT NULL"
        );
        $kpis['valor_risco'] = (float) $stmt->fetchColumn();

        return $kpis;
    }

    public function atualizarStatus(int $id, string $status, ?string $usuario = null, ?int $qtdAprovada = null): bool
    {
        $statusValidos = ['APROVADO', 'EM_TRANSITO', 'CONCLUIDO', 'CANCELADO', 'REJEITADO'];
        if (!in_array($status, $statusValidos, true)) {
            return false;
        }

        $sql = "UPDATE remanejamentos SET status = :status";
        $params = [':status' => $status, ':id' => $id];

        if ($status === 'APROVADO') {
            $sql .= ", aprovado_em = NOW(), aprovado_por = :usuario";
            $params[':usuario'] = $usuario ?? 'sistema';
            if ($qtdAprovada !== null) {
                $sql .= ", quantidade_aprovada = :qtd";
                $params[':qtd'] = $qtdAprovada;
            }
        }

        if ($status === 'CONCLUIDO') {
            $sql .= ", concluido_em = NOW()";
        }

        $sql .= " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    private function calcularPrioridade(int $diasVencer, string $nivelDestino): int
    {
        if ($diasVencer <= self::DIAS_CRITICO && $nivelDestino === 'CRITICO') {
            return self::PRIORIDADE_CRITICO;
        }

        if ($diasVencer <= self::DIAS_CRITICO || $nivelDestino === 'CRITICO') {
            return self::PRIORIDADE_CRITICO;
        }

        if ($diasVencer <= self::DIAS_URGENTE || $nivelDestino === 'ABAIXO_MINIMO') {
            return self::PRIORIDADE_URGENTE;
        }

        if ($diasVencer <= self::DIAS_ATENCAO) {
            return self::PRIORIDADE_ATENCAO;
        }

        return self::PRIORIDADE_NORMAL;
    }

    private function determinarMotivo(int $diasVencer, string $nivelDestino): string
    {
        if ($diasVencer <= self::DIAS_CRITICO && $nivelDestino === 'CRITICO') {
            return 'VENCIMENTO_PROXIMO';
        }

        if ($nivelDestino === 'CRITICO') {
            return 'ESTOQUE_CRITICO_DESTINO';
        }

        if ($diasVencer <= self::DIAS_ATENCAO) {
            return 'VENCIMENTO_PROXIMO';
        }

        return 'REEQUILIBRIO';
    }

    private function sugestaoJaExiste(int $estoqueOrigemId, int $unidadeDestinoId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM remanejamentos
             WHERE estoque_origem_id = :eo
               AND unidade_destino_id = :ud
               AND status = 'SUGERIDO'
             LIMIT 1"
        );
        $stmt->execute([':eo' => $estoqueOrigemId, ':ud' => $unidadeDestinoId]);
        return (bool) $stmt->fetchColumn();
    }

    private function persistirSugestao(array $dados): int
    {
        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO remanejamentos
                (insumo_id, estoque_origem_id, unidade_origem_id, unidade_destino_id,
                 quantidade_sugerida, prioridade, motivo, dias_para_vencer, status)
            VALUES
                (:insumo_id, :estoque_origem_id, :unidade_origem_id, :unidade_destino_id,
                 :quantidade_sugerida, :prioridade, :motivo, :dias_para_vencer, 'SUGERIDO')
        SQL);

        $stmt->execute($dados);
        return (int) $this->pdo->lastInsertId();
    }
}
