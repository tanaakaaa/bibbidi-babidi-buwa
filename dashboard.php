<?php
use Remanejamento\Database;
use Remanejamento\RemanejaMotor;

if (!function_exists('badgePrioridade')) {
    function badgePrioridade(int $prioridade): string {
        return match (true) {
            $prioridade === 1 => 'badge badge--critico',
            $prioridade === 2 => 'badge badge--urgente',
            $prioridade === 3 => 'badge badge--atencao',
            default           => 'badge badge--normal',
        };
    }
    function textoPrioridade(int $prioridade): string {
        return match (true) {
            $prioridade === 1 => '⚠ CRÍTICO',
            $prioridade === 2 => '▲ URGENTE',
            $prioridade === 3 => '● ATENÇÃO',
            default           => '○ NORMAL',
        };
    }
    function textoMotivo(string $motivo): string {
        return match ($motivo) {
            'VENCIMENTO_PROXIMO'      => 'Vencimento próximo',
            'ESTOQUE_CRITICO_DESTINO' => 'Estoque crítico no destino',
            'ESTOQUE_EXCEDENTE'       => 'Excedente no estoque',
            'REEQUILIBRIO'            => 'Reequilíbrio de estoque',
            default                   => $motivo,
        };
    }
    function dataBr(?string $data): string {
        if (!$data) return '—';
        $dt = DateTime::createFromFormat('Y-m-d', $data);
        return $dt ? $dt->format('d/m/Y') : $data;
    }
    function classeDias(int $dias): string {
        if ($dias <= 30) return 'dias-critico';
        if ($dias <= 60) return 'dias-urgente';
        if ($dias <= 90) return 'dias-atencao';
        return 'dias-normal';
    }
}

$erroConexao  = null;
$kpis         = [];
$sugestoes    = [];
$lotesVencendo= [];
$deficits     = [];

try {
    $pdo   = Database::getInstance();
    $motor = new RemanejaMotor($pdo);
    $kpis          = $motor->getKpis();
    $sugestoes     = $motor->getSugestoesPendentes();
    $lotesVencendo = $motor->getLotesVencendo();
    $deficits      = $motor->getUnidadesComDeficit();
} catch (\Throwable $e) {
    $erroConexao = $e->getMessage();
}
?>

<?php if ($erroConexao): ?>
<div class="flash flash--erro">
    <strong>Erro de conexão com o banco de dados:</strong> <?= h($erroConexao) ?>
</div>
<?php else: ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding: 20px; background: white; border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--shadow-sm);">
    <h2 class="section__title" style="margin: 0; font-size: 18px;">Motor Logístico (MySQL)</h2>
    <form method="POST" action="action.php" style="margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="acao" value="executar_motor">
        <button type="submit" class="btn btn--primary btn--sm">↻ Executar Remanejamento Automático</button>
    </form>
</div>

<section class="kpi-grid" style="margin-bottom: 24px;">
    <div class="kpi-card kpi-card--<?= ($kpis['sugestoes_criticas'] ?? 0) > 0 ? 'alert' : 'neutral' ?>">
        <span class="kpi-card__value"><?= h((string)($kpis['sugestoes_criticas'] ?? 0)) ?></span>
        <span class="kpi-card__label">Sugestões Críticas</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-card__value"><?= h((string)($kpis['total_sugestoes'] ?? 0)) ?></span>
        <span class="kpi-card__label">Sugestões Pendentes</span>
    </div>
    <div class="kpi-card kpi-card--<?= ($kpis['lotes_vencendo_30d'] ?? 0) > 0 ? 'warn' : 'neutral' ?>">
        <span class="kpi-card__value"><?= h((string)($kpis['lotes_vencendo_30d'] ?? 0)) ?></span>
        <span class="kpi-card__label">Vencendo em 30 dias</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-card__value"><?= h((string)($kpis['total_unidades'] ?? 0)) ?></span>
        <span class="kpi-card__label">Unidades Ativas</span>
    </div>
</section>

<section class="section" style="margin-bottom: 24px;">
    <div class="section__header">
        <h2 class="section__title">Decisões Pendentes</h2>
    </div>
    <?php if (empty($sugestoes)): ?>
    <div class="empty-state"><p>Nenhuma sugestão gerada no banco.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Prioridade</th>
                    <th>Insumo</th>
                    <th>Validade</th>
                    <th>Movimentação (De ➔ Para)</th>
                    <th class="text-right">Quantidade</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sugestoes as $s): ?>
                <tr>
                    <td><span class="<?= badgePrioridade((int) $s['prioridade']) ?>"><?= textoPrioridade((int) $s['prioridade']) ?></span></td>
                    <td><strong><?= h($s['insumo_nome']) ?></strong><br><small class="muted"><?= h($s['codigo_insumo']) ?></small></td>
                    <td><span class="<?= classeDias((int) ($s['dias_para_vencer'] ?? 999)) ?>"><?= dataBr($s['data_validade']) ?></span></td>
                    <td>
                        <?= h($s['unidade_origem']) ?><br>
                        <small class="muted">➔ <?= h($s['unidade_destino']) ?></small>
                    </td>
                    <td class="text-right"><strong><?= number_format((int) $s['quantidade_sugerida']) ?></strong></td>
                    <td style="display: flex; gap: 8px;">
                        <form method="POST" action="action.php" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="acao" value="aprovar">
                            <input type="hidden" name="remanejamento_id" value="<?= h((string)$s['id']) ?>">
                            <button type="submit" class="btn btn--success btn--xs">✔</button>
                        </form>
                        <form method="POST" action="action.php" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="acao" value="rejeitar">
                            <input type="hidden" name="remanejamento_id" value="<?= h((string)$s['id']) ?>">
                            <button type="submit" class="btn btn--danger btn--xs">✖</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<div class="two-col">
    <section class="section">
        <div class="section__header"><h2 class="section__title">Lotes Críticos (Vencendo)</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Insumo</th><th>Unidade</th><th class="text-right">Qtd</th></tr></thead>
                <tbody>
                <?php foreach ($lotesVencendo as $lote): ?>
                    <tr>
                        <td><?= h($lote['insumo_nome']) ?></td>
                        <td><?= h($lote['unidade_codigo']) ?></td>
                        <td class="text-right"><strong><?= number_format((int) $lote['quantidade']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="section">
        <div class="section__header"><h2 class="section__title">Unidades em Déficit</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Unidade</th><th>Insumo</th><th class="text-right">Falta</th></tr></thead>
                <tbody>
                <?php foreach ($deficits as $def): ?>
                    <tr>
                        <td><?= h($def['unidade_codigo']) ?></td>
                        <td><?= h($def['insumo_nome']) ?></td>
                        <td class="text-right"><strong><?= number_format((int) $def['deficit']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php endif; ?>
