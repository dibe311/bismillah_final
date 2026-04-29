// MediRek — Main JS

document.addEventListener('DOMContentLoaded', () => {

    // ---- Auto-dismiss alerts ----
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 4500);
    });

    // ---- Confirm delete/action buttons ----
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!confirm(btn.dataset.confirm || 'Yakin ingin menghapus data ini?')) {
                e.preventDefault();
            }
        });
    });

    // ---- Modal system ----
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.dataset.modalOpen);
            if (modal) modal.classList.add('open');
        });
    });

    document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-overlay')?.classList.remove('open');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // ---- Mobile sidebar toggle ----
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // ---- Live clock in topbar ----
    const clockEl = document.getElementById('liveClock');
    if (clockEl) {
        const update = () => {
            const now = new Date();
            clockEl.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        };
        update();
        setInterval(update, 10000);
    }

    // ---- Demo account fill (login page) ----
    document.querySelectorAll('.demo-row').forEach(row => {
        row.addEventListener('click', () => {
            const email = row.dataset.email;
            const pwd = row.dataset.password;
            const emailField = document.getElementById('email');
            const pwdField = document.getElementById('password');
            if (emailField) emailField.value = email;
            if (pwdField) { pwdField.value = pwd; pwdField.type = 'text'; setTimeout(() => pwdField.type='password', 600); }
        });
    });

    // ---- Inline patient search autocomplete (new.php) ----
    const patientSearch = document.getElementById('patientSearch');
    const patientDropdown = document.getElementById('patientDropdown');
    const patientIdInput = document.getElementById('patientId');

    if (patientSearch && patientDropdown) {
        let debounceTimer;
        patientSearch.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const q = patientSearch.value.trim();
                if (q.length < 2) { patientDropdown.classList.remove('open'); return; }
                try {
                    // FIX: gunakan path relatif dari BASE_URL, tidak hardcode /medirek/
                    const res = await fetch(`/apb/search_patients?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
                    const data = await res.json();
                    patientDropdown.innerHTML = '';
                    if (!data.length) {
                        patientDropdown.innerHTML = '<div class="patient-option text-muted">Tidak ditemukan</div>';
                    } else {
                        data.forEach(p => {
                            const div = document.createElement('div');
                            div.className = 'patient-option';
                            div.innerHTML = `<div class="patient-option-name">${escHtml(p.name)}</div><div class="patient-option-sub">NIK: ${escHtml(p.nik)} &bull; ${p.gender === 'L' ? 'Laki-laki' : 'Perempuan'} &bull; ${p.age} th</div>`;
                            div.addEventListener('click', () => {
                                patientSearch.value = p.name;
                                if (patientIdInput) patientIdInput.value = p.id;
                                patientDropdown.classList.remove('open');
                                // Enable submit button
                                const submitBtn = document.getElementById('submitBtn');
                                if (submitBtn) submitBtn.disabled = false;
                                // Trigger patient info update
                                if (typeof onPatientSelected === 'function') onPatientSelected(p);
                                // Update info card for new.php
                                const infoCard = document.getElementById('patientInfoCard');
                                const headerRow = document.getElementById('patientHeaderRow');
                                const infoAvatar = document.getElementById('infoAvatar');
                                const infoName = document.getElementById('infoName');
                                const infoSub = document.getElementById('infoSub');
                                const infoAllergy = document.getElementById('infoAllergy');
                                if (infoCard) infoCard.style.display = 'block';
                                if (headerRow) headerRow.style.display = 'flex';
                                if (infoAvatar) infoAvatar.textContent = p.name.substring(0, 2).toUpperCase();
                                if (infoName) infoName.textContent = p.name;
                                if (infoSub) infoSub.textContent = `${p.gender === 'L' ? 'Laki-laki' : 'Perempuan'} \u00b7 ${p.age} tahun`;
                                if (infoAllergy) {
                                    if (p.allergy) {
                                        infoAllergy.textContent = '\u26a0 Alergi: ' + p.allergy;
                                        infoAllergy.style.display = 'inline-flex';
                                    } else {
                                        infoAllergy.style.display = 'none';
                                    }
                                }
                            });
                            patientDropdown.appendChild(div);
                        });
                    }
                    patientDropdown.classList.add('open');
                } catch (_) {}
            }, 280);
        });

        document.addEventListener('click', e => {
            if (!patientSearch.contains(e.target)) patientDropdown.classList.remove('open');
        });
    }

    // ---- Character counter for textareas ----
    document.querySelectorAll('textarea[maxlength]').forEach(ta => {
        const counter = document.createElement('div');
        counter.className = 'text-xs text-muted mt-2';
        const update = () => { counter.textContent = `${ta.value.length} / ${ta.maxLength}`; };
        ta.after(counter);
        ta.addEventListener('input', update);
        update();
    });

    // ---- Queue status quick-update via AJAX ----
    // FIX: gunakan BASE_URL yang benar, tidak hardcode /medirek/
    document.querySelectorAll('[data-queue-action]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const queueId = btn.dataset.queueId;
            const action = btn.dataset.queueAction;
            const confirmMsg = btn.dataset.confirm;

            if (confirmMsg && !confirm(confirmMsg)) return;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>';

            try {
                const res = await fetch('/apb/queue_action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ queue_id: parseInt(queueId), action })
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    showToast('error', data.message || 'Gagal memperbarui antrian');
                    btn.disabled = false;
                    btn.innerHTML = action === 'called' ? 'Panggil' : action === 'in_progress' ? 'Mulai' : 'Batal';
                }
            } catch (_) {
                btn.disabled = false;
                showToast('error', 'Terjadi kesalahan jaringan');
            }
        });
    });
});

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;box-shadow:0 4px 20px rgba(0,0,0,.15);';
    toast.innerHTML = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity .4s'; setTimeout(() => toast.remove(), 400); }, 3500);
}
