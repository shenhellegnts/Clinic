'use strict';

function formatMobileInput(el) {
  let digits = el.value.replace(/\D/g, '');
  if (digits.startsWith('63')) digits = digits.slice(2);
  if (digits.startsWith('0'))  digits = digits.slice(1);
  digits = digits.slice(0, 10);
  let formatted = digits;
  if (digits.length > 6)      formatted = digits.slice(0,3) + ' ' + digits.slice(3,6) + ' ' + digits.slice(6);
  else if (digits.length > 3) formatted = digits.slice(0,3) + ' ' + digits.slice(3);
  el.value = formatted;
}

const state = {
  mobile: '',
  otpSent: false,
  patient: null,
  priorityFlags: [],
  medicalHistory: null,
  selectedServices: [],
  appointmentId: null,
  queueNum: null,
  currentStep: 0,
  bookingForOther: false,
  existingPatients: [],
  selectedExistingPatient: null,
};

let statusPollInterval = null;

function goPatientStep(n, fromPopState = false) {
  document.querySelectorAll('.patient-screen').forEach((s, i) => {
    s.classList.toggle('active', i === n);
  });
  state.currentStep = n;
  window.scrollTo({ top: 0, behavior: 'smooth' });
  if (n === 5) updateSummary();
  if (n === 6) restoreQueueState();
  if (n === 7) loadPatientHistory();
  if (n === 1) {
    const cb    = document.getElementById('booking-for-other-check');
    const label = document.getElementById('another-booking-row');
    if (cb)    { cb.checked = false; state.bookingForOther = false; }
    if (label) label.classList.remove('checked');
  }
  if (!fromPopState) {
    history.pushState({ step: n }, '', '#step' + n);
  }
}

window.addEventListener('popstate', e => {
  const step = e.state?.step ?? 0;
  goPatientStep(step, true);
});

function toggleDropdown() {
  const menu     = document.getElementById('dropdown-menu');
  const backdrop = document.getElementById('dd-backdrop');
  const isOpen   = menu.classList.contains('open');
  if (isOpen) {
    menu.classList.remove('open');
    backdrop.style.display = 'none';
  } else {
    menu.classList.add('open');
    backdrop.style.display = 'block';
  }
}
function closeDropdown() {
  document.getElementById('dropdown-menu').classList.remove('open');
  document.getElementById('dd-backdrop').style.display = 'none';
}

function showToast(msg, type = 'default', duration = 3500) {
  const container = document.getElementById('toast-container');
  const toast     = document.createElement('div');
  toast.className = 'toast' + (type !== 'default' ? ' ' + type : '');
  toast.innerHTML = `
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      ${type === 'success'
        ? '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'
        : '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>'}
    </svg>
    <span>${msg}</span>
  `;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    toast.style.transition = 'all .3s ease';
    setTimeout(() => toast.remove(), 320);
  }, duration);
}

function sendOTP() {
  const input  = document.getElementById('mobile-input');
  const mobile = input.value.replace(/\D/g, '');
  if (mobile.length !== 10 || !mobile.startsWith('9')) {
    showToast('Enter a valid PH mobile number starting with 9 (e.g. 9XX XXX XXXX).', 'error');
    input.focus();
    return;
  }

  const btn = document.querySelector('#pstep-1 .btn-primary');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Sending…'; }

  fetch('send-otp.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mobile }),
  })
    .then(r => r.json())
    .then(data => {
      if (btn) { btn.disabled = false; btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013 12.62 19.79 19.79 0 01.06 4.1a2 2 0 012-2.18h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.13 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg> Send OTP'; }

      if (!data.success) { showToast(data.message || 'Unable to send OTP.', 'error'); return; }

      state.mobile = data.mobile || ('+63' + mobile);
      document.getElementById('otp-phone-display').textContent = state.mobile;
      const mobileDisplay = document.getElementById('patient-mobile-display');
      if (mobileDisplay) mobileDisplay.value = state.mobile;
      state.otpSent = true;
      goPatientStep(2);
      showToast('Code sent to ' + state.mobile, 'success');
      setTimeout(() => { const boxes = document.querySelectorAll('.otp-box'); if (boxes.length) boxes[0].focus(); }, 360);
    })
    .catch(() => {
      if (btn) { btn.disabled = false; btn.textContent = 'Send OTP'; }
      showToast('Connection error. Please try again.', 'error');
    });
}

function verifyOTP() {
  const boxes = document.querySelectorAll('.otp-box');
  const code  = Array.from(boxes).map(b => b.value).join('');
  if (code.length < 6) {
    showToast('Enter the complete 6-digit code.', 'error');
    boxes.forEach(b => b.classList.add('error'));
    setTimeout(() => boxes.forEach(b => b.classList.remove('error')), 650);
    if (boxes[0]) boxes[0].focus();
    return;
  }

  const btn = document.querySelector('#pstep-2 .btn-primary');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Verifying…'; }

  fetch('verify-otp.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mobile: state.mobile, code }),
  })
    .then(r => r.json())
    .then(data => {
      if (btn) { btn.disabled = false; btn.textContent = 'Verify & Continue'; }
      if (!data.success) {
        boxes.forEach(b => { b.classList.add('error'); b.value = ''; });
        boxes[0].focus();
        setTimeout(() => boxes.forEach(b => b.classList.remove('error')), 700);
        showToast(data.message || 'Incorrect code. Try again.', 'error');
        return;
      }
      boxes.forEach(b => b.classList.remove('error'));
      showToast('Verified! Welcome.', 'success');

      fetch('get-patients-by-mobile.php?mobile=' + encodeURIComponent(state.mobile))
        .then(r => r.json())
        .then(d => {
          state.existingPatients = d.patients || [];
          openPatientSelectModal();
        })
        .catch(() => {
          state.existingPatients = [];
          openPatientSelectModal();
        });
    })
    .catch(() => {
      if (btn) { btn.disabled = false; btn.textContent = 'Verify & Continue'; }
      showToast('Connection error. Please try again.', 'error');
    });
}

function toggleBookingForOtherClick(label) {
  const cb = document.getElementById('booking-for-other-check');
  if (!cb) return;
  cb.checked = !cb.checked;
  state.bookingForOther = cb.checked;
  label.classList.toggle('checked', cb.checked);
}
function toggleBookingForOther(checkbox) {
  state.bookingForOther = checkbox.checked;
  const label = document.getElementById('another-booking-row');
  if (label) label.classList.toggle('checked', checkbox.checked);
}

function openPatientSelectModal() {
  buildPatientList();
  const sub = document.querySelector('#patient-select-modal .modal-sub');
  if (sub) {
    sub.textContent = state.bookingForOther
      ? 'You\'re booking for someone else. Select an existing profile or create a new one.'
      : 'Select an existing profile or create a new one linked to your mobile number.';
  }
  document.getElementById('patient-select-modal').classList.add('open');
}

function closePatientModal() {
  document.getElementById('patient-select-modal').classList.remove('open');
}

function buildPatientList() {
  const container = document.getElementById('patient-list-container');
  const patients  = state.existingPatients || [];

  const profileCards = patients.map((p, i) => `
    <div class="patient-card-select ${i === 0 ? 'selected' : ''}"
         id="pcard-${p.id}"
         data-patient-id="${p.id}"
         onclick="selectPatientCard(this)">
      <div class="patient-card-avatar">${p.name.charAt(0).toUpperCase()}</div>
      <div style="flex:1;min-width:0;">
        <div class="patient-card-name">${p.name}</div>
        <div class="patient-card-meta">Existing profile · ${state.mobile}</div>
      </div>
      <svg class="patient-card-arrow" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
    </div>`).join('');

  const newCard = `
    <div class="patient-card-select ${patients.length === 0 ? 'selected' : ''}"
         id="pcard-new"
         data-patient-id="new"
         onclick="selectPatientCard(this)"
         style="margin-top:${patients.length ? '8' : '0'}px;">
      <div class="patient-card-avatar" style="background:linear-gradient(135deg,#0ea5e9,#06b6d4);">
        <svg width="18" height="18" fill="none" stroke="#fff" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      </div>
      <div style="flex:1;min-width:0;">
        <div class="patient-card-name">${patients.length ? 'Book for a different person' : 'Create new profile'}</div>
        <div class="patient-card-meta">Linked to ${state.mobile}</div>
      </div>
      <svg class="patient-card-arrow" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
    </div>`;

  container.innerHTML = profileCards + newCard;
}

function selectPatientCard(el) {
  document.querySelectorAll('.patient-card-select').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
}

function toggleAnotherPerson(checkbox) {
  state.bookingForOther = checkbox.checked;
  const container = document.getElementById('patient-list-container');
  if (checkbox.checked) {
    container.innerHTML += `
      <div class="patient-card-select" id="pcard-other" onclick="selectPatientCard(this)" style="margin-top:10px;">
        <div class="patient-card-avatar" style="background:linear-gradient(135deg,#ff6b35,#f97316);">+</div>
        <div>
          <div class="patient-card-name">New Patient Profile</div>
          <div class="patient-card-meta">Create a new profile linked to ${state.mobile}</div>
        </div>
        <svg class="patient-card-arrow" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </div>
    `;
  } else {
    const other = document.getElementById('pcard-other');
    if (other) other.remove();
    const self = document.getElementById('pcard-self');
    if (self) selectPatientCard(self);
  }
}

function selectPatientAndContinue() {
  const selected   = document.querySelector('.patient-card-select.selected');
  const patientId  = selected?.dataset.patientId;

  if (patientId && patientId !== 'new') {
    const patient = state.existingPatients.find(p => String(p.id) === String(patientId));
    state.selectedExistingPatient = patient || null;
  } else {
    state.selectedExistingPatient = null;
  }

  closePatientModal();
  updateProfileHeader(true);

  if (state.selectedExistingPatient) {
    prefillPatientProfile(state.selectedExistingPatient);
  } else {
    clearProfileForm();
  }

  resetMedicalHistory();
  goPatientStep(3);
}

function parseName(fullName) {
  if (!fullName) return { first: '', middle: '', last: '', suffix: '' };
  let suffix = '';
  const ci = fullName.lastIndexOf(',');
  if (ci !== -1) {
    suffix   = fullName.slice(ci + 1).trim();
    fullName = fullName.slice(0, ci).trim();
  }
  const parts = fullName.trim().split(/\s+/);
  if (parts.length === 1) return { first: parts[0], middle: '', last: '',        suffix };
  if (parts.length === 2) return { first: parts[0], middle: '', last: parts[1],  suffix };
  return {
    first:  parts[0],
    middle: parts.slice(1, -1).join(' '),
    last:   parts[parts.length - 1],
    suffix,
  };
}

function prefillPatientProfile(patient) {
  if (!patient) return;
  const { first, middle, last, suffix } = parseName(patient.name);

  const set = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };

  set('patient-firstname',   first);
  set('patient-middlename',  middle);
  set('patient-lastname',    last);
  set('patient-dob',         patient.dob     || '');
  set('patient-address',     patient.address || '');
  set('patient-company',     patient.company || '');

  const sxEl = document.getElementById('patient-suffix');
  if (sxEl && suffix) {
    const opt = Array.from(sxEl.options).find(o => o.value === suffix);
    if (opt) sxEl.value = suffix;
  }

  const sexEl = document.getElementById('patient-sex');
  if (sexEl && patient.sex) sexEl.value = patient.sex;

  const mobEl = document.getElementById('patient-mobile-display');
  if (mobEl) mobEl.value = state.mobile;
}

function clearProfileForm() {
  ['patient-firstname','patient-middlename','patient-lastname',
   'patient-address','patient-company'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  const dobEl = document.getElementById('patient-dob'); if (dobEl) dobEl.value = '';
  const sxEl  = document.getElementById('patient-suffix'); if (sxEl)  sxEl.value = 'None';
  const sexEl = document.getElementById('patient-sex');    if (sexEl) sexEl.value = 'Male';
  const mobEl = document.getElementById('patient-mobile-display'); if (mobEl) mobEl.value = state.mobile;
  state.priorityFlags = [];
  ['pill-senior','pill-pregnant','pill-pwd'].forEach(id => {
    document.getElementById(id)?.classList.remove('active');
  });
}

function updateProfileHeader(loggedIn) {
  const avatar      = document.getElementById('profile-avatar-initial');
  const label       = document.getElementById('profile-btn-label');
  const loginItem   = document.getElementById('dd-login-item');
  const profileItem = document.getElementById('dd-profile-item');
  const bookingItem = document.getElementById('dd-booking-item');
  const queueItem   = document.getElementById('dd-queue-item');
  if (loggedIn) {
    const name = state.patient
      ? state.patient.name
      : (document.getElementById('patient-firstname')?.value || 'Patient');
    avatar.textContent = name.charAt(0).toUpperCase();
    label.textContent  = name.split(' ')[0];
    loginItem.style.display   = 'none';
    profileItem.style.display = '';
    bookingItem.style.display = '';
    queueItem.style.display   = '';
    const pcardAvatar = document.getElementById('pcard-avatar');
    if (pcardAvatar) pcardAvatar.textContent = name.charAt(0).toUpperCase();
  } else {
    avatar.textContent = '?';
    label.textContent  = 'Login / Register';
    loginItem.style.display   = '';
    profileItem.style.display = 'none';
    bookingItem.style.display = 'none';
    queueItem.style.display   = 'none';
  }
}

function togglePriority(flag) {
  const pill = document.getElementById('pill-' + flag);
  if (!pill) return;
  const active = pill.classList.toggle('active');
  if (active) {
    if (!state.priorityFlags.includes(flag)) state.priorityFlags.push(flag);
  } else {
    state.priorityFlags = state.priorityFlags.filter(f => f !== flag);
  }
}

function saveProfileAndContinue() {
  const first  = document.getElementById('patient-firstname').value.trim();
  const last   = document.getElementById('patient-lastname').value.trim();
  const middle = document.getElementById('patient-middlename').value.trim();
  const suffix = document.getElementById('patient-suffix').value;
  const dob    = document.getElementById('patient-dob').value;

  if (!first) { showToast('Please enter your first name.', 'error'); return; }
  if (!last)  { showToast('Please enter your last name.', 'error'); return; }
  if (!dob)   { showToast('Please select your date of birth.', 'error'); return; }

  let fullName = first;
  if (middle) fullName += ' ' + middle;
  fullName += ' ' + last;
  if (suffix && suffix !== 'None') fullName += ', ' + suffix;

  state.patient = {
    name:    fullName,
    first, middle, last, suffix, dob,
    sex:     document.getElementById('patient-sex').value,
    address: document.getElementById('patient-address').value.trim(),
    company: document.getElementById('patient-company').value.trim(),
    mobile:  state.mobile,
  };

  document.getElementById('sum-patient-name').textContent  = fullName;
  document.getElementById('sum-patient-phone').textContent = state.mobile || '+63 —';
  updateProfileHeader(true);

  resetMedicalHistory();
  goPatientStep(4);
}

function resetMedicalHistory() {
  ['med-hereditary','med-hospitalization','med-surgery','med-allergies'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  const smokingEl = document.getElementById('med-smoking');
  if (smokingEl) smokingEl.value = '';
  const drinkingEl = document.getElementById('med-drinking');
  if (drinkingEl) drinkingEl.value = '';
  document.querySelectorAll('.check-pill.selected').forEach(p => p.classList.remove('selected'));
}

function toggleCheckPill(el) {
  el.classList.toggle('selected');
}

function saveMedicalAndContinue() {
  const symptoms = [];
  document.querySelectorAll('#symptoms-grid .check-pill.selected').forEach(p => {
    symptoms.push(p.getAttribute('data-val'));
  });
  const conditions = [];
  document.querySelectorAll('#conditions-grid .check-pill.selected').forEach(p => {
    conditions.push(p.getAttribute('data-val'));
  });

  state.medicalHistory = {
    hereditary:      document.getElementById('med-hereditary').value.trim(),
    hospitalization: document.getElementById('med-hospitalization').value.trim(),
    surgery:         document.getElementById('med-surgery').value.trim(),
    allergies:       document.getElementById('med-allergies').value.trim(),
    smoking:         document.getElementById('med-smoking').value.trim(),
    drinking:        document.getElementById('med-drinking').value,
    symptoms,
    conditions,
  };

  goPatientStep(5);
  updateSummary();
}

let basic5On = true;

function toggleService(el) {
  el.classList.toggle('selected');
  syncBasic5State();
  updateSummary();
}

function toggleBasic5() {
  const toggle = document.getElementById('basic5-toggle');
  basic5On = !basic5On;
  toggle.classList.toggle('on', basic5On);
  document.querySelectorAll('.service-item[data-basic="true"]').forEach(item => {
    if (basic5On) item.classList.add('selected');
    else          item.classList.remove('selected');
  });
  updateSummary();
}

function syncBasic5State() {
  const basicItems = document.querySelectorAll('.service-item[data-basic="true"]');
  const allSelected = Array.from(basicItems).every(i => i.classList.contains('selected'));
  basic5On = allSelected;
  document.getElementById('basic5-toggle').classList.toggle('on', allSelected);
}

function filterServices(btn, cat) {
  document.querySelectorAll('.service-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.service-item').forEach(item => {
    const itemCat = item.getAttribute('data-cat');
    const hidden  = cat !== 'all' && itemCat !== cat;
    item.style.display = hidden ? 'none' : '';
  });
}

function searchServices(query) {
  const q = query.toLowerCase().trim();
  document.querySelectorAll('.service-item').forEach(item => {
    const name = (item.getAttribute('data-name') || '').toLowerCase();
    item.style.display = (!q || name.includes(q)) ? '' : 'none';
  });
  if (q) document.querySelectorAll('.service-tab').forEach(b => b.classList.remove('active'));
}

function updateSummary() {
  const items = document.querySelectorAll('.service-item');
  let total = 0;
  let rows  = '';
  const serviceNames = [];

  items.forEach(el => {
    if (el.classList.contains('selected')) {
      const name  = el.getAttribute('data-name');
      const price = parseInt(el.getAttribute('data-price'), 10);
      total += price;
      serviceNames.push(name);
      rows += `<div class="summary-item"><span>${name}</span><span>₱${price.toLocaleString()}</span></div>`;
    }
  });

  state.selectedServices = serviceNames;
  const container = document.getElementById('summary-items');
  const empty     = document.getElementById('summary-empty');
  const totalEl   = document.getElementById('summary-total');

  if (container) container.innerHTML = rows;
  if (empty) empty.style.display = rows ? 'none' : 'block';
  if (totalEl) totalEl.textContent = '₱' + total.toLocaleString();
}

function openBookingModal() {
  if (!state.patient) {
    showToast('Please complete your profile first.', 'error');
    goPatientStep(3);
    return;
  }
  if (state.selectedServices.length === 0) {
    showToast('Please select at least one service.', 'error');
    return;
  }
  const preferredDate = document.getElementById('appt-datetime').value;
  if (!preferredDate) {
    showToast('Please pick a preferred appointment date.', 'error');
    return;
  }

  document.getElementById('bsc-patient').textContent  = state.patient.name;
  document.getElementById('bsc-mobile').textContent   = state.mobile;
  document.getElementById('bsc-services').textContent = state.selectedServices.join(', ');
  document.getElementById('bsc-date').textContent     = new Date(preferredDate + 'T00:00:00').toLocaleDateString('en-PH', { dateStyle: 'long' });

  let total = 0;
  document.querySelectorAll('.service-item.selected').forEach(el => {
    total += parseInt(el.getAttribute('data-price'), 10);
  });
  document.getElementById('bsc-total').textContent = '₱' + total.toLocaleString();

  document.getElementById('booking-confirm-modal').classList.add('open');
}

function closeBookingModal() {
  document.getElementById('booking-confirm-modal').classList.remove('open');
}

function submitBookingConfirmed() {
  const btn = document.getElementById('confirm-submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Submitting...';

  const preferredDate = document.getElementById('appt-datetime').value;
  const bookingData = {
    mobile:          state.mobile.replace('+63', ''),
    name:            state.patient.name,
    dob:             state.patient.dob,
    sex:             state.patient.sex,
    company:         state.patient.company,
    address:         state.patient.address,
    services:        state.selectedServices,
    custom_service:  document.getElementById('custom-service').value.trim(),
    preferred_date:  preferredDate,
    priority_flags:  state.priorityFlags,
    medical_history: state.medicalHistory,
  };

  fetch('submit-appointment.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(bookingData),
  })
    .then(res => res.json())
    .then(data => {
      closeBookingModal();
      btn.disabled = false;
      btn.innerHTML = 'Submit Appointment →';

      if (!data.success) {
        showToast(data.message || 'Unable to confirm booking.', 'error');
        return;
      }

      state.appointmentId = data.appointment_id;
      saveQueueToStorage(data.appointment_id);

      const pName = document.getElementById('pend-patient-name');
      const pSvc  = document.getElementById('pend-services');
      const pDate = document.getElementById('pend-date');
      if (pName) pName.textContent = state.patient.name;
      if (pSvc)  pSvc.textContent  = data.services || state.selectedServices.join(', ');
      if (pDate) pDate.textContent = data.preferred_date || '—';

      const aPatient = document.getElementById('appt-det-patient');
      const aSvc     = document.getElementById('appt-det-services');
      const aDate    = document.getElementById('appt-det-date');
      if (aPatient) aPatient.textContent = state.patient.name;
      if (aSvc)     aSvc.textContent     = data.services || state.selectedServices.join(', ');
      if (aDate)    aDate.textContent    = data.preferred_date || '—';

      const smsDisplay = document.getElementById('sms-phone-display');
      if (smsDisplay) smsDisplay.textContent = state.mobile;

      showQueueState('pending');
      goPatientStep(6);

      showToast('Appointment submitted! Awaiting clinic approval.', 'success', 5000);

      startStatusPolling(data.appointment_id);
    })
    .catch(() => {
      closeBookingModal();
      btn.disabled = false;
      btn.innerHTML = 'Submit Appointment →';
      showToast('Connection error. Please try again.', 'error');
    });
}

function startStatusPolling(appointmentId) {
  if (statusPollInterval) clearInterval(statusPollInterval);
  statusPollInterval = setInterval(() => {
    fetch('check-appointment-status.php?id=' + appointmentId)
      .then(r => r.json())
      .then(data => {
        if (!data.success) return;
        if (data.status === 'confirmed' || data.status === 'in_progress') {
          clearInterval(statusPollInterval);
          statusPollInterval = null;
          handleApproval(data);
        } else if (data.status === 'rejected') {
          clearInterval(statusPollInterval);
          statusPollInterval = null;
          showQueueState('rejected');
          showToast('Your appointment was not approved. Please contact the clinic.', 'error', 6000);
        }
      })
      .catch(() => {});
  }, 8000);
}

function handleApproval(data) {
  const queueNum = data.queue_number || '—';
  state.queueNum = queueNum;

  document.getElementById('my-queue-num').textContent  = queueNum;
  document.getElementById('my-queue-type').textContent = data.services || state.selectedServices.join(', ');
  document.getElementById('appt-det-queue').textContent = '#' + queueNum;

  if (data.people_ahead !== undefined) {
    document.getElementById('ahead-count').textContent = data.people_ahead;
  }
  if (data.now_serving) {
    document.getElementById('now-serving-info').textContent    = '#' + data.now_serving.queue_number + ' — ' + data.now_serving.patient_name;
    document.getElementById('now-serving-service').textContent = data.now_serving.services || '—';
  }

  showQueueState('approved');
  showToast('Your appointment is approved! Queue #' + queueNum, 'success', 6000);
}

function showQueueState(state) {
  ['pending', 'approved', 'rejected'].forEach(s => {
    const el = document.getElementById('queue-state-' + s);
    if (el) el.style.display = s === state ? '' : 'none';
  });
}

function cancelQueue() {
  if (confirm('Are you sure you want to cancel your queue position?')) {
    if (statusPollInterval) { clearInterval(statusPollInterval); statusPollInterval = null; }
    state.queueNum = null;
    state.appointmentId = null;
    try { localStorage.removeItem('clinic_appt_id'); } catch (e) {}
    showToast('Queue cancelled.', 'default');
    goPatientStep(0);
  }
}

function confirmBooking() { openBookingModal(); }

function saveQueueToStorage(appointmentId) {
  try {
    localStorage.setItem('clinic_appt_id', appointmentId);
    localStorage.setItem('clinic_mobile',  state.mobile);
  } catch (e) {}
}

function restoreQueueState() {
  if (state.appointmentId) return;

  const savedId     = localStorage.getItem('clinic_appt_id');
  const savedMobile = localStorage.getItem('clinic_mobile');
  if (!savedId) { showQueueState('pending'); return; }

  if (!state.mobile && savedMobile) {
    state.mobile = savedMobile;
    const smsDisplay = document.getElementById('sms-phone-display');
    if (smsDisplay) smsDisplay.textContent = savedMobile;
  }

  fetch('check-appointment-status.php?id=' + savedId)
    .then(r => r.json())
    .then(data => {
      if (!data.success) { showQueueState('pending'); return; }
      state.appointmentId = savedId;
      if (data.status === 'confirmed' || data.status === 'in_progress') {
        handleApproval(data);
      } else if (data.status === 'rejected') {
        showQueueState('rejected');
      } else {
        const pSvc  = document.getElementById('pend-services');
        const pDate = document.getElementById('pend-date');
        if (pSvc  && data.services)       pSvc.textContent  = data.services;
        if (pDate && data.preferred_date) pDate.textContent = data.preferred_date;
        showQueueState('pending');
        startStatusPolling(savedId);
      }
    })
    .catch(() => showQueueState('pending'));
}

function loadPatientHistory() {
  const mobile = state.mobile || localStorage.getItem('clinic_mobile');
  if (!mobile) {
    showHistoryEmpty();
    return;
  }

  const mobileDisplay = document.getElementById('hist-mobile-display');
  if (mobileDisplay) mobileDisplay.textContent = mobile;

  const loading = document.getElementById('hist-loading');
  const empty   = document.getElementById('hist-empty');
  const list    = document.getElementById('hist-list');
  if (loading) loading.style.display = 'flex';
  if (empty)   empty.style.display   = 'none';
  if (list)    { list.innerHTML = ''; list.style.display = 'none'; }

  fetch('get-appointments.php?mobile=' + encodeURIComponent(mobile))
    .then(r => r.json())
    .then(data => {
      if (loading) loading.style.display = 'none';
      if (!data.success || !data.appointments || data.appointments.length === 0) {
        showHistoryEmpty(); return;
      }
      if (list) {
        list.innerHTML = data.appointments.map(buildHistoryCard).join('');
        list.style.display = 'flex';
      }
    })
    .catch(() => { if (loading) loading.style.display = 'none'; showHistoryEmpty(); });
}

function showHistoryEmpty() {
  const empty = document.getElementById('hist-empty');
  const list  = document.getElementById('hist-list');
  if (empty) empty.style.display = 'flex';
  if (list)  list.style.display  = 'none';
}

function buildHistoryCard(appt) {
  const STATUS_CONFIG = {
    pending:     { label: 'Pending Review',   chip: 'orange', dot: '#f97316' },
    confirmed:   { label: 'Approved — Queued', chip: 'blue',   dot: '#3b82f6' },
    in_progress: { label: 'Now Serving',       chip: 'green',  dot: '#10b981' },
    done:        { label: 'Completed',          chip: 'gray',   dot: '#94a3b8' },
    rejected:    { label: 'Rejected',           chip: 'red',    dot: '#ef4444' },
    cancelled:   { label: 'Cancelled',          chip: 'red',    dot: '#ef4444' },
    skipped:     { label: 'Skipped',            chip: 'yellow', dot: '#f59e0b' },
    disregarded: { label: 'Disregarded',        chip: 'gray',   dot: '#94a3b8' },
  };

  const cfg    = STATUS_CONFIG[appt.status] || { label: appt.status, chip: 'gray', dot: '#94a3b8' };
  const qNum   = appt.queue_number ? `<span class="hist-queue">#${appt.queue_number}</span>` : `<span class="hist-queue-none">No queue # yet</span>`;
  const date   = appt.preferred_date
    ? new Date(appt.preferred_date).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' })
    : '—';
  const booked = appt.created_at
    ? new Date(appt.created_at).toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' })
    : '—';

  return `
    <div class="hist-card hist-${appt.status || 'pending'}">
      <div class="hist-top">
        ${qNum}
        <span class="hist-chip hist-chip-${cfg.chip}">${cfg.label}</span>
      </div>
      <div class="hist-services">${appt.services || '—'}</div>
      <div class="hist-meta">
        <div class="hist-meta-item">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          ${date}
        </div>
        <div class="hist-meta-item">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          Booked ${booked}
        </div>
      </div>
    </div>
  `;
}

document.addEventListener('DOMContentLoaded', () => {
  const boxes = document.querySelectorAll('.otp-box');
  boxes.forEach((box, i) => {
    box.addEventListener('input', () => {
      box.value = box.value.replace(/\D/g, '');
      if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
    });
    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
    });
    box.addEventListener('paste', e => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      boxes.forEach((b, j) => { if (pasted[j]) b.value = pasted[j]; });
      const last = Math.min(pasted.length, boxes.length) - 1;
      if (last >= 0) boxes[last].focus();
    });
  });

  document.getElementById('basic5-toggle').classList.add('on');
  updateSummary();
  history.replaceState({ step: 0 }, '', '#step0');
  goPatientStep(0, true);
});
