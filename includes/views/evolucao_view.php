<?php
/**
 * Evolução da revisão por vínculo: % acumulado de quem já revisou, dia a dia.
 * Espera $rev. SVG desenhado aqui mesmo (sem biblioteca): uma escala só (0–100%),
 * linhas finas, nome na ponta de cada linha, linha-guia com os valores no hover
 * e a tabela com os números para quem não lê o gráfico.
 */
$evo = evolucao_por_vinculo($rev);
if (count($evo['dias']) < 2 || !$evo['series']) return;
// Cor segue o vínculo (ordem fixa, validada para daltonismo), nunca a posição
$corEvo = ['Empregado' => 1, 'Terceirizado' => 2, 'Polo' => 3, 'Professor' => 4];
$dadosEvo = ['dias' => $evo['dias'], 'series' => []];
foreach ($evo['series'] as $v => $s) $dadosEvo['series'][] = ['nome' => $v, 'cor' => $corEvo[$v] ?? 1] + $s;
?>
<section class="card mb-4 evo-root" aria-labelledby="evoTitulo">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
      <div>
        <h2 class="h6 fw-bold mb-1" id="evoTitulo">Evolução da revisão por vínculo</h2>
        <div class="small text-muted">Percentual acumulado de pessoas que já revisaram, dia a dia, desde <?= date('d/m/Y', strtotime($rev['inicio'])) ?>.</div>
      </div>
      <ul class="evo-legenda" aria-label="Legenda">
        <?php foreach ($dadosEvo['series'] as $s): ?>
          <li><span class="evo-chip" style="background:var(--evo-<?= (int) $s['cor'] ?>)"></span><?= e($s['nome']) ?> <strong><?= number_format(end($s['pct']), 1, ',', '.') ?>%</strong></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="evo-plot" id="evoPlot" role="img" aria-label="Gráfico de linhas: percentual acumulado de revisões por vínculo ao longo dos dias">
      <div class="evo-tip" id="evoTip" hidden></div>
    </div>
    <details class="mt-2 small">
      <summary class="text-muted">Ver os números</summary>
      <div class="table-responsive mt-2" style="max-height:320px">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light" style="position:sticky;top:0"><tr><th>Dia</th>
            <?php foreach ($dadosEvo['series'] as $s): ?><th class="text-end"><?= e($s['nome']) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach (array_reverse(array_keys($evo['dias'])) as $i): ?>
              <tr><td><?= date('d/m/Y', strtotime($evo['dias'][$i])) ?></td>
                <?php foreach ($dadosEvo['series'] as $s): ?>
                  <td class="text-end"><?= number_format($s['pct'][$i], 1, ',', '.') ?>% <span class="text-muted">(<?= number_format($s['qtd'][$i], 0, ',', '.') ?>/<?= number_format($s['total'], 0, ',', '.') ?>)</span></td>
                <?php endforeach; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  </div>
</section>
<script>
(function () {
  var D = <?= json_encode($dadosEvo, JSON_UNESCAPED_UNICODE) ?>;
  var plot = document.getElementById('evoPlot'), tip = document.getElementById('evoTip');
  var NS = 'http://www.w3.org/2000/svg', svg = null;
  var fmt = function (n) { return n.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%'; };
  var dia = function (s) { var p = s.split('-'); return p[2] + '/' + p[1]; };
  function el(tag, at, pai) { var e = document.createElementNS(NS, tag); for (var k in at) e.setAttribute(k, at[k]); if (pai) pai.appendChild(e); return e; }

  function desenhar() {
    if (svg) svg.remove();
    var W = plot.clientWidth, H = 300, n = D.dias.length;
    var m = { t: 12, r: W < 560 ? 12 : 150, b: 28, l: 40 };     // margem direita guarda os nomes na ponta das linhas
    var w = W - m.l - m.r, h = H - m.t - m.b;
    var x = function (i) { return m.l + (n > 1 ? i * w / (n - 1) : 0); };
    var y = function (p) { return m.t + h - p / 100 * h; };
    svg = el('svg', { width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, class: 'evo-svg' });
    plot.insertBefore(svg, tip);

    // Grade recessiva: 0, 25, 50, 75, 100%
    [0, 25, 50, 75, 100].forEach(function (p) {
      el('line', { x1: m.l, x2: m.l + w, y1: y(p), y2: y(p), class: p ? 'evo-grade' : 'evo-base' }, svg);
      el('text', { x: m.l - 8, y: y(p) + 4, 'text-anchor': 'end', class: 'evo-eixo' }, svg).textContent = p + '%';
    });
    // Datas: ~6 marcas, sempre a primeira e a última
    var passo = Math.max(1, Math.ceil((n - 1) / (W < 560 ? 3 : 6)));
    for (var i = 0; i < n; i += passo) if (n - 1 - i >= passo / 2) el('text', { x: x(i), y: H - 8, 'text-anchor': i ? 'middle' : 'start', class: 'evo-eixo' }, svg).textContent = dia(D.dias[i]);
    el('text', { x: x(n - 1), y: H - 8, 'text-anchor': 'end', class: 'evo-eixo' }, svg).textContent = dia(D.dias[n - 1]);

    // Linhas
    D.series.forEach(function (s) {
      var d = s.pct.map(function (p, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(p).toFixed(1); }).join(' ');
      el('path', { d: d, class: 'evo-linha', stroke: 'var(--evo-' + s.cor + ')' }, svg);
    });
    // Ponta: marcador com anel da cor da superfície + nome e % (afastados para não colidir)
    var pontas = D.series.map(function (s) { var p = s.pct[n - 1]; return { s: s, p: p, y: y(p) }; }).sort(function (a, b) { return a.y - b.y; });
    pontas.forEach(function (o) { el('circle', { cx: x(n - 1), cy: o.y, r: 4.5, class: 'evo-ponto', fill: 'var(--evo-' + o.s.cor + ')' }, svg); });
    if (m.r > 100) {
      for (var k = 1; k < pontas.length; k++) if (pontas[k].y - pontas[k - 1].y < 15) pontas[k].y = pontas[k - 1].y + 15;
      var exc = pontas.length ? pontas[pontas.length - 1].y - (m.t + h + 4) : 0;
      if (exc > 0) pontas.forEach(function (o) { o.y -= exc; });
      pontas.forEach(function (o) {
        var t = el('text', { x: x(n - 1) + 10, y: o.y + 4, class: 'evo-rotulo' }, svg);
        t.textContent = o.s.nome + ' ';
        el('tspan', { class: 'evo-rotulo-num' }, t).textContent = fmt(o.p);
      });
    }

    // Hover: linha-guia + pontos do dia + tooltip com os quatro valores
    var guia = el('line', { y1: m.t, y2: m.t + h, class: 'evo-guia', visibility: 'hidden' }, svg);
    var pts = D.series.map(function (s) { return el('circle', { r: 4.5, class: 'evo-ponto', fill: 'var(--evo-' + s.cor + ')', visibility: 'hidden' }, svg); });
    var alvo = el('rect', { x: m.l, y: m.t, width: w, height: h, fill: 'transparent' }, svg);
    function mostrar(ev) {
      var r = svg.getBoundingClientRect(), px = (ev.touches ? ev.touches[0].clientX : ev.clientX) - r.left;
      var i = Math.max(0, Math.min(n - 1, Math.round((px - m.l) / w * (n - 1))));
      guia.setAttribute('x1', x(i)); guia.setAttribute('x2', x(i)); guia.setAttribute('visibility', 'visible');
      D.series.forEach(function (s, j) { pts[j].setAttribute('cx', x(i)); pts[j].setAttribute('cy', y(s.pct[i])); pts[j].setAttribute('visibility', 'visible'); });
      var linhas = D.series.slice().sort(function (a, b) { return b.pct[i] - a.pct[i]; }).map(function (s) {
        return '<div class="evo-tip-linha"><span class="evo-chip" style="background:var(--evo-' + s.cor + ')"></span>' + s.nome +
               '<strong>' + fmt(s.pct[i]) + '</strong><small>' + s.qtd[i].toLocaleString('pt-BR') + '/' + s.total.toLocaleString('pt-BR') + '</small></div>';
      }).join('');
      tip.innerHTML = '<div class="evo-tip-dia">' + D.dias[i].split('-').reverse().join('/') + '</div>' + linhas;
      tip.hidden = false;
      var tx = x(i) + 14, tw = tip.offsetWidth;
      if (tx + tw > W) tx = x(i) - 14 - tw;
      tip.style.left = Math.max(0, tx) + 'px'; tip.style.top = m.t + 'px';
    }
    function esconder() { guia.setAttribute('visibility', 'hidden'); pts.forEach(function (p) { p.setAttribute('visibility', 'hidden'); }); tip.hidden = true; }
    alvo.addEventListener('mousemove', mostrar); alvo.addEventListener('touchstart', mostrar, { passive: true });
    alvo.addEventListener('touchmove', mostrar, { passive: true }); alvo.addEventListener('mouseleave', esconder);
  }
  desenhar();
  var t; window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(desenhar, 120); });
})();
</script>
