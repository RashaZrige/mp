<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

  <style>
:root{
  --bg1:#f3f8ff; 
  --bg2:#eef6ff;
  --text:#0b0f1a; 
  --muted:#99a3b2;
  --card:#ffffff; 
  --border:#edf0f4;
  --radius-xl:26px; 
  --radius-md:10px;
  --shadow:0 20px 46px rgba(22,60,120,.12);
  --primary:#137BEA; 
  --blue:#1e90ff;
  --green:#12b886; 
  --green-100:#e6fff7; 
  --green-200:#baf2de;
  --amber:#f59f00; 
  --amber-100:#fff3cd; 
  --amber-200:#ffe19a;
  --red:#ef4444;   
  --red-100:#ffe3e3;  
  --red-200:#f7b6b6;
}

*{ box-sizing:border-box; }
html,body{
  margin:0;
  background:linear-gradient(90deg, var(--bg1), var(--bg2) 60%, #e9f3ff 100%);
  color:var(--text);
  font-family:'Nunito',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
}

.wrap{ 
  max-width:1060px; 
  margin:26px auto 40px; 
  padding:0 22px; 
}

.toprow{ 
  display:flex; 
  justify-content:space-between; 
  align-items:center; 
  margin-bottom:16px; 
}
.welcome{ 
  margin:0; 
  font-size:22px; 
  font-weight:800; 
}
.right-actions{ 
  display:flex; 
  align-items:center; 
  gap:18px; 
}

.availability{ 
  display:flex; 
  align-items:center; 
  gap:10px; 
  cursor:pointer; 
  user-select:none; 
}
.availability .ask{ 
  color:#7a84a0; 
  font-weight:700; 
  font-size:14px; 
}
.availability input{ display:none; }

.switch{
  width:46px; 
  height:24px; 
  border-radius:999px; 
  background:#d5dbe6;
  position:relative; 
  box-shadow:inset 0 2px 6px rgba(0,0,0,.06);
  transition:background .15s ease;
}
.switch .knob{
  position:absolute; 
  top:2px; 
  left:2px; 
  width:20px; 
  height:20px; 
  border-radius:50%;
  background:#fff; 
  box-shadow:0 2px 6px rgba(0,0,0,.15); 
  transition:left .15s ease;
}
.switch.on{ background:var(--blue); }
.switch.on .knob{ left:24px; }

.btn-primary{
  display:inline-flex; 
  align-items:center; 
  justify-content:center;
  height:44px; 
  padding:0 18px; 
  background:var(--blue);
  color:#fff; 
  text-decoration:none; 
  font-weight:800; 
  font-size:14px;
  border-radius:10px; 
  box-shadow:0 10px 24px rgba(30,144,255,.25);
}

.card.kpi{ 
  background:var(--card); 
  border-radius:28px; 
  box-shadow:var(--shadow); 
  padding:22px 26px;
}
.kpi-grid{ 
  display:flex; 
  align-items:center; 
  justify-content:space-between; 
  gap:26px; 
  flex-wrap:wrap;
}
.kpi-item{ 
  display:flex; 
  align-items:center; 
  gap:18px; 
  min-width:220px; 
}
.kpi-icon{
  width:62px; 
  height:62px; 
  border-radius:50%; 
  display:grid; 
  place-items:center;
  background:radial-gradient(120% 120% at 30% 20%, #bfe0ff 0%, #86c5ff 45%, #5ab1ff 100%);
  box-shadow:inset 0 0 0 6px rgba(255,255,255,.42);
}
.kpi-icon svg{ width:30px; height:30px; }
.kpi-label{ color:#9aa3b2; font-size:13px; font-weight:800; margin-bottom:4px; }
.kpi-value{ font-size:28px; font-weight:800; color:#1d2736; line-height:1.1; }
.kpi-value.money{ letter-spacing:.5px; }
.kpi-sub{ margin-top:6px; font-size:12px; color:#8c95a6; font-weight:700; }

.ql-title{ margin:0 0 10px; font-size:22px; font-weight:800; }
.ql-card{ background:var(--card); border-radius:var(--radius-xl); box-shadow:var(--shadow); padding:18px; }
.ql-head{ display:flex; align-items:center; justify-content:space-between; padding:10px 8px 16px; }
.ql-heading{ margin:0; font-size:22px; font-weight:800; }

.filters{ display:flex; gap:12px; }
.filter-btn{
  display:inline-flex; 
  align-items:center; 
  gap:10px; 
  height:36px; 
  padding:0 14px;
  border-radius:10px; 
  border:1px solid #d8dde6; 
  background:#f3f5f7; 
  color:#1f2a44;
  font-weight:800; 
  font-size:14px; 
  cursor:pointer;
}
.filter-btn svg{ width:16px; height:16px; }
.filter-btn.active{ outline:2px solid #b9d9ff; }

.dropdown{ position:relative; }
.menu{
  position:absolute; 
  top:calc(100% + 8px); 
  right:0; 
  background:#fff;
  border:1px solid #e6ecf5; 
  border-radius:12px; 
  box-shadow:0 16px 36px rgba(20,40,80,.14);
  z-index:30; 
  display:none; 
  padding:12px;
}
.menu.open{ display:block; }

.list-menu ul{ list-style:none; margin:0; padding:4px; min-width:200px; }
.list-menu li{ padding:8px 10px; border-radius:8px; font-weight:800; color:#1f2a44; cursor:pointer; }
.list-menu li:hover{ background:#f1f6ff; }
.list-menu li.active{ background:#e7f1ff; outline:1px solid #b7d0ff; }

.date-menu{ width:320px; }
.cal-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
.cal-title{ font-weight:800; font-size:16px; }
.cal-nav{ width:30px; height:28px; border-radius:8px; border:1px solid #d8dde6; background:#f7fbff; font-weight:900; cursor:pointer; }
.cal-week{ display:grid; grid-template-columns:repeat(7,1fr); gap:4px; color:#9aa3b2; font-size:12px; font-weight:800; text-align:center; margin-bottom:4px; }
.cal-week span{ padding:6px 0; }
.cal-grid{ display:grid; grid-template-columns:repeat(7,1fr); grid-auto-rows:38px; gap:4px; user-select:none; }
.cal-day{ display:flex; align-items:center; justify-content:center; border-radius:8px; font-weight:800; cursor:pointer; color:#0b0f1a; }
.cal-day:hover{ background:#f1f6ff; }
.cal-day.muted{ color:#b6bfcc; }
.cal-day.today{ outline:2px solid var(--blue); outline-offset:-2px; }
.cal-day.selected{ background:var(--blue); color:#fff; }
.cal-foot{ margin-top:8px; padding-top:8px; border-top:1px solid #e6ecf5; font-size:13px; color:#1f2a44; }

.table{ width:100%; border-top:1px solid var(--border); }
.thead, .trow{
  display:grid; 
  align-items:center; 
  gap:12px;
  grid-template-columns: 2fr 1.3fr 1.4fr 1.5fr 1.1fr 1.6fr;
  padding:14px 8px;
}
.thead{ color:#a7b0bf; font-size:13px; font-weight:800; }
.trow{ border-top:1px solid var(--border); font-size:14px; }
.link{ color:#1e6ef7; text-decoration:none; font-weight:800; }

.badge{
  display:inline-flex; 
  align-items:center; 
  justify-content:center; 
  height:28px; 
  padding:0 12px;
  border-radius:8px; 
  font-size:13px; 
  font-weight:800; 
  border:1.5px solid transparent;
}
.badge.green{ background:var(--green-100); color:#0f8e6b; border-color:var(--green-200); }
.badge.amber{ background:var(--amber-100); color:#8a5a00; border-color:var(--amber-200); }
.badge.red{ background:var(--red-100); color:#b4231a; border-color:var(--red-200); }
.badge.blue{ background:#e1efff; color:#1658c5; border-color:#b7d0ff; }

.actions{ display:flex; gap:10px; }
.btn{
  display:inline-flex; 
  align-items:center; 
  justify-content:center; 
  height:32px;
  padding:0 14px; 
  border-radius:8px; 
  font-weight:800; 
  text-decoration:none; 
  border:1.5px solid transparent;
}
.btn.blue{ background:var(--blue); color:#fff; font-size:13px; padding:0 12px; white-space:nowrap; }
.btn.outline.red{ background:#fff; color:#d12a20; border-color:#ffc8c8; }
.btn.solid.red{ background:#ff5a59; color:#fff; }

/* Quick Actions Panel */
.qa-wrap{ max-width:1060px; margin:14px auto 24px; padding:0 22px; }
.qa-title{ margin:0 0 12px; font:800 20px/1.1 "Nunito",system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; color:var(--text); }
.qa-grid{ display:flex; align-items:center; justify-content:space-between; gap:28px; }
.qa-btn{
  display:flex; 
  align-items:center; 
  justify-content:space-between; 
  width:260px; 
  height:40px;
  padding:0 16px 0 18px; 
  border-radius:8px; 
  background:var(--blue); 
  color:#fff; 
  text-decoration:none;
  font-weight:800; 
  font-size:14px; 
  box-shadow:var(--shadow); 
  white-space:nowrap; 
  transition:transform .06s ease, box-shadow .2s ease;
}
.qa-btn:active{ transform:translateY(1px); box-shadow:0 6px 16px rgba(30,144,255,.25); }
.qa-ico{ width:20px; height:20px; stroke:#fff; fill:none; stroke-width:2; }
.qa-ico.box rect{ stroke:#fff; fill:none; }

/* Responsive */
@media (max-width:920px){
  .kpi-grid{ flex-wrap:wrap; }
  .kpi-item{ min-width:46%; }
}
@media (max-width:820px){
  .thead, .trow{ grid-template-columns: 1.6fr 1.2fr 1.4fr 1.4fr 1fr 1.6fr; }
}
@media (max-width:720px){
  .qa-grid{ flex-wrap:wrap; gap:14px; }
  .qa-btn{ width:100%; justify-content:center; gap:10px; }
}
@media (max-width:640px){
  .right-actions{ gap:12px; }
  .btn-primary{ height:40px; padding:0 14px; }
  .kpi-item{ min-width:100%; }
  .filters{ display:none; }
  .thead{ display:none; }
  .trow{ grid-template-columns:1fr; gap:6px; padding:14px 6px 16px; }
  .trow > div{ display:flex; justify-content:space-between; }
  .trow > div:nth-child(1){ font-weight:800; }
  .actions{ justify-content:flex-end; }
}
  </style>
</head>
<body>

  <!-- Header + Availability + CTA -->
  <section class="wrap">
    <div class="toprow">
      <h1 class="welcome">Welcome Back, Ahmad</h1>

      <div class="right-actions">
        <label class="availability" for="availToggle">
          <span class="ask">Are You Available For Work Now?</span>
          <input id="availToggle" type="checkbox" />
          <span id="switch" class="switch" role="switch" aria-checked="false">
            <span class="knob"></span>
          </span>
        </label>

        <a class="btn-primary" href="#">Go To My Bookings</a>
      </div>
    </div>

    <!-- KPIs -->
    <div class="card kpi">
      <div class="kpi-grid">
        <div class="kpi-item">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M15 9a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3Z" fill="#fff"/>
              <path d="M2 19a6 6 0 0 1 11.7-2M12 19a6 6 0 0 1 11.7-2" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-meta">
            <div class="kpi-label">Total Customers</div>
            <div class="kpi-value">100</div>
          </div>
        </div>

        <div class="kpi-item">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <rect x="3" y="4" width="18" height="16" rx="3" fill="none" stroke="#fff" stroke-width="2"/>
              <path d="M7 2v4M17 2v4M3 9h18" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-meta">
            <div class="kpi-label">Upcoming Jobs</div>
            <div class="kpi-value">12</div>
            <div class="kpi-sub">You have 12 jobs<br/>scheduled for today</div>
          </div>
        </div>

        <div class="kpi-item">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="8.5" fill="none" stroke="#fff" stroke-width="2"/>
              <path d="M8 12.5l2.6 2.6L16.5 9" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="kpi-meta">
            <div class="kpi-label">Completed Jobs</div>
            <div class="kpi-value">30</div>
          </div>
        </div>

        <div class="kpi-item">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="8.5" fill="none" stroke="#fff" stroke-width="2"/>
              <path d="M12 7v10M15.5 9.5a3.5 3.5 0 0 0-7 0c0 1.9 1.6 3 3.5 3s3.5 1.1 3.5 3a3.5 3.5 0 0 1-7 0" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-meta">
            <div class="kpi-label">Earnings</div>
            <div class="kpi-value money">$ 450</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Quick Look / Upcoming Bookings -->
  <section class="wrap">
    <h2 class="ql-title">Quick Look</h2>

    <div class="ql-card">
      <header class="ql-head">
        <h3 class="ql-heading">Upcoming Bookings</h3>

        <div class="filters">
          <!-- Date -->
          <div class="dropdown">
            <button id="btnDate" class="filter-btn" type="button" aria-expanded="false">
              <span>Date</span>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="4" width="18" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2"/>
                <path d="M7 2v4M17 2v4M3 9h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>

            <div id="menuDate" class="menu date-menu" aria-hidden="true">
              <div class="cal-head">
                <button id="calPrev" class="cal-nav" aria-label="Prev month">‹</button>
                <div id="calTitle" class="cal-title">Month YYYY</div>
                <button id="calNext" class="cal-nav" aria-label="Next month">›</button>
              </div>
              <div class="cal-week">
                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
              </div>
              <div id="calGrid" class="cal-grid"></div>
              <div class="cal-foot">
                <span>Today: </span><strong id="todayStr"></strong>
              </div>
            </div>
          </div>

          <!-- Status -->
          <div class="dropdown">
            <button id="btnStatus" class="filter-btn" type="button" aria-expanded="false">
              <span>Status</span>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
              </svg>
            </button>
            <div id="menuStatus" class="menu list-menu" aria-hidden="true">
              <ul>
                <li data-value="all" class="active">All statuses</li>
                <li data-value="confirmed">Confirmed</li>
                <li data-value="pending">Pending</li>
                <li data-value="completed">Completed</li>
                <li data-value="cancelled">Cancelled</li>
              </ul>
            </div>
          </div>

          <!-- Services -->
          <div class="dropdown">
            <button id="btnServices" class="filter-btn" type="button" aria-expanded="false">
              <span>Services</span>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
              </svg>
            </button>
            <div id="menuServices" class="menu list-menu" aria-hidden="true">
              <ul>
                <li data-service="all" class="active">All services</li>
                <li data-service="home-cleaning">Home Cleaning</li>
                <li data-service="yahoo">Yahoo</li>
                <li data-service="adobe">Adobe</li>
              </ul>
            </div>
          </div>
        </div>
      </header>

      <div class="table">
        <div class="thead">
          <div>Client name</div>
          <div>Service</div>
          <div>Phone Number</div>
          <div>Date &amp; Time</div>
          <div>Status</div>
          <div>Action</div>
        </div>

        <div class="trow" data-status="confirmed" data-service="home-cleaning">
          <div><a class="link" href="#">Jane Cooper</a></div>
          <div>Home<br>Cleaning</div>
          <div>(225) 555-0118</div>
          <div data-date="2024-08-29T10:00">Aug 29, 10:00 AM</div>
          <div><span class="badge green">Confirmed</span></div>
          <div class="actions">
            <a class="btn blue" href="#">Start job</a>
            <a class="btn outline red" href="#">Reject</a>
          </div>
        </div>

        <div class="trow" data-status="pending" data-service="yahoo">
          <div><a class="link" href="#">Floyd Miles</a></div>
          <div>Yahoo</div>
          <div>(205) 555-0100</div>
          <div data-date="2024-08-29T14:00">Aug 29, 2:00 PM</div>
          <div><span class="badge amber">Pending</span></div>
          <div class="actions">
            <a class="btn blue" href="#">Confirm</a>
            <a class="btn outline red" href="#">Reject</a>
          </div>
        </div>

        <div class="trow" data-status="cancelled" data-service="adobe">
          <div><a class="link" href="#">Ronald Richards</a></div>
          <div>Adobe</div>
          <div>(302) 555-0107</div>
          <div data-date="2024-08-30T09:00">Aug 30, 9:00 AM</div>
          <div><span class="badge red">Cancelled</span></div>
          <div class="actions">
            <a class="btn solid red" href="#">Cancelled</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Quick Actions Panel -->
  <section class="qa-wrap">
    <h3 class="qa-title">Quick Actions Panel</h3>
    <div class="qa-grid">
      <a class="qa-btn" href="#">
        <span>Add Availability</span>
        <svg class="qa-ico" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="9"></circle>
          <path d="M12 7v5l3 1.5" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
      </a>
      <a class="qa-btn" href="#">
        <span>Add New Service</span>
        <svg class="qa-ico" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="9"></circle>
          <path d="M12 8v8M8 12h8" stroke-linecap="round"></path>
        </svg>
      </a>
      <a class="qa-btn" href="#">
        <span>Upload Payment Proof</span>
        <svg class="qa-ico box" viewBox="0 0 24 24" aria-hidden="true">
          <rect x="3" y="6" width="18" height="12" rx="4" ry="4"></rect>
          <circle cx="12" cy="12" r="3.2"></circle>
          <path d="M8.2 6l1.2-1.6h5.2L15.8 6" stroke-linecap="round"></path>
        </svg>
      </a>
    </div>
  </section>

  <script>
  // ===== Helpers =====
  const qs  = (s, r=document) => r.querySelector(s);
  const qsa = (s, r=document) => [...r.querySelectorAll(s)];

  // ===== Availability switch =====
  (function(){
    const checkbox = document.getElementById('availToggle');
    const switchEl = document.getElementById('switch');
    if (checkbox && switchEl) {
      const syncSwitch = () => {
        const on = checkbox.checked;
        switchEl.classList.toggle('on', on);
        switchEl.setAttribute('aria-checked', on ? 'true' : 'false');
      };
      checkbox.addEventListener('change', syncSwitch);
      syncSwitch();
    }
  })();

  // ===== Dropdowns (Date/Status/Services) =====
  function closeAllMenus(except=null){
    qsa('.menu.open').forEach(m => { if (m !== except) m.classList.remove('open'); });
    qsa('.filter-btn[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded','false'));
    qsa('.filter-btn').forEach(b => b.classList.remove('active'));
  }
  function bindDropdown(btnId, menuId){
    const btn = qs('#'+btnId), menu = qs('#'+menuId);
    if(!btn || !menu) return;
    btn.addEventListener('click', e=>{
      e.stopPropagation();
      const willOpen = !menu.classList.contains('open');
      closeAllMenus(menu);
      menu.classList.toggle('open', willOpen);
      btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      if (willOpen) btn.classList.add('active');
    });
  }
  bindDropdown('btnDate','menuDate');
  bindDropdown('btnStatus','menuStatus');
  bindDropdown('btnServices','menuServices');
  document.addEventListener('click', ()=> closeAllMenus());
  document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeAllMenus(); });

  // ===== Calendar =====
  (function(){
    const title = qs('#calTitle'), grid = qs('#calGrid');
    const todayStr = qs('#todayStr'), prev = qs('#calPrev'), next = qs('#calNext');
    if(!title || !grid) return;

    const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    let view = new Date(); view.setDate(1);
    const today = new Date();

    const ymd = d => ${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')};

    function render(){
      title.textContent = ${MONTHS[view.getMonth()]} ${view.getFullYear()};
      todayStr.textContent = ${today.getMonth()+1}/${today.getDate()}/${today.getFullYear()};

      grid.innerHTML = '';
      const year = view.getFullYear(), month = view.getMonth();
      const start = new Date(year, month, 1 - new Date(year, month, 1).getDay());

      for(let i=0;i<42;i++){
        const d = new Date(start); d.setDate(start.getDate()+i);
        const cell = document.createElement('div');
        cell.className = 'cal-day';
        if (d.getMonth() !== month) cell.classList.add('muted');
        if (d.toDateString() === today.toDateString()) cell.classList.add('today');
        cell.textContent = d.getDate();
        cell.dataset.date = ymd(d);
        cell.addEventListener('click', ()=>{
          qsa('.cal-day', grid).forEach(el=>el.classList.remove('selected'));
          cell.classList.add('selected');
          closeAllMenus();
        });
        grid.appendChild(cell);
      }
    }
    if (prev) prev.addEventListener('click', ()=>{ view.setMonth(view.getMonth()-1); render(); });
    if (next) next.addEventListener('click', ()=>{ view.setMonth(view.getMonth()+1); render(); });
    render();
  })();

  // ===== Status filter (real filtering) =====
  (function(){
    const list = qs('#menuStatus');
    if(!list) return;
    const rows = qsa('.table .trow');

    function apply(f){
      rows.forEach(r=>{
        const st = r.dataset.status || '';
        r.style.display = (f==='all' || st===f) ? 'grid' : 'none';
      });
    }
    qsa('li', list).forEach(li=>{
      li.addEventListener('click', ()=>{
        qsa('li', list).forEach(x=>x.classList.remove('active'));
        li.classList.add('active');
        apply(li.dataset.value);
        closeAllMenus();
      });
    });
    apply('all');
  })();

  // ===== Services (hook جاهز) =====
  (function(){
    const list = qs('#menuServices');
    if(!list) return;
    qsa('li', list).forEach(li=>{
      li.addEventListener('click', ()=>{
        qsa('li', list).forEach(x=>x.classList.remove('active'));
        li.classList.add('active');
        // للتفعيل لاحقًا: فلترة حسب data-service بنفس أسلوب Status
        closeAllMenus();
      });
    });
  })();
  </script>
</body>
</html>