(() => {
  const api = document.getElementById('app').dataset.api;
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  const $ = id => document.getElementById(id);
  let st = null, offset = 0;

  const fmt = s => { s = Math.max(0, Math.floor(s)); return Math.floor(s / 3600) + ':' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); };
  const nowSrv = () => Date.now() / 1000 + offset;   // server clock estimate

  async function call(path, method = 'GET') {
    const r = await fetch(api + path, { method, credentials: 'same-origin', cache: 'no-store',
      headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } });
    let d = {}; try { d = await r.json(); } catch (e) {}
    if (r.status === 401) { location.reload(); return; }
    if (!r.ok) throw new Error(d.error || 'Request failed');
    return d;
  }
  function showMsg(text, type = 'danger') {
    $('msg').innerHTML = '';
    if (!text) return;
    const d = document.createElement('div'); d.className = 'alert alert-' + type; d.textContent = text; $('msg').appendChild(d);
  }
  async function load() {
    try { st = await call('status.php'); offset = st.now - Date.now() / 1000; renderHistory(); render(); }
    catch (e) { showMsg(e.message); }
  }

  function render() {
    if (!st) return;
    const on = st.active_start !== null;
    const run = on ? nowSrv() - st.active_start : 0;
    const used = st.completed + (on && st.active_counts ? run : 0);
    const remain = st.allowance - used;
    $('badge').textContent = on ? 'ON BREAK' : 'AVAILABLE';
    $('badge').className = 'badge fs-6 mb-3 ' + (on ? 'bg-primary' : 'bg-success');
    $('timer').textContent = fmt(run);
    $('timer').classList.toggle('text-primary', on);
    $('timerLabel').textContent = on ? 'Current break timer' : 'No active break';
    $('btnStart').disabled = on; $('btnEnd').disabled = !on;
    $('allow').textContent = fmt(st.allowance);
    $('count').textContent = st.count;
    $('used').textContent = fmt(used);
    $('remLabel').textContent = remain < 0 ? 'Exceeded by' : 'Remaining';
    $('remain').textContent = fmt(Math.abs(remain));
    $('remain').className = 'fs-4 ' + (remain < 0 ? 'text-danger fw-bold' : '');
    const pct = st.allowance > 0 ? Math.min(100, used / st.allowance * 100) : 100;
    const bar = $('bar'); bar.style.width = pct + '%';
    bar.className = 'progress-bar ' + (used > st.allowance ? 'bg-danger' : pct >= st.warn_percent ? 'bg-warning' : 'bg-success');
    $('longWarn').classList.toggle('d-none', !(on && run > st.forgotten_after));
  }

  function renderHistory() {
    const tb = $('hist'); tb.innerHTML = '';
    if (!st.history.length) { tb.innerHTML = '<tr><td colspan="6" class="text-muted">No breaks yet.</td></tr>'; return; }
    for (const h of st.history) {
      const tr = document.createElement('tr');
      if (h.active) tr.className = 'table-primary';
      [h.date, h.start, h.end || '—', fmt(h.sec), fmt(h.day_total)].forEach(v => { const td = document.createElement('td'); td.textContent = v; tr.appendChild(td); });
      const f = document.createElement('td');
      for (const [show, cls, txt] of [[h.active, 'bg-primary', 'Active'], [h.day_over, 'bg-danger', 'Over allowance'], [h.corrected, 'bg-secondary', 'Corrected']]) {
        if (show) { const b = document.createElement('span'); b.className = 'badge me-1 ' + cls; b.textContent = txt; f.appendChild(b); }
      }
      tr.appendChild(f); tb.appendChild(tr);
    }
  }

  async function act(path, btn) {
    btn.disabled = true; showMsg('');
    try { await call(path, 'POST'); } catch (e) { showMsg(e.message); }
    await load();
  }
  $('btnStart').addEventListener('click', e => act('break_start.php', e.target));
  $('btnEnd').addEventListener('click', e => act('break_end.php', e.target));

  load();
  setInterval(render, 1000);
  setInterval(load, 30000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
})();
