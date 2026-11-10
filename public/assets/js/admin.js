(() => {
  const app = document.getElementById('app');
  const $ = id => document.getElementById(id);
  let data = null, offset = 0;
  const fmt = s => { s = Math.max(0, Math.floor(s)); return Math.floor(s / 3600) + ':' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); };

  async function load() {
    try {
      const r = await fetch(app.dataset.api, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
      if (r.status === 401) { location.reload(); return; }
      if (!r.ok) throw new Error('Could not load data (' + r.status + ')');
      data = await r.json(); offset = data.now - Date.now() / 1000;
      $('msg').innerHTML = ''; $('updated').textContent = 'updated ' + new Date().toLocaleTimeString();
      render();
    } catch (e) { $('msg').innerHTML = ''; const d = document.createElement('div'); d.className = 'alert alert-danger'; d.textContent = e.message; $('msg').appendChild(d); }
  }

  function calc(r) {
    const now = Date.now() / 1000 + offset;
    const run = r.active_start ? now - r.active_start : 0;
    const used = r.completed + (r.active_counts ? run : 0);
    let level = 'normal';
    if (used > r.allowance) level = 'exceeded'; else if (used >= r.allowance * data.warn_percent / 100) level = 'warning';
    return { run, used, remaining: r.allowance - used, level, on: r.active_start !== null, long: r.active_start !== null && run > data.forgotten_after };
  }
  function badge(text, cls) { const b = document.createElement('span'); b.className = 'badge me-1 ' + cls; b.textContent = text; return b; }

  function render() {
    if (!data) return;
    const rows = data.employees.map(r => ({ r, c: calc(r) }));
    rows.sort((a, b) => (b.c.on - a.c.on) || a.r.name.localeCompare(b.r.name));
    $('cTotal').textContent = rows.length;
    $('cOn').textContent = rows.filter(x => x.c.on).length;
    $('cOver').textContent = rows.filter(x => x.c.level === 'exceeded').length;
    $('cTotalTime').textContent = fmt(rows.reduce((s, x) => s + x.c.used, 0));
    const longN = rows.filter(x => x.c.long).length;
    $('flagged').textContent = longN + ' need attention'; $('flagged').classList.toggle('d-none', !longN);

    const tb = $('rows'); tb.innerHTML = '';
    if (!rows.length) { tb.innerHTML = '<tr><td colspan="9" class="text-muted">No active employees.</td></tr>'; return; }
    for (const { r, c } of rows) {
      const tr = document.createElement('tr');
      tr.className = (c.long || c.level === 'exceeded') ? 'table-danger' : c.on ? 'table-primary' : c.level === 'warning' ? 'table-warning' : '';
      const cells = [r.code, r.name, null, c.on ? fmt(c.run) : '—', fmt(c.used), fmt(r.allowance), (c.remaining < 0 ? '−' : '') + fmt(Math.abs(c.remaining)), r.count];
      cells.forEach((v, i) => {
        const td = document.createElement('td');
        if (i >= 3) td.className = 'num';
        if (v === null) {
          td.appendChild(c.on ? badge('On break', 'bg-primary') : badge('Available', 'bg-secondary'));
          td.appendChild(c.level === 'exceeded' ? badge('Exceeded', 'bg-danger') : c.level === 'warning' ? badge('Near limit', 'bg-warning text-dark') : badge('Normal', 'bg-success'));
          if (c.long) td.appendChild(badge('Long break – check', 'bg-dark'));
        } else td.textContent = v;
        tr.appendChild(td);
      });
      const td = document.createElement('td'); const a = document.createElement('a');
      a.href = app.dataset.history + '?id=' + r.id; a.className = 'btn btn-sm btn-outline-secondary'; a.textContent = 'History';
      td.appendChild(a); tr.appendChild(td); tb.appendChild(tr);
    }
  }

  load();
  setInterval(load, 15000);     // refresh data from server every 15 s
  setInterval(render, 1000);    // tick live timers locally
})();
