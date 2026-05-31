'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('dashboard-date');
  if (el) el.textContent = new Date().toLocaleDateString('en-PH', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
  const initActive = document.querySelector('.admin-section.active');
  if (initActive) {
    const name = initActive.id.replace('section-', '');
    if (name === 'analytics') loadAnalytics();
  }
});

function buildReloadUrl(extraParams) {
  const active = document.querySelector('.admin-section.active');
  const section = active ? active.id.replace('section-', '') : 'dashboard';
  const url = new URL(window.location.href);
  url.searchParams.set('s', section);
  if (extraParams) {
    Object.entries(extraParams).forEach(([k, v]) => url.searchParams.set(k, v));
  }
  return url.toString();
}

function reloadSection() {
  window.location.href = buildReloadUrl();
}

function changeViewDate(date) {
  window.location.href = buildReloadUrl({ date });
}

function showSection(name, clickedEl) {
  document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
  const sec = document.getElementById('section-' + name);
  if (sec) sec.classList.add('active');

  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  if (clickedEl) {
    clickedEl.classList.add('active');
  } else {
    document.querySelectorAll('.nav-item').forEach(n => {
      if ((n.getAttribute('onclick') || '').includes("'" + name + "'")) n.classList.add('active');
    });
  }
  if (window.innerWidth <= 768) closeSidebar();
  window.scrollTo({ top:0, behavior:'smooth' });

  if (name === 'analytics') loadAnalytics();
}

function goToAppts(status) {
  showSection('appointments', null);
  const btn = document.querySelector(`.filter-btn[onclick="filterAppts(this,'${status}')"]`);
  if (btn) { filterAppts(btn, status); }
}

function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sidebar-overlay');
  const open = sb.classList.toggle('open');
  ov.classList.toggle('visible', open);
}
function closeSidebar() {
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebar-overlay')?.classList.remove('visible');
}

function showToast(msg, type = 'default') {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = 'toast' + (type !== 'default' ? ' ' + type : '');
  const icon = type === 'success'
    ? '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'
    : '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>';
  t.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">${icon}</svg><span>${msg}</span>`;
  c.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transform='translateY(8px)'; t.style.transition='all .3s'; setTimeout(()=>t.remove(),320); }, 3500);
}

function openApptModal(id) {
  const a = APPT_DATA[id];
  if (!a) { showToast('Data not found.','error'); return; }

  const mh   = a.medical_history || {};
  const flags = (a.priority_flags||'').split(',').map(f=>f.trim()).filter(Boolean);

  const dob    = a.patient_dob  ? new Date(a.patient_dob).toLocaleDateString('en-PH',{dateStyle:'long'}) : '—';
  const age    = a.patient_dob  ? Math.floor((Date.now()-new Date(a.patient_dob))/(365.25*24*3600*1000)) : '—';
  const date   = a.preferred_date ? new Date(a.preferred_date).toLocaleDateString('en-PH',{dateStyle:'long'}) : '—';
  const subm   = a.created_at   ? new Date(a.created_at).toLocaleString('en-PH') : '—';
  const suffixMatch = a.patient_name ? a.patient_name.match(/,\s*([^,]+)$/) : null;
  const suffix = suffixMatch ? suffixMatch[1].trim() : '—';

  const flagsHTML = flags.map(f => {
    const lbl = {senior:'Senior',pregnant:'Pregnant',pwd:'PWD'}[f] || f;
    return `<span class="priority-tag priority-${f}">${lbl}</span>`;
  }).join(' ');

  const statusColor = {pending:'orange',confirmed:'blue',in_progress:'cyan',done:'green',rejected:'red',skipped:'yellow'}[a.status]||'gray';
  const statusLbl   = {pending:'Pending',confirmed:'Approved',in_progress:'In Progress',done:'Completed',rejected:'Rejected',skipped:'Skipped'}[a.status]||a.status;

  document.getElementById('appt-modal-body').innerHTML = `
    <div class="appt-modal-patient-header">
      <div class="appt-modal-avatar">${a.patient_name.charAt(0).toUpperCase()}</div>
      <div class="appt-modal-patient-info">
        <div class="appt-modal-name">${esc(a.patient_name)} ${flagsHTML}</div>
        <div class="appt-modal-mobile">${esc(a.patient_mobile)}</div>
      </div>
    </div>

    <div class="mh-section">
      <div class="mh-section-title">I. Personal Information</div>
      <div class="mh-info-grid">
        <div class="mh-info-item"><span>Date of Birth</span><strong>${dob}</strong></div>
        <div class="mh-info-item"><span>Age</span><strong>${age} yrs</strong></div>
        <div class="mh-info-item"><span>Gender</span><strong>${esc(a.patient_sex)||'—'}</strong></div>
        <div class="mh-info-item"><span>Suffix</span><strong>${suffix}</strong></div>
        <div class="mh-info-item"><span>Company / School</span><strong>${esc(a.patient_company)||'—'}</strong></div>
        <div class="mh-info-item mh-full"><span>Address</span><strong>${esc(a.patient_address)||'—'}</strong></div>
      </div>
    </div>

    <div class="mh-section">
      <div class="mh-section-title">II. Priority Status</div>
      <div class="mh-info-grid">
        <div class="mh-info-item"><span>Senior Citizen</span><strong>${flags.includes('senior')?'<span class="tag yellow">✓ Yes</span>':'<span style="color:rgba(255,255,255,.35)">No</span>'}</strong></div>
        <div class="mh-info-item"><span>PWD</span><strong>${flags.includes('pwd')?'<span class="tag purple">✓ Yes</span>':'<span style="color:rgba(255,255,255,.35)">No</span>'}</strong></div>
        <div class="mh-info-item"><span>Pregnant</span><strong>${flags.includes('pregnant')?'<span class="tag orange">✓ Yes</span>':'<span style="color:rgba(255,255,255,.35)">No</span>'}</strong></div>
      </div>
    </div>

    ${renderMedicalHistory(mh)}

    <div class="mh-section">
      <div class="mh-section-title">IV. Appointment Information</div>
      <div class="mh-info-grid">
        <div class="mh-info-item mh-full"><span>Services</span><strong>${esc(a.services)}</strong></div>
        <div class="mh-info-item"><span>Preferred Date</span><strong>${date}</strong></div>
        <div class="mh-info-item"><span>Queue Number</span><strong style="color:#7ab8cc;">${a.queue_number ? '#'+esc(a.queue_number) : 'Not assigned yet'}</strong></div>
        <div class="mh-info-item"><span>Status</span><strong><span class="tag ${statusColor}">${statusLbl}</span></strong></div>
        <div class="mh-info-item"><span>Submitted</span><strong style="font-size:12px;">${subm}</strong></div>
      </div>
    </div>

    <div class="mh-section">
      <div class="mh-section-title">V. Medical Review Notes</div>
      ${a.review_notes ? `<div class="mh-info-item mh-full" style="margin-bottom:12px;"><span>Previous Notes</span><strong>${esc(a.review_notes)}</strong></div>` : ''}
      ${a.status === 'pending' ? `
        <textarea id="review-notes-input" class="form-control"
          placeholder="Add review notes (optional) — visible in patient records…"
          rows="3" style="margin-top:4px;resize:vertical;"></textarea>
      ` : (a.review_notes ? '' : '<p style="color:rgba(255,255,255,.35);font-size:13px;font-style:italic;">No notes recorded.</p>')}
    </div>

    ${a.status === 'pending' ? `
    <div class="modal-action-row">
      <button class="btn btn-gray" style="flex:1;" onclick="closeApptModal()">Close</button>
      <button class="btn btn-danger" style="flex:1;" onclick="rejectApptFromModal(${id})">✗ Reject</button>
      <button class="btn btn-accent" style="flex:1;" onclick="approveApptFromModal(${id})">✓ Approve</button>
    </div>` : `
    <div class="modal-action-row">
      <button class="btn btn-gray" style="width:100%;" onclick="closeApptModal()">Close</button>
    </div>`}
  `;

  document.getElementById('appt-detail-modal').classList.remove('hidden');
}

function closeApptModal() {
  document.getElementById('appt-detail-modal').classList.add('hidden');
}

function renderMedicalHistory(mh) {
  if (!mh || !Object.keys(mh).length) {
    return `<div class="mh-section"><div class="mh-section-title">III. Medical History</div><p style="color:rgba(255,255,255,.35);font-size:13px;font-style:italic;">No medical history recorded.</p></div>`;
  }
  const symptoms   = Array.isArray(mh.symptoms)   ? (mh.symptoms.join(', ')||'None') : '—';
  const conditions = Array.isArray(mh.conditions) ? (mh.conditions.join(', ')||'None') : '—';
  return `
    <div class="mh-section">
      <div class="mh-section-title">III-A. Background Information</div>
      <div class="mh-info-grid">
        <div class="mh-info-item"><span>Hereditary Diseases</span><strong>${esc(mh.hereditary)||'—'}</strong></div>
        <div class="mh-info-item"><span>Hospitalization History</span><strong>${esc(mh.hospitalization)||'—'}</strong></div>
        <div class="mh-info-item"><span>Surgery History</span><strong>${esc(mh.surgery)||'—'}</strong></div>
        <div class="mh-info-item"><span>Allergies</span><strong>${esc(mh.allergies)||'—'}</strong></div>
      </div>
    </div>
    <div class="mh-section">
      <div class="mh-section-title">III-B. Lifestyle</div>
      <div class="mh-info-grid">
        <div class="mh-info-item"><span>Smoking</span><strong>${mh.smoking ? esc(mh.smoking)+' sticks/day' : 'Non-smoker'}</strong></div>
        <div class="mh-info-item"><span>Drinking</span><strong>${esc(mh.drinking)||'—'}</strong></div>
      </div>
    </div>
    <div class="mh-section">
      <div class="mh-section-title">III-C. Symptoms &amp; Conditions</div>
      <div class="mh-info-grid">
        <div class="mh-info-item mh-full"><span>Symptoms Checked</span><strong>${symptoms}</strong></div>
        <div class="mh-info-item mh-full"><span>Pre-existing Conditions</span><strong>${conditions}</strong></div>
      </div>
    </div>`;
}

function getReviewNotes() {
  return document.getElementById('review-notes-input')?.value.trim() || '';
}

function approveApptFromModal(id) {
  if (!confirm('Approve this appointment? A queue number will be assigned and an SMS sent.')) return;
  const notes = getReviewNotes();
  post('appointment-action.php', {action:'approve', appointment_id:id, review_notes:notes}).then(d => {
    if (!d.success) { showToast(d.message||'Unable to approve.','error'); return; }
    showToast('Approved! Queue #'+d.queue_number+' assigned.','success');
    closeApptModal();
    setTimeout(()=>reloadSection(), 800);
  });
}

function rejectApptFromModal(id) {
  if (!confirm('Reject this appointment? A rejection SMS will be sent.')) return;
  const notes = getReviewNotes();
  post('appointment-action.php', {action:'reject', appointment_id:id, review_notes:notes}).then(d => {
    if (!d.success) { showToast(d.message||'Unable to reject.','error'); return; }
    showToast('Appointment rejected. SMS sent.','default');
    closeApptModal();
    setTimeout(()=>reloadSection(), 800);
  });
}

function approveAppt(id) { approveApptFromModal(id); }
function rejectAppt(id)  { rejectApptFromModal(id);  }

function removePendingRow(id) {
  const r = document.getElementById('prow-'+id);
  if (r) { r.style.opacity='0'; r.style.transition='opacity .3s'; setTimeout(()=>r.remove(),310); }
}
function updateApptRowStatus(id, status) {
  if (APPT_DATA[id]) APPT_DATA[id].status = status;
}

function callNext() {
  post('queue-action.php', {action:'call'}).then(d => {
    if (!d.success) { showToast(d.message||'Unable to advance queue.','error'); return; }
    showToast(d.next ? 'Calling #'+d.next.queue_number+' — '+d.next.patient_name+'. SMS sent.' : 'Queue is empty.', 'success');
    setTimeout(()=>reloadSection(), 600);
  });
}

function markDone() {
  if (!confirm('Mark current patient as done?')) return;
  post('queue-action.php', {action:'done'}).then(d => {
    if (!d.success) { showToast(d.message||'No patient in service.','error'); return; }
    showToast('Patient marked as done.','success');
    setTimeout(()=>reloadSection(), 600);
  });
}

function skipCurrent(id) {
  if (!confirm('Skip this patient? They can be reinserted later.')) return;
  post('queue-action.php', {action:'skip', appointment_id:id}).then(d => {
    if (!d.success) { showToast(d.message||'Unable to skip.','error'); return; }
    showToast('Patient skipped.','default');
    setTimeout(()=>reloadSection(), 600);
  });
}

function reinsertSkipped(id) {
  post('queue-action.php', {action:'reinsert', appointment_id:id}).then(d => {
    if (!d.success) { showToast(d.message||'Unable to reinsert.','error'); return; }
    showToast('Patient reinserted into queue.','success');
    setTimeout(()=>reloadSection(), 600);
  });
}

function filterAppts(btn, status) {
  document.querySelectorAll('#section-appointments .filter-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');

  const today = new Date().toISOString().slice(0, 10);
  const statusMap = { confirmed: ['confirmed','in_progress'] };

  document.querySelectorAll('#appt-table-body tr').forEach(row => {
    const rs = row.dataset.status;
    const prefDate = (row.dataset.prefDate || '').slice(0, 10);

    let show = false;
    if (status === 'today') {
      show = (prefDate === today);
    } else {
      show = rs === status || (statusMap[status]?.includes(rs));
    }
    row.style.display = show ? '' : 'none';
  });
}

function filterApptsSearch(q) {
  const lq = q.toLowerCase();
  document.querySelectorAll('#appt-table-body tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(lq) ? '' : 'none';
  });
}

function openPatientModal(id) {
  const modal = document.getElementById('patient-modal');
  const body  = document.getElementById('patient-modal-body');
  modal.classList.remove('hidden');
  body.innerHTML = '<p style="color:rgba(255,255,255,.4);text-align:center;padding:30px;">Loading…</p>';

  fetch('get-patient.php?id=' + id)
    .then(r => r.json())
    .then(d => {
      if (!d.success) { body.innerHTML = '<p style="color:#f87171;">Unable to load patient.</p>'; return; }
      const p = d.patient;
      const appts = d.appointments || [];
      body.innerHTML = `
        <div class="appt-modal-patient-header">
          <div class="appt-modal-avatar">${p.name.charAt(0).toUpperCase()}</div>
          <div>
            <div class="appt-modal-name">${esc(p.name)}</div>
            <div class="appt-modal-mobile">${esc(p.mobile)}</div>
          </div>
        </div>
        <div class="mh-info-grid" style="margin-bottom:20px;">
          <div class="mh-info-item"><span>Date of Birth</span><strong>${p.dob||'—'}</strong></div>
          <div class="mh-info-item"><span>Age</span><strong>${p.dob ? Math.floor((Date.now()-new Date(p.dob))/(365.25*24*3600*1000))+' yrs' : '—'}</strong></div>
          <div class="mh-info-item"><span>Gender</span><strong>${esc(p.sex)||'—'}</strong></div>
          <div class="mh-info-item"><span>Company</span><strong>${esc(p.company)||'—'}</strong></div>
          <div class="mh-info-item mh-full"><span>Address</span><strong>${esc(p.address)||'—'}</strong></div>
        </div>
        <div class="mh-section-title" style="margin-top:20px;">Appointment &amp; Queue History (${appts.length})</div>
        ${appts.length ? appts.map(a => {
          const hasQueue = !!a.queue_number;
          return `<div style="padding:12px 0;border-bottom:1px solid rgba(255,255,255,.07);">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:${a.review_notes?'8px':'0'};">
              <strong style="color:#7ab8cc;font-family:'DM Serif Display',serif;font-size:16px;min-width:40px;">${hasQueue?'#'+a.queue_number:'—'}</strong>
              <div style="flex:1;">
                <div style="font-size:13px;color:#fff;font-weight:500;">${esc(a.services)}</div>
                <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:2px;">${a.preferred_date ? new Date(a.preferred_date).toLocaleDateString('en-PH',{dateStyle:'medium'}) : '—'}</div>
              </div>
              <span class="tag ${statusTag(a.status)}">${statusLabel(a.status)}</span>
            </div>
            ${a.review_notes ? `<div style="margin-left:52px;font-size:12px;color:rgba(255,255,255,.52);background:rgba(255,255,255,.05);padding:8px 12px;border-radius:8px;border-left:2px solid rgba(255,255,255,.15);">📋 ${esc(a.review_notes)}</div>` : ''}
          </div>`;
        }).join('') : '<p style="color:rgba(255,255,255,.35);font-size:13px;margin-top:8px;">No appointments yet.</p>'}
        <div class="modal-action-row">
          <button class="btn btn-gray" style="width:100%;" onclick="closePatientModal()">Close</button>
        </div>`;
    }).catch(()=>{ body.innerHTML='<p style="color:#f87171;">Error loading data.</p>'; });
}
function closePatientModal() { document.getElementById('patient-modal').classList.add('hidden'); }

function statusTag(s) { return {pending:'orange',confirmed:'blue',in_progress:'cyan',done:'green',rejected:'red',skipped:'yellow',disregarded:'gray'}[s]||'gray'; }
function statusLabel(s){ return {pending:'Pending',confirmed:'Approved',in_progress:'In Progress',done:'Completed',rejected:'Rejected',skipped:'Skipped',disregarded:'Disregarded'}[s]||s; }

function filterQueue(btn, status) {
  document.querySelectorAll('#section-queue .filter-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.querySelectorAll('#queue-list .queue-list-item').forEach(item => {
    const qs = item.dataset.qstatus || '';
    item.style.display = (status === 'all' || qs === status) ? '' : 'none';
  });
}

function priorityInsert(id) {
  if (!confirm('Move this priority patient to the front of the queue?')) return;
  post('queue-action.php', { action: 'priority_insert', appointment_id: id })
    .then(d => {
      if (!d.success) { showToast(d.message || 'Unable to reorder.', 'error'); return; }
      showToast('Patient moved to front of queue.', 'success');
      setTimeout(() => reloadSection(), 600);
    });
}

function filterSmsLogs(query) {
  const q = (query || '').toLowerCase().trim();
  document.querySelectorAll('#sms-log-body tr').forEach(r => {
    const name = (r.dataset.name || '').toLowerCase();
    r.style.display = (!q || name.includes(q)) ? '' : 'none';
  });
}

function sortSmsLogs(mode) {
  const tbody = document.getElementById('sms-log-body');
  if (!tbody || !mode) return;
  const rows = Array.from(tbody.querySelectorAll('tr'));
  rows.sort((a, b) => {
    switch (mode) {
      case 'name-az':   return (a.dataset.name||'').localeCompare(b.dataset.name||'');
      case 'name-za':   return (b.dataset.name||'').localeCompare(a.dataset.name||'');
      case 'queue-asc': {
        const qa = parseInt(a.dataset.queue||'999', 10);
        const qb = parseInt(b.dataset.queue||'999', 10);
        if (qa === 999 && qb !== 999) return 1;
        if (qb === 999 && qa !== 999) return -1;
        return qa - qb;
      }
      case 'time-desc': return (b.dataset.time||'').localeCompare(a.dataset.time||'');
      default: return 0;
    }
  });
  rows.forEach(r => tbody.appendChild(r));
}

let patientSortMode = 'surname'; 

function filterPatients() {
  const q    = (document.getElementById('patient-search')?.value || '').toLowerCase();
  const date = document.getElementById('patient-date-filter')?.value || '';

  document.querySelectorAll('#patient-table tbody tr').forEach(r => {
    const text = r.textContent.toLowerCase();
    const lastVisit = r.dataset.lastVisit || '';
    const matchQ    = !q    || text.includes(q);
    const matchDate = !date || lastVisit.startsWith(date);
    r.style.display = (matchQ && matchDate) ? '' : 'none';
  });
}

function sortPatients(mode) {
  patientSortMode = mode;
  ['sort-surname-btn','sort-queue-btn'].forEach(id => {
    document.getElementById(id)?.classList.remove('active');
  });
  const btnId = mode === 'queue' ? 'sort-queue-btn' : 'sort-surname-btn';
  document.getElementById(btnId)?.classList.add('active');

  const tbody = document.querySelector('#patient-table tbody');
  if (!tbody) return;
  const rows  = Array.from(tbody.querySelectorAll('tr'));

  rows.sort((a, b) => {
    if (mode === 'queue') {
      const qa = parseInt(a.dataset.queue || '9999', 10);
      const qb = parseInt(b.dataset.queue || '9999', 10);
      return qa - qb;
    } else {
      return (a.dataset.name || '').localeCompare(b.dataset.name || '');
    }
  });
  rows.forEach(r => tbody.appendChild(r));
}

function openSmsHistory(mobile, name) {
  document.getElementById('sms-history-title').textContent = 'SMS History — ' + name;
  document.getElementById('sms-history-body').innerHTML =
    '<p style="color:rgba(255,255,255,.4);text-align:center;padding:24px;">Loading…</p>';
  document.getElementById('sms-history-modal').classList.remove('hidden');

  fetch('get-sms-history.php?mobile=' + encodeURIComponent(mobile))
    .then(r => r.json())
    .then(d => {
      const body = document.getElementById('sms-history-body');
      if (!d.success || !d.logs.length) {
        body.innerHTML = '<p style="color:rgba(255,255,255,.35);text-align:center;padding:24px;">No messages found.</p>';
        return;
      }
      const typeColor = { approved:'green', rejected:'red', called:'cyan', completed:'gray', manual:'gray' };
      body.innerHTML = d.logs.map(l => {
        const tc = typeColor[l.sms_type || 'manual'] || 'gray';
        const time = l.created_at ? new Date(l.created_at).toLocaleString('en-PH') : '—';
        return `<div style="padding:14px 0;border-bottom:1px solid rgba(255,255,255,.07);">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <span class="tag ${tc}">${l.sms_type || 'manual'}</span>
            <span class="sms-status ${l.status}">${l.status}</span>
            <span style="font-size:11px;color:rgba(255,255,255,.35);margin-left:auto;">${time}</span>
          </div>
          <p style="font-size:13px;color:rgba(255,255,255,.72);margin:0;line-height:1.55;">${esc(l.message)}</p>
        </div>`;
      }).join('') + `<div class="modal-action-row">
        <button class="btn btn-gray" style="width:100%;" onclick="closeSmsHistory()">Close</button>
      </div>`;
    }).catch(() => {
      document.getElementById('sms-history-body').innerHTML =
        '<p style="color:#f87171;text-align:center;padding:20px;">Error loading history.</p>';
    });
}
function closeSmsHistory() {
  document.getElementById('sms-history-modal').classList.add('hidden');
}

function saveTemplate() {
  const t = document.getElementById('sms-template')?.value || '';
  post('save-settings.php', {setting_key:'sms_template_called', setting_value:t})
    .then(d => showToast(d.success ? 'Template saved.' : 'Unable to save.', d.success?'success':'error'));
}
function previewSMS() {
  const t = document.getElementById('sms-template')?.value || '';
  alert('SMS Preview:\n\n' + t.replace('[Patient Name]','Patient Name').replace('[Queue #]','#003'));
}
function sendManualSMS() {
  const sel = document.getElementById('manual-sms-patient');
  const msgEl = document.getElementById('manual-sms-message');
  const mobile = sel?.value || '';
  const msg    = msgEl?.value?.trim() || '';

  if (!mobile) { showToast('Please select a patient.', 'error'); return; }
  if (!msg)    { showToast('Please enter a message.', 'error'); return; }

  post('../admin/send-manual-sms.php', { mobile, message: msg, patient_name: sel.options[sel.selectedIndex]?.text || '' })
    .then(d => {
      if (d.success) {
        showToast('SMS sent successfully.', 'success');
        if (msgEl) msgEl.value = '';
      } else {
        showToast(d.message || 'Unable to send SMS.', 'error');
      }
    });
}

function openAddService() {
  document.getElementById('svc-id').value    = '';
  document.getElementById('svc-name').value  = '';
  document.getElementById('svc-price').value = '';
  document.getElementById('svc-modal-title').textContent = 'Add Service';
  setSvcStatus(1);
  document.getElementById('svc-status-row').style.display = '';
  document.getElementById('service-modal').classList.remove('hidden');
  setTimeout(() => document.getElementById('svc-name').focus(), 120);
}

function openEditService(id, name, price, active) {
  document.getElementById('svc-id').value    = id;
  document.getElementById('svc-name').value  = name;
  document.getElementById('svc-price').value = price;
  document.getElementById('svc-modal-title').textContent = 'Edit Service';
  setSvcStatus(parseInt(active) === 1 ? 1 : 0);
  document.getElementById('svc-status-row').style.display = '';
  document.getElementById('service-modal').classList.remove('hidden');
  setTimeout(() => document.getElementById('svc-name').focus(), 120);
}

function setSvcStatus(val) {
  document.getElementById('svc-active').value = val;
  document.getElementById('svc-status-active-btn').classList.toggle('active', val === 1);
  document.getElementById('svc-status-inactive-btn').classList.toggle('active', val === 0);
}

function closeServiceModal() { document.getElementById('service-modal').classList.add('hidden'); }

function saveService() {
  const id    = document.getElementById('svc-id').value;
  const name  = document.getElementById('svc-name').value.trim();
  const price = document.getElementById('svc-price').value;
  const catId = document.getElementById('svc-category')?.value;
  const active = parseInt(document.getElementById('svc-active')?.value ?? '1');
  if (!name || !price) { showToast('Service name and price are required.', 'error'); return; }
  post('service-action.php', { action: id ? 'edit' : 'add', id, name, price, category_id: catId, active })
    .then(d => {
      if (!d.success) { showToast(d.message||'Unable to save.','error'); return; }
      showToast(id?'Service updated.':'Service added.','success');
      closeServiceModal();
      setTimeout(()=>reloadSection(), 800);
    });
}
function toggleServiceActive(id, active) {
  post('service-action.php', {action:'toggle', id, active})
    .then(d => {
      if (!d.success) { showToast('Unable to update.','error'); return; }
      showToast(active?'Service enabled.':'Service disabled.','success');
      setTimeout(()=>reloadSection(), 600);
    });
}

function setPeriod(period, btn) {
  currentAnalyticsPeriod = period;
  document.querySelectorAll('.period-btn').forEach(b=>b.classList.remove('active'));
  if(btn) btn.classList.add('active');

  const dateEl  = document.getElementById('analytics-date');
  const monthEl = document.getElementById('analytics-month');
  const yearEl  = document.getElementById('analytics-year');
  dateEl.style.display  = period==='daily'   ? '' : 'none';
  monthEl.style.display = period==='monthly' ? '' : 'none';
  yearEl.style.display  = period==='yearly'  ? '' : 'none';

  loadAnalytics();
}

function loadAnalytics() {
  const period = currentAnalyticsPeriod || 'daily';
  const date   = document.getElementById('analytics-date')?.value  || '';
  const month  = document.getElementById('analytics-month')?.value || '';
  const year   = document.getElementById('analytics-year')?.value  || '';

  fetch(`analytics-data.php?period=${period}&date=${date}&month=${month}&year=${year}`)
    .then(r=>r.json())
    .then(res => {
      if (!res.success) return;
      const d = res.data;

      const fmtIncomePct = pct => (pct >= 0 ? '+' : '') + pct + '%';
      const incomeTrend  = (pct, vs) => `${pct >= 0 ? '↑' : '↓'} ${Math.abs(pct)}% vs ${vs}`;

      setTxt('an-total', d.total ?? '—');

      const ip  = d.incomePct ?? 0;
      const ipStr = (ip >= 0 ? '+' : '') + ip + '%';
      setTxt('an-income', ipStr);

      const pct = d.pctChange || 0;

      if (period==='daily') {
        setTxt('an-pct',        (pct>=0?'↑ ':'↓ ')+Math.abs(pct)+'% vs yesterday');
        setTxt('an-income-pct', incomeTrend(ip, 'yesterday'));
        setTxt('an-appts-title', 'Appointments — ' + (d.d || ''));
        document.getElementById('an-vol-title').textContent = 'Daily Volume';
        document.getElementById('an-vol-sub').textContent   = 'Appointments for ' + (d.d || '');
      } else if (period==='monthly') {
        setTxt('an-pct',        (pct>=0?'↑ ':'↓ ')+Math.abs(pct)+'% vs last month');
        setTxt('an-income-pct', incomeTrend(ip, 'last month'));
        setTxt('an-appts-title', 'Appointments — ' + (d.m || ''));
        document.getElementById('an-vol-title').textContent = 'Monthly Volume';
        document.getElementById('an-vol-sub').textContent   = 'Daily count for ' + (d.m || '');

        if (d.dailyVolume) {
          const mx = Math.max(1, ...d.dailyVolume.map(x=>x.count));
          const bc = document.getElementById('analytics-bar-chart');
          const maxBarH = Math.max(40, (bc.clientHeight || 80) - 20);
          bc.innerHTML = d.dailyVolume.filter((_,i)=>i%2===0).map(x=>`
            <div class="bar-item">
              <div class="bar" style="height:${Math.max(6,Math.round((x.count/mx)*maxBarH))}px;"></div>
              <div class="bar-label">${x.day}</div>
            </div>`).join('');
        }
        renderTopServices(d.topServices);
      } else if (period==='yearly') {
        setTxt('an-pct',        (pct>=0?'↑ ':'↓ ')+Math.abs(pct)+'% vs '+(d.yr-1));
        setTxt('an-income-pct', incomeTrend(ip, String((d.yr||new Date().getFullYear())-1)));
        setTxt('an-appts-title', 'Appointments — ' + (d.yr || ''));
        document.getElementById('an-vol-title').textContent = 'Yearly Volume';
        document.getElementById('an-vol-sub').textContent   = 'Monthly count for '+d.yr;

        if (d.monthlyVolume) {
          const mx = Math.max(1, ...d.monthlyVolume.map(x=>x.count));
          const bc = document.getElementById('analytics-bar-chart');
          const maxBarH = Math.max(40, (bc.clientHeight || 80) - 20);
          bc.innerHTML = d.monthlyVolume.map(x=>`
            <div class="bar-item">
              <div class="bar" style="height:${Math.max(6,Math.round((x.count/mx)*maxBarH))}px;"></div>
              <div class="bar-label">${x.month}</div>
            </div>`).join('');
        }
        renderTopServices(d.topServices);
      }
      renderAnalyticsAppointments(d.appointments || []);

    }).catch(()=>{});
}

function renderAnalyticsAppointments(appointments) {
  _allAnalyticsAppts = appointments || [];

  const searchEl = document.getElementById('an-patient-search');
  if (searchEl) searchEl.value = '';

  const el  = document.getElementById('analytics-appointments-list');
  const cnt = document.getElementById('an-appts-count');
  if (!el) return;
  if (cnt) cnt.textContent = '— ' + _allAnalyticsAppts.length + ' record' + (_allAnalyticsAppts.length !== 1 ? 's' : '');

  if (!_allAnalyticsAppts.length) {
    el.innerHTML = '<p style="color:rgba(255,255,255,.3);font-size:13px;text-align:center;padding:24px;">No appointments found for this period.</p>';
    return;
  }
  el.innerHTML = _allAnalyticsAppts.map(buildAnalyticsRow).join('');
}

function openAnalyticsApptModal(id) {
  if (APPT_DATA[id]) { openApptModal(id); return; }

  const modal = document.getElementById('appt-detail-modal');
  const body  = document.getElementById('appt-modal-body');
  modal.classList.remove('hidden');
  body.innerHTML = '<p style="color:rgba(255,255,255,.4);text-align:center;padding:30px;">Loading…</p>';

  fetch('get-appt-detail.php?id=' + id)
    .then(r => r.json())
    .then(d => {
      if (!d.success) { body.innerHTML = '<p style="color:#f87171;text-align:center;padding:20px;">Unable to load details.</p>'; return; }
      APPT_DATA[id] = d.appointment;
      openApptModal(id);
    })
    .catch(() => { body.innerHTML = '<p style="color:#f87171;text-align:center;padding:20px;">Connection error.</p>'; });
}

let _allAnalyticsAppts = [];

function searchAnalyticsAppts(query) {
  const q = query.toLowerCase().trim();
  if (!_allAnalyticsAppts.length) return;

  const filtered = q
    ? _allAnalyticsAppts.filter(a =>
        (a.patient_name || '').toLowerCase().includes(q) ||
        (a.services || '').toLowerCase().includes(q)
      )
    : _allAnalyticsAppts;

  const cnt = document.getElementById('an-appts-count');
  if (cnt) cnt.textContent = '— ' + filtered.length + ' record' + (filtered.length !== 1 ? 's' : '') + (q ? ' (filtered)' : '');

  const el = document.getElementById('analytics-appointments-list');
  if (!el) return;
  if (!filtered.length) {
    el.innerHTML = `<p style="color:rgba(255,255,255,.3);font-size:13px;text-align:center;padding:24px;">No results for "${esc(query)}".</p>`;
    return;
  }
  el.innerHTML = filtered.map(buildAnalyticsRow).join('');
}

function sortAnalyticsAppts(mode) {
  if (!mode || !_allAnalyticsAppts.length) return;

  const sorted = [..._allAnalyticsAppts].sort((a, b) => {
    switch (mode) {
      case 'queue-asc': {
        const qa = parseInt(a.queue_number || '999', 10);
        const qb = parseInt(b.queue_number || '999', 10);
        if (qa === 999 && qb !== 999) return 1;
        if (qb === 999 && qa !== 999) return -1;
        return qa - qb;
      }
      case 'name-az':   return (a.patient_name||'').localeCompare(b.patient_name||'');
      case 'name-za':   return (b.patient_name||'').localeCompare(a.patient_name||'');
      case 'date-asc':  return (a.preferred_date||'').localeCompare(b.preferred_date||'');
      case 'date-desc': return (b.preferred_date||'').localeCompare(a.preferred_date||'');
      default: return 0;
    }
  });

  const q = (document.getElementById('an-patient-search')?.value || '').toLowerCase().trim();
  const visible = q ? sorted.filter(a =>
    (a.patient_name||'').toLowerCase().includes(q) || (a.services||'').toLowerCase().includes(q)
  ) : sorted;

  const el = document.getElementById('analytics-appointments-list');
  if (el) el.innerHTML = visible.length
    ? visible.map(buildAnalyticsRow).join('')
    : `<p style="color:rgba(255,255,255,.3);font-size:13px;text-align:center;padding:24px;">No results.</p>`;
}

function buildAnalyticsRow(a) {
  const sc  = statusTag(a.status);
  const sl  = statusLabel(a.status);
  const dt  = a.preferred_date ? new Date(a.preferred_date).toLocaleDateString('en-PH', {dateStyle:'medium'}) : '—';
  const qn  = a.queue_number ? '#' + esc(a.queue_number) : '—';
  const prio = (a.priority_flags||'').split(',').filter(Boolean).map(f => {
    const lbl = {senior:'Senior',pregnant:'Pregnant',pwd:'PWD'}[f.trim()]||f.trim();
    return `<span class="priority-tag priority-${f.trim()} sm">${lbl}</span>`;
  }).join('');
  return `<div style="display:flex;align-items:center;gap:12px;padding:12px 4px;border-bottom:1px solid rgba(255,255,255,.06);cursor:pointer;transition:background .15s;"
               onclick="openAnalyticsApptModal(${a.id})"
               onmouseover="this.style.background='rgba(255,255,255,.04)'"
               onmouseout="this.style.background=''">
    <strong style="color:#38bdf8;font-family:'DM Serif Display',serif;font-size:16px;min-width:42px;">${qn}</strong>
    <div style="flex:1;min-width:0;">
      <div style="font-size:13px;color:#fff;font-weight:500;">${esc(a.patient_name)} ${prio}</div>
      <div style="font-size:11px;color:rgba(255,255,255,.42);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(a.services)}</div>
    </div>
    <div style="font-size:11px;color:rgba(255,255,255,.38);flex-shrink:0;">${dt}</div>
    <span class="tag ${sc}" style="flex-shrink:0;">${sl}</span>
  </div>`;
}

function sortApptTable(mode) {
  const tbody = document.querySelector('#appt-table-body');
  if (!tbody || !mode) return;

  const rows = Array.from(tbody.querySelectorAll('tr'));
  rows.sort((a, b) => {
    switch (mode) {
      case 'name-az':   return (a.dataset.name||'').localeCompare(b.dataset.name||'');
      case 'name-za':   return (b.dataset.name||'').localeCompare(a.dataset.name||'');
      case 'queue-asc': return parseInt(a.dataset.queue||'999') - parseInt(b.dataset.queue||'999');
      case 'date-asc':  return (a.dataset.prefDate||'').localeCompare(b.dataset.prefDate||'');
      case 'date-desc': return (b.dataset.prefDate||'').localeCompare(a.dataset.prefDate||'');
      default: return 0;
    }
  });
  rows.forEach(r => tbody.appendChild(r));
}

function renderTopServices(svcMap) {
  const el = document.getElementById('analytics-top-services');
  if (!el || !svcMap) return;
  const entries = Object.entries(svcMap);
  if (!entries.length) { el.innerHTML='<p style="color:rgba(255,255,255,.3);font-size:13px;text-align:center;padding:20px 0;">No data.</p>'; return; }
  const max = Math.max(...entries.map(([,v])=>v));
  el.innerHTML = entries.map(([name,cnt])=>{
    const w = Math.max(8, Math.round((cnt/max)*100));
    const cls = w>75?'green':w>45?'':'orange';
    return `<div class="progress-item">
      <div class="progress-label"><span>${esc(name)}</span><span>${cnt} bookings</span></div>
      <div class="progress-track"><div class="progress-fill ${cls}" style="width:${w}%;"></div></div>
    </div>`;
  }).join('');
}

function setTxt(id, val) { const el=document.getElementById(id); if(el) el.textContent=val; }

function exportPatients() {
  const rows    = Array.from(document.querySelectorAll('#patient-table tbody tr'));
  const header  = ['Name','Mobile','Sex','Age','Company','Address','Total Visits','Last Visit'];
  const csvRows = [header.map(h => `"${h}"`).join(',')];

  rows.forEach(r => {
    const tds = Array.from(r.querySelectorAll('td')).slice(0, -1); // skip action button column
    const cells = tds.map((td, i) => {
      let text = (i === 0 ? r.getAttribute('data-name') : td.innerText) || '';
      text = text.trim();

      if (i === 1) {
        let digits = text.replace(/\D/g, '');
        if (digits.startsWith('63')) digits = digits.slice(2);
        if (digits.startsWith('0'))  digits = digits.slice(1);
        text = '+63' + digits;
        return `"\t${text}"`;
      }

      return `"${text.replace(/"/g, '""')}"`;
    });
    if (cells.length) csvRows.push(cells.join(','));
  });

  const blob = new Blob(['﻿' + csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = 'patients_' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
  URL.revokeObjectURL(url);
  showToast('Patients exported to CSV.', 'success');
}

function adminLogout() {
  if (confirm('Sign out of admin panel?')) window.location.href='logout.php';
}

async function post(url, body) {
  try {
    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return await r.json();
  } catch {
    showToast('Connection error.', 'error');
    return { success: false };
  }
}
function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
