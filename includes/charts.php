<?php
// Single-series column chart in plain HTML/CSS (no chart library).
// $rows: list of ['label' => 'Mon 3', 'short' => '3', 'value' => 150.0]
// $format: callable that turns a value into text, e.g. fn ($v) => peso($v)

function bar_chart(array $rows, callable $format, string $title): string
{
    $max = max(array_merge([0], array_column($rows, 'value')));
    $top = nice_ceiling($max);
    $ticks = [$top, $top / 2, 0];
    $peak = $max > 0 ? array_search($max, array_column($rows, 'value')) : null;

    ob_start(); ?>
    <figure class="chart min-w-0" aria-label="<?= e($title) ?>">
      <div class="relative flex h-44 gap-1 pl-12 sm:gap-2">
        <!-- recessive grid + y-axis labels -->
        <?php foreach ($ticks as $i => $t): ?>
          <div class="pointer-events-none absolute inset-x-0 flex items-center" style="top: <?= $i * 50 ?>%; transform: translateY(-50%)">
            <span class="w-11 shrink-0 pr-2 text-right text-[10px] text-gray-400"><?= e($format($t)) ?></span>
            <span class="h-px flex-1 <?= $t == 0 ? 'bg-gray-300' : 'bg-gray-100' ?>"></span>
          </div>
        <?php endforeach; ?>
        <!-- columns -->
        <?php foreach ($rows as $i => $r):
            $h = $top > 0 ? $r['value'] / $top * 100 : 0;
            // Keep tooltips inside the card: edge columns anchor them inward.
            $tipPos = $i < count($rows) / 4 ? 'left-0' : ($i >= count($rows) * 3 / 4 ? 'right-0' : 'left-1/2 -translate-x-1/2'); ?>
          <div class="chart-col group relative z-10 flex min-w-0 flex-1 items-end justify-center" tabindex="0"
               aria-label="<?= e($r['label'] . ': ' . $format($r['value'])) ?>">
            <?php if ($i === $peak): ?>
              <span class="absolute left-1/2 -translate-x-1/2 whitespace-nowrap text-[10px] font-semibold text-gray-700"
                    style="bottom: calc(<?= $h ?>% + 4px)"><?= e($format($r['value'])) ?></span>
            <?php endif; ?>
            <span class="chart-bar block w-full max-w-6 rounded-t bg-brand-500 transition-colors group-hover:bg-brand-600 group-focus:bg-brand-600"
                  style="height: <?= max($r['value'] > 0 ? 2 : 0, $h) ?>%"></span>
            <span class="chart-tip pointer-events-none absolute bottom-full <?= $tipPos ?> z-20 mb-2 whitespace-nowrap rounded-lg bg-ink-900 px-2.5 py-1.5 text-xs text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus:opacity-100">
              <span class="block text-white/60"><?= e($r['label']) ?></span><b><?= e($format($r['value'])) ?></b>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
      <!-- x-axis: label every day when it fits, otherwise every few days -->
      <?php $every = (int) ceil(count($rows) / 10); ?>
      <div class="mt-1.5 flex gap-1 pl-12 sm:gap-2">
        <?php foreach ($rows as $i => $r): $show = (count($rows) - 1 - $i) % $every === 0; ?>
          <span class="min-w-0 flex-1 text-center text-[10px] text-gray-400"><?= $show ? e($r['short']) : '' ?></span>
        <?php endforeach; ?>
      </div>
      <!-- table view for screen readers -->
      <table class="sr-only">
        <caption><?= e($title) ?></caption>
        <?php foreach ($rows as $r): ?><tr><th scope="row"><?= e($r['label']) ?></th><td><?= e($format($r['value'])) ?></td></tr><?php endforeach; ?>
      </table>
    </figure>
    <?php return ob_get_clean();
}

// 0 -> 100, 287 -> 300, 1234 -> 1500: a clean top for the y-axis.
function nice_ceiling(float $v): float
{
    if ($v <= 0) return 100;
    $mag = 10 ** floor(log10($v));
    foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $m) {
        if ($m * $mag >= $v) return $m * $mag;
    }
    return 10 * $mag;
}

// Last $days days ending today, filled with zeros: ['2026-10-31' => 0, ...]
function last_days(int $days): array
{
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $out[date('Y-m-d', strtotime("-$i day"))] = 0.0;
    }
    return $out;
}

function day_rows(array $byDay): array
{
    $rows = [];
    foreach ($byDay as $date => $value) {
        $ts = strtotime($date);
        $rows[] = ['label' => date('D, M j', $ts), 'short' => date('j', $ts), 'value' => (float) $value];
    }
    return $rows;
}
