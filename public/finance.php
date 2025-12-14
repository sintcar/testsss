<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/finance_service.php';

$pdo = ensure_db_connection();

$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$mode = $_GET['mode'] ?? 'planned';

if ($mode === 'received') {
    $data = received_finance($pdo, $from, $to);
} else {
    $mode = 'planned';
    $data = planned_finance($pdo, $from, $to);
}

$chartData = [];
foreach ($data['items'] as $item) {
    $dateKey = $mode === 'received' ? substr($item['paid_at'], 0, 10) : substr($item['start_at'], 0, 10);
    $pricing = calculate_pricing($item, new DateTime($item['start_at']), (int)$item['players'], (bool)$item['tea_room']);
    $sum = $mode === 'received' ? $item['total_received'] : $pricing['total'];
    $chartData[$dateKey] = ($chartData[$dateKey] ?? 0) + (int)$sum;
}
ksort($chartData);
$chartPoints = [];
foreach ($chartData as $date => $value) {
    $chartPoints[] = ['date' => $date, 'value' => $value];
}

render_header('Финансы');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4">Финансы</h1>
</div>
<form class="row g-2 mb-3" method="get">
    <div class="col-auto">
        <select name="mode" class="form-select">
            <option value="planned" <?= $mode === 'planned' ? 'selected' : '' ?>>Планируется</option>
            <option value="received" <?= $mode === 'received' ? 'selected' : '' ?>>Получено</option>
        </select>
    </div>
    <div class="col-auto"><input type="date" name="from" class="form-control" value="<?= h($from) ?>" placeholder="С"></div>
    <div class="col-auto"><input type="date" name="to" class="form-control" value="<?= h($to) ?>" placeholder="По"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Показать</button></div>
</form>
<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>Сумма:</strong> <?= number_format($data['total'], 0, '.', ' ') ?>₽</div>
            <div class="col-md-3"><strong>Квестов:</strong> <?= h($data['count']) ?></div>
            <div class="col-md-3"><strong>Средний чек:</strong> <?= number_format($data['average'], 0, '.', ' ') ?>₽</div>
            <div class="col-md-3"><strong>Чайная:</strong> <?= number_format($data['tea_income'], 0, '.', ' ') ?>₽</div>
            <?php if ($mode === 'received'): ?>
                <div class="col-md-3"><strong>Предоплата:</strong> <?= number_format($data['prepayment_income'], 0, '.', ' ') ?>₽</div>
                <div class="col-md-3"><strong>Доплата:</strong> <?= number_format($data['balance_income'], 0, '.', ' ') ?>₽</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="card finance-chart-card mb-3">
    <div class="card-body">
        <div class="chart-header mb-2">
            <div>
                <h2 class="h5 mb-1">Динамика доходов</h2>
                <p class="text-muted small mb-0">Линейный график по выбранному диапазону и режиму</p>
            </div>
            <div class="chart-meta">Обновляется при смене режима и дат</div>
        </div>
        <div class="chart-wrapper">
            <canvas id="incomeChart" height="220"></canvas>
        </div>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead>
        <tr>
            <th>Дата</th>
            <th>Квест</th>
            <th>Клиент</th>
            <?php if ($mode === 'received'): ?>
                <th>Предоплата</th>
                <th>Доплата</th>
                <th>Всего</th>
            <?php else: ?>
                <th>Сумма</th>
            <?php endif; ?>
            <th>Чайная</th>
            <th>Тип оплаты</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($data['items'])): ?>
            <tr>
                <td colspan="<?= $mode === 'received' ? 8 : 6 ?>" class="text-center text-muted py-4">Нет данных для отображения</td>
            </tr>
        <?php else: ?>
            <?php foreach ($data['items'] as $item): ?>
                <?php $pricing = calculate_pricing($item, new DateTime($item['start_at']), (int)$item['players'], (bool)$item['tea_room']); ?>
                <?php $sum = $mode === 'received' ? $item['total_received'] : $pricing['total']; ?>
                <?php $tea = $mode === 'received' ? $item['tea_room_amount'] : $pricing['tea']; ?>
                <tr>
                    <td><?= h($mode === 'received' ? $item['paid_at'] : $item['start_at']) ?></td>
                    <td><?= h($item['quest_name']) ?></td>
                    <td><?= h($item['client_name']) ?></td>
                    <?php if ($mode === 'received'): ?>
                        <td><?= number_format($item['prepayment_amount'], 0, '.', ' ') ?>₽</td>
                        <td><?= number_format($item['balance_amount'], 0, '.', ' ') ?>₽</td>
                        <td><?= number_format($sum, 0, '.', ' ') ?>₽</td>
                    <?php else: ?>
                        <td><?= number_format($sum, 0, '.', ' ') ?>₽</td>
                    <?php endif; ?>
                    <td><?= number_format($tea, 0, '.', ' ') ?>₽</td>
                    <td><?= h($item['payment_type'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const chartPoints = <?= json_encode($chartPoints, JSON_UNESCAPED_UNICODE) ?>;
        const canvas = document.getElementById('incomeChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const styles = getComputedStyle(document.documentElement);
        const accent = styles.getPropertyValue('--accent').trim() || '#3b82f6';
        const accentSoft = styles.getPropertyValue('--accent-strong').trim() || '#60a5fa';
        const text = styles.getPropertyValue('--text-main').trim() || '#e5e7eb';
        const muted = styles.getPropertyValue('--text-muted').trim() || '#9ca3af';
        const gridColor = 'rgba(255,255,255,0.08)';

        const dpr = window.devicePixelRatio || 1;
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.scale(dpr, dpr);

        ctx.clearRect(0, 0, width, height);

        if (!chartPoints.length) {
            ctx.fillStyle = muted;
            ctx.font = '14px "Inter", system-ui, sans-serif';
            ctx.fillText('Нет данных для выбранного диапазона', 16, height / 2);
            return;
        }

        const padding = {left: 56, right: 18, top: 18, bottom: 42};
        const plotWidth = width - padding.left - padding.right;
        const plotHeight = height - padding.top - padding.bottom;

        const maxValue = Math.max(...chartPoints.map(p => p.value));
        const yTicks = 4;
        const yMax = Math.max(1000, Math.ceil(maxValue / 500) * 500);
        const yStep = Math.ceil(yMax / yTicks / 500) * 500;

        const points = chartPoints.map((p, index) => {
            const x = padding.left + (index / Math.max(chartPoints.length - 1, 1)) * plotWidth;
            const y = padding.top + (1 - p.value / yMax) * plotHeight;
            return {...p, x, y};
        });

        ctx.strokeStyle = gridColor;
        ctx.fillStyle = muted;
        ctx.font = '12px "Inter", system-ui, sans-serif';
        ctx.lineWidth = 1;

        for (let i = 0; i <= yMax; i += yStep) {
            const y = padding.top + (1 - i / yMax) * plotHeight;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(width - padding.right, y);
            ctx.stroke();
            ctx.fillText(i.toLocaleString('ru-RU'), 10, y + 4);
        }

        const xCount = points.length;
        points.forEach((point, index) => {
            ctx.fillText(point.date, point.x - 20, height - padding.bottom + 24);
            if (xCount > 1 && index < points.length - 1) {
                const nextX = points[index + 1].x;
                ctx.beginPath();
                ctx.moveTo(point.x, height - padding.bottom);
                ctx.lineTo(nextX, height - padding.bottom);
                ctx.strokeStyle = gridColor;
                ctx.stroke();
            }
        });

        const gradient = ctx.createLinearGradient(0, padding.top, 0, height - padding.bottom);
        gradient.addColorStop(0, accent + '33');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

        ctx.beginPath();
        points.forEach((point, i) => {
            if (i === 0) {
                ctx.moveTo(point.x, point.y);
            } else {
                ctx.lineTo(point.x, point.y);
            }
        });
        ctx.lineTo(points[points.length - 1].x, height - padding.bottom);
        ctx.lineTo(points[0].x, height - padding.bottom);
        ctx.closePath();
        ctx.fillStyle = gradient;
        ctx.fill();

        ctx.beginPath();
        points.forEach((point, i) => {
            if (i === 0) {
                ctx.moveTo(point.x, point.y);
            } else {
                ctx.lineTo(point.x, point.y);
            }
        });
        ctx.strokeStyle = accent;
        ctx.lineWidth = 2;
        ctx.stroke();

        points.forEach(point => {
            ctx.beginPath();
            ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
            ctx.fillStyle = accent;
            ctx.fill();
            ctx.strokeStyle = accentSoft;
            ctx.lineWidth = 2;
            ctx.stroke();

            ctx.fillStyle = text;
            ctx.font = '12px "Inter", system-ui, sans-serif';
            ctx.fillText(point.value.toLocaleString('ru-RU') + '₽', point.x - 28, point.y - 10);
        });

        ctx.strokeStyle = gridColor;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(padding.left, padding.top - 6);
        ctx.lineTo(padding.left, height - padding.bottom);
        ctx.lineTo(width - padding.right + 6, height - padding.bottom);
        ctx.stroke();
    });
</script>
<?php
render_footer();
