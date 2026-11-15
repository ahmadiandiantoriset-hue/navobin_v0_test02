/**
 * NATOBIN INTERNATIONAL - Frontend Application Logic
 */

'use strict';

const App = {
    state: {
        activePhase: 'dashboard',
        kpi: {},
        customers: [],
        availableInbounds: [],
        availableProduction: [],
        racks: [],
        currentUser: { id: 1, name: 'Administrator Utama', role: 'admin' },
        currentShift: 'PAGI'
    },

    init() {
        this.bindEvents();
        this.updateClock();
        setInterval(() => this.updateClock(), 1000);
        this.loadCustomers();
        this.loadDashboard();
    },

    bindEvents() {
        // Navigation clicks
        document.querySelectorAll('.nav-item[data-phase]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const phase = item.getAttribute('data-phase');
                this.navigate(phase);
            });
        });

        // Mobile menu toggle
        const toggleBtn = document.getElementById('mobileToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                document.getElementById('sidebar').classList.toggle('open');
            });
        }
    },

    updateClock() {
        const now = new Date();
        const clockEl = document.getElementById('clockDisplay');
        if (clockEl) {
            clockEl.textContent = now.toLocaleDateString('id-ID', {
                weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
            }) + ' ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
        }
    },

    navigate(phase) {
        this.state.activePhase = phase;

        // Update nav UI
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        const activeNav = document.querySelector(`.nav-item[data-phase="${phase}"]`);
        if (activeNav) activeNav.classList.add('active');

        // Update Phase views
        document.querySelectorAll('.phase-view').forEach(el => el.classList.remove('active'));
        const targetView = document.getElementById(`phase-${phase}`);
        if (targetView) targetView.classList.add('active');

        // Close mobile sidebar if open
        const sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.remove('open');

        // Load phase data
        switch (phase) {
            case 'dashboard':
                this.loadDashboard();
                break;
            case 'inbound':
                this.loadInbound();
                break;
            case 'production':
                this.loadProduction();
                break;
            case 'inventory':
                this.loadInventory();
                break;
            case 'outbound':
                this.loadOutbound();
                break;
            case 'invoice':
                this.loadInvoice();
                break;
            case 'audit':
                this.loadAudit();
                break;
        }
    },

    // TOAST NOTIFICATION
    toast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                ${type === 'success' 
                    ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>' 
                    : '<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>'}
            </svg>
            <span>${message}</span>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    },

    async fetchAPI(url, options = {}) {
        try {
            const res = await fetch(url, options);
            const data = await res.json();
            return data;
        } catch (err) {
            console.error('API Error:', err);
            return { success: false, message: 'Gagal menghubungi server' };
        }
    },

    async loadCustomers() {
        const res = await this.fetchAPI('api/inbound.php?action=customers');
        if (res.success) {
            this.state.customers = res.data;
        }
    },

    /* =========================================================================
     * PHASE 1: DASHBOARD
     * ========================================================================= */
    async loadDashboard() {
        const res = await this.fetchAPI('api/dashboard.php');
        if (!res.success) return;

        const d = res.data;
        this.state.kpi = d.kpi;
        this.state.currentShift = d.kpi.active_shift;

        // Shift indicator
        const shiftDot = document.getElementById('shiftIndicatorText');
        if (shiftDot) shiftDot.textContent = `Shift ${d.kpi.active_shift} Aktif`;

        // Metrics
        document.getElementById('kpiInboundKg').textContent = Number(d.kpi.total_inbound_kg).toLocaleString('id-ID') + ' KG';
        document.getElementById('kpiInboundSak').textContent = `${Number(d.kpi.total_inbound_sak).toLocaleString('id-ID')} Sak (${d.kpi.total_inbound_batches} Batch LPB)`;

        document.getElementById('kpiProcessKg').textContent = Number(d.kpi.total_input_kg).toLocaleString('id-ID') + ' KG';
        document.getElementById('kpiProcessSak').textContent = `${Number(d.kpi.total_output_sak).toLocaleString('id-ID')} Sak Terolah (${Number(d.kpi.total_output_kg).toLocaleString('id-ID')} KG Output)`;

        document.getElementById('kpiAvgYield').textContent = Number(d.kpi.avg_yield_percentage).toFixed(2) + '%';
        document.getElementById('kpiShrinkageLoss').textContent = `Total Susut: ${Number(d.kpi.total_shrinkage_kg).toLocaleString('id-ID')} KG`;

        document.getElementById('kpiStockSak').textContent = Number(d.kpi.total_stok_sak).toLocaleString('id-ID') + ' Sak';
        document.getElementById('kpiStockKg').textContent = `Tersimpan: ${Number(d.kpi.total_stok_kg).toLocaleString('id-ID')} KG di Gudang`;

        // Render Recent Activity Feeds
        const activityBody = document.getElementById('recentActivityFeed');
        if (activityBody) {
            if (d.recent_audit.length === 0) {
                activityBody.innerHTML = '<div style="color: var(--text-muted); font-size: 12px; padding: 12px;">Belum ada aktivitas tercatat.</div>';
            } else {
                activityBody.innerHTML = d.recent_audit.map(log => `
                    <div style="display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-subtle); font-size: 12px;">
                        <span class="badge ${log.aksi === 'CREATE' ? 'badge-emerald' : 'badge-blue'}" style="height: fit-content;">${log.aksi}</span>
                        <div style="flex: 1;">
                            <div style="color: var(--text-primary); font-weight: 500;">${this.escapeHtml(log.keterangan_detail || log.aksi)}</div>
                            <div style="color: var(--text-muted); font-size: 11px; margin-top: 2px;">
                                ${this.escapeHtml(log.nama_user)} • ${new Date(log.waktu).toLocaleTimeString('id-ID')} • ${log.ip_address}
                            </div>
                        </div>
                    </div>
                `).join('');
            }
        }

        // Render Recent Batches in Dashboard Table
        const recentBatchesBody = document.getElementById('dashboardBatchesTable');
        if (recentBatchesBody) {
            recentBatchesBody.innerHTML = d.recent_batches.map(b => `
                <tr>
                    <td><strong class="font-mono text-cyan">${b.kode_produksi}</strong></td>
                    <td>Shift ${b.shift_kerja}</td>
                    <td>${Number(b.total_input_kg).toLocaleString('id-ID')} KG</td>
                    <td>${Number(b.jumlah_sak_hasil).toLocaleString('id-ID')} Sak (${Number(b.berat_hasil_kg).toLocaleString('id-ID')} KG)</td>
                    <td>
                        <span class="badge ${b.yield_percentage >= 90 ? 'badge-emerald' : 'badge-amber'}">
                            ${Number(b.yield_percentage).toFixed(1)}%
                        </span>
                    </td>
                    <td><span style="color: var(--accent-red); font-weight: 600;">-${Number(b.berat_susut_kg).toLocaleString('id-ID')} KG</span></td>
                    <td><span class="badge badge-purple">${b.storage_location || 'G1-B01'}</span></td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick="App.openPrintSPK(${b.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                            SPK
                        </button>
                    </td>
                </tr>
            `).join('');
        }
    },

    /* =========================================================================
     * PHASE 2: INBOUND (LPB)
     * ========================================================================= */
    async loadInbound() {
        const res = await this.fetchAPI('api/inbound.php');
        if (!res.success) return;

        const tableBody = document.getElementById('inboundTableBody');
        if (!tableBody) return;

        if (res.data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada data barang masuk. Klik "+ Tambah LPB Baru" untuk input.</td></tr>';
            return;
        }

        tableBody.innerHTML = res.data.map(item => {
            const percentUsed = item.berat_bruto_kg > 0 ? Math.min(100, Math.round((item.used_kg / item.berat_bruto_kg) * 100)) : 0;
            let statusBadge = 'badge-blue';
            if (item.status_lpb === 'SELESAI_RAJANG') statusBadge = 'badge-emerald';
            if (item.status_lpb === 'SEDANG_DIRAJANG') statusBadge = 'badge-amber';

            return `
                <tr>
                    <td><strong class="font-mono text-cyan">${this.escapeHtml(item.no_badge)}</strong></td>
                    <td class="strong">${this.escapeHtml(item.nama_customer)}</td>
                    <td>${this.escapeHtml(item.jenis_tembakau)}</td>
                    <td>${new Date(item.tanggal_masuk).toLocaleDateString('id-ID')}</td>
                    <td><strong>${Number(item.berat_bruto_kg).toLocaleString('id-ID')} KG</strong> (${item.jumlah_kemasan} ${item.tipe_kemasan})</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong style="color: ${item.remaining_kg > 0 ? 'var(--accent-emerald)' : 'var(--text-muted)'};">
                                ${Number(item.remaining_kg).toLocaleString('id-ID')} KG
                            </strong>
                            <small style="color: var(--text-muted);">(${item.remaining_sak} Sak)</small>
                        </div>
                        <div style="height: 4px; background: #1e293b; border-radius: 2px; margin-top: 4px; width: 100px;">
                            <div style="height: 100%; width: ${percentUsed}%; background: var(--accent-amber); border-radius: 2px;"></div>
                        </div>
                    </td>
                    <td><span class="badge ${statusBadge}">${item.status_lpb.replace('_', ' ')}</span></td>
                    <td>${this.escapeHtml(item.plat_nomor_truk || '-')}</td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick="App.openPrintLPB(${item.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                            Cetak LPB
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    },

    async openNewInboundModal() {
        // Suggest code
        const codeRes = await this.fetchAPI('api/inbound.php?action=suggest_code');
        document.getElementById('inboundFormCode').value = codeRes.success ? codeRes.data.code : 'IN-' + Date.now();
        document.getElementById('inboundFormDate').value = new Date().toISOString().slice(0, 16);

        // Populate customers dropdown
        const custSelect = document.getElementById('inboundFormCustomer');
        custSelect.innerHTML = '<option value="">-- Pilih Klien / Customer --</option>' +
            this.state.customers.map(c => `<option value="${c.id}">${this.escapeHtml(c.nama_customer)} (${c.kode_customer})</option>`).join('');

        this.openModal('modalInbound');
    },

    async submitInboundForm(e) {
        e.preventDefault();
        const form = document.getElementById('inboundForm');
        const formData = new FormData(form);

        const res = await fetch('api/inbound.php', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();

        if (result.success) {
            this.toast(result.message, 'success');
            this.closeModal('modalInbound');
            form.reset();
            this.loadInbound();
            this.loadDashboard();
        } else {
            this.toast(result.message || 'Gagal menyimpan data', 'error');
        }
    },

    /* =========================================================================
     * PHASE 3: PRODUKSI RAJANG & YIELD/SHRINKAGE
     * ========================================================================= */
    async loadProduction() {
        const res = await this.fetchAPI('api/production.php');
        if (!res.success) return;

        const tableBody = document.getElementById('productionTableBody');
        if (!tableBody) return;

        if (res.data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada batch produksi rajang. Klik "+ Buat Batch Produksi" untuk memulai proses rajang.</td></tr>';
            return;
        }

        tableBody.innerHTML = res.data.map(b => `
            <tr>
                <td><strong class="font-mono text-cyan">${this.escapeHtml(b.kode_produksi)}</strong></td>
                <td>${new Date(b.tanggal_produksi).toLocaleDateString('id-ID')}</td>
                <td><span class="badge ${b.shift_kerja === 'PAGI' ? 'badge-blue' : 'badge-amber'}">Shift ${b.shift_kerja}</span></td>
                <td><strong>${Number(b.total_input_kg).toLocaleString('id-ID')} KG</strong></td>
                <td>
                    <strong>${Number(b.jumlah_sak_hasil).toLocaleString('id-ID')} Sak</strong>
                    <div style="color: var(--text-muted); font-size: 11px;">(${Number(b.berat_hasil_kg).toLocaleString('id-ID')} KG • @${Number(b.target_bobot_sak_kg).toFixed(0)} KG)</div>
                </td>
                <td>
                    <span class="badge ${b.yield_percentage >= 90 ? 'badge-emerald' : (b.yield_percentage >= 85 ? 'badge-amber' : 'badge-red')}">
                        ${Number(b.yield_percentage).toFixed(2)}%
                    </span>
                </td>
                <td>
                    <span style="color: var(--accent-red); font-weight: 700;">-${Number(b.berat_susut_kg).toLocaleString('id-ID')} KG</span>
                </td>
                <td>
                    <span class="badge badge-purple">${this.escapeHtml(b.storage_location || 'G1-B01')}</span>
                    <div style="font-size: 10.5px; color: #0284c7; margin-top: 2px;">${b.kepemilikan_sak}</div>
                </td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick="App.openPrintSPK(${b.id})">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        SPK
                    </button>
                </td>
            </tr>
        `).join('');
    },

    async openNewProductionModal() {
        // Suggest batch code
        const codeRes = await this.fetchAPI('api/production.php?action=suggest_code');
        document.getElementById('prodBatchCode').value = codeRes.success ? codeRes.data.code : 'PRD-' + Date.now();
        document.getElementById('prodDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('prodShift').value = this.state.currentShift;

        // Fetch available inbounds with remaining KG
        const inbRes = await this.fetchAPI('api/production.php?action=available_inbounds');
        this.state.availableInbounds = inbRes.success ? inbRes.data : [];

        // Render Inbound Source Rows with checkboxes
        const inbContainer = document.getElementById('prodInboundRows');
        if (this.state.availableInbounds.length === 0) {
            inbContainer.innerHTML = '<div style="color: var(--accent-red); font-size: 12px; padding: 10px;">Tidak ada stok bahan masuk LPB yang tersedia. Silakan input LPB baru terlebih dahulu.</div>';
        } else {
            inbContainer.innerHTML = this.state.availableInbounds.map((item, idx) => `
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 10px; background: #0b0f17; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); margin-bottom: 6px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="chk_inb_${item.id}" value="${item.id}" onchange="App.toggleInboundSelection(${item.id}, ${item.available_kg})" style="width: 16px; height: 16px; accent-color: #ef4444; cursor: pointer;">
                        <label for="chk_inb_${item.id}" style="font-size: 12.5px; cursor: pointer;">
                            <strong class="font-mono text-cyan">${this.escapeHtml(item.no_badge)}</strong>
                            <span style="color: var(--text-secondary); margin-left: 6px;">${this.escapeHtml(item.nama_customer)} (${item.jenis_tembakau})</span>
                            <span class="badge badge-emerald" style="margin-left: 6px;">Tersedia: ${Number(item.available_kg).toLocaleString('id-ID')} KG</span>
                        </label>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 11px; color: var(--text-muted);">Gunakan:</span>
                        <input type="number" id="input_kg_${item.id}" step="0.5" min="0" max="${item.available_kg}" placeholder="0 KG" disabled 
                               oninput="App.calculateProductionMetrics()" 
                               class="form-control" style="width: 110px; padding: 4px 8px; font-size: 12px; text-align: right;">
                    </div>
                </div>
            `).join('');
        }

        // Reset workers table to 3 default rows
        this.resetWorkerRows();

        // Calculate initially
        this.calculateProductionMetrics();

        this.openModal('modalProduction');
    },

    toggleInboundSelection(id, maxAvailable) {
        const chk = document.getElementById(`chk_inb_${id}`);
        const input = document.getElementById(`input_kg_${id}`);
        if (!chk || !input) return;

        if (chk.checked) {
            input.disabled = false;
            if (!input.value || Number(input.value) === 0) {
                input.value = maxAvailable; // auto-fill max
            }
        } else {
            input.disabled = true;
            input.value = '';
        }
        this.calculateProductionMetrics();
    },

    resetWorkerRows() {
        const container = document.getElementById('workerRows');
        container.innerHTML = `
            <div class="worker-row" style="display: flex; gap: 8px; margin-bottom: 6px;">
                <input type="text" placeholder="Nama Pekerja" value="Sahrul" class="form-control worker-name" style="flex: 2; padding: 6px 10px; font-size: 12px;">
                <select class="form-control worker-role" style="flex: 1.2; padding: 6px 10px; font-size: 12px;">
                    <option value="RAJANG" selected>RAJANG</option>
                    <option value="BANGUNAN">BANGUNAN</option>
                    <option value="LAINNYA">LAINNYA</option>
                </select>
                <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()" style="padding: 4px 8px;">✕</button>
            </div>
            <div class="worker-row" style="display: flex; gap: 8px; margin-bottom: 6px;">
                <input type="text" placeholder="Nama Pekerja" value="B. Amsor" class="form-control worker-name" style="flex: 2; padding: 6px 10px; font-size: 12px;">
                <select class="form-control worker-role" style="flex: 1.2; padding: 6px 10px; font-size: 12px;">
                    <option value="RAJANG">RAJANG</option>
                    <option value="BANGUNAN" selected>BANGUNAN</option>
                    <option value="LAINNYA">LAINNYA</option>
                </select>
                <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()" style="padding: 4px 8px;">✕</button>
            </div>
            <div class="worker-row" style="display: flex; gap: 8px; margin-bottom: 6px;">
                <input type="text" placeholder="Nama Pekerja" value="Sugik" class="form-control worker-name" style="flex: 2; padding: 6px 10px; font-size: 12px;">
                <select class="form-control worker-role" style="flex: 1.2; padding: 6px 10px; font-size: 12px;">
                    <option value="RAJANG" selected>RAJANG</option>
                    <option value="BANGUNAN">BANGUNAN</option>
                    <option value="LAINNYA">LAINNYA</option>
                </select>
                <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()" style="padding: 4px 8px;">✕</button>
            </div>
        `;
    },

    addWorkerRow() {
        const container = document.getElementById('workerRows');
        const row = document.createElement('div');
        row.className = 'worker-row';
        row.style.cssText = 'display: flex; gap: 8px; margin-bottom: 6px;';
        row.innerHTML = `
            <input type="text" placeholder="Nama Pekerja" class="form-control worker-name" style="flex: 2; padding: 6px 10px; font-size: 12px;">
            <select class="form-control worker-role" style="flex: 1.2; padding: 6px 10px; font-size: 12px;">
                <option value="RAJANG">RAJANG</option>
                <option value="BANGUNAN">BANGUNAN</option>
                <option value="LAINNYA">LAINNYA</option>
            </select>
            <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()" style="padding: 4px 8px;">✕</button>
        `;
        container.appendChild(row);
    },

    /**
     * CRUCIAL LIVE CALCULATION FOR SHRINKAGE & YIELD
     * Total Input KG = SUM(Bahan Baku Digunakan)
     * Total Output KG = Jumlah Sak * Target KG/Sak
     * Yield (%) = (Total Output / Total Input) * 100
     * Susut / Loss (KG) = Total Input - Total Output
     */
    calculateProductionMetrics() {
        let totalInputKg = 0;
        this.state.availableInbounds.forEach(item => {
            const chk = document.getElementById(`chk_inb_${item.id}`);
            const input = document.getElementById(`input_kg_${item.id}`);
            if (chk && chk.checked && input) {
                totalInputKg += Number(input.value) || 0;
            }
        });

        const jumlahSak = Number(document.getElementById('prodOutputSak').value) || 0;
        const targetPerSak = Number(document.getElementById('prodTargetPerSak').value) || 25;

        const totalOutputKg = Math.round(jumlahSak * targetPerSak * 100) / 100;
        const shrinkageKg = Math.round((totalInputKg - totalOutputKg) * 100) / 100;
        const yieldPercentage = totalInputKg > 0 ? Math.round((totalOutputKg / totalInputKg) * 10000) / 100 : 0;

        // Update displays
        document.getElementById('calcInputKgDisplay').textContent = totalInputKg.toLocaleString('id-ID') + ' KG';
        document.getElementById('calcOutputKgDisplay').textContent = totalOutputKg.toLocaleString('id-ID') + ' KG';
        document.getElementById('calcYieldDisplay').textContent = yieldPercentage.toFixed(2) + '%';
        document.getElementById('calcShrinkDisplay').textContent = shrinkageKg.toLocaleString('id-ID') + ' KG';

        // Update meter bar
        const meterFill = document.getElementById('yieldMeterFill');
        const meterAlert = document.getElementById('yieldAlertNote');
        const clampedYield = Math.min(100, Math.max(0, yieldPercentage));
        meterFill.style.width = clampedYield + '%';

        if (totalInputKg === 0) {
            meterAlert.innerHTML = '<span style="color: var(--text-muted);">Pilih bahan baku inbound untuk mulai kalkulasi.</span>';
        } else if (yieldPercentage >= 90) {
            meterAlert.innerHTML = '<span style="color: var(--accent-emerald); font-weight: 700;">✓ Rendemen Optimal (≥ 90%) - Efisiensi Sangat Baik</span>';
        } else if (yieldPercentage >= 85) {
            meterAlert.innerHTML = '<span style="color: var(--accent-amber); font-weight: 700;">⚠ Rendemen Wajar (85% - 90%) - Periksa kelembaban tembakau</span>';
        } else {
            meterAlert.innerHTML = `<span style="color: var(--accent-red); font-weight: 700;">🚨 Peringatan Susut Tinggi! Rendemen ${yieldPercentage.toFixed(1)}% (Susut ${shrinkageKg} KG)</span>`;
        }
    },

    async submitProductionForm(e) {
        e.preventDefault();

        // Collect Inbound Sources
        const inbound_sources = [];
        this.state.availableInbounds.forEach(item => {
            const chk = document.getElementById(`chk_inb_${item.id}`);
            const input = document.getElementById(`input_kg_${item.id}`);
            if (chk && chk.checked && input) {
                const used = Number(input.value);
                if (used > 0) {
                    inbound_sources.push({ inbound_id: item.id, used_kg: used });
                }
            }
        });

        if (inbound_sources.length === 0) {
            this.toast('Pilih minimal 1 Bahan Baku Inbound dan isi bobot KG yang ditarik', 'error');
            return;
        }

        // Collect Workers
        const workers = [];
        document.querySelectorAll('.worker-row').forEach(row => {
            const name = row.querySelector('.worker-name').value.trim();
            const role = row.querySelector('.worker-role').value;
            if (name !== '') {
                workers.push({ worker_name: name, role_type: role });
            }
        });

        const payload = {
            kode_produksi: document.getElementById('prodBatchCode').value,
            tanggal_produksi: document.getElementById('prodDate').value,
            shift_kerja: document.getElementById('prodShift').value,
            target_bobot_sak_kg: Number(document.getElementById('prodTargetPerSak').value),
            jumlah_sak_hasil: Number(document.getElementById('prodOutputSak').value),
            ukuran_cutting_mm: Number(document.getElementById('prodCuttingSize').value),
            kepemilikan_sak: document.getElementById('prodOwnership').value,
            storage_location: document.getElementById('prodStorageLocation').value,
            keterangan_operasional: document.getElementById('prodNotes').value,
            inbound_sources: inbound_sources,
            workers: workers
        };

        const res = await this.fetchAPI('api/production.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (res.success) {
            this.toast(res.message, 'success');
            this.closeModal('modalProduction');
            this.loadProduction();
            this.loadDashboard();
        } else {
            this.toast(res.message || 'Gagal memproses batch produksi', 'error');
        }
    },

    /* =========================================================================
     * PHASE 4: INVENTORY & STORAGE BINNING
     * ========================================================================= */
    async loadInventory() {
        const res = await this.fetchAPI('api/inventory.php');
        const overviewRes = await this.fetchAPI('api/inventory.php?action=racks_overview');

        if (overviewRes.success) {
            const rackGrid = document.getElementById('warehouseRackGrid');
            if (rackGrid) {
                rackGrid.innerHTML = overviewRes.data.racks.map(r => {
                    const capacity = Number(r.kapasitas_sak) || 100;
                    const used = Number(r.total_sak_tersisa) || 0;
                    const percent = Math.min(100, Math.round((used / capacity) * 100));

                    return `
                        <div class="rack-card">
                            <div class="rack-card-header">
                                <span class="rack-card-code">${this.escapeHtml(r.kode_rak)}</span>
                                <span class="badge ${percent > 85 ? 'badge-red' : (percent > 50 ? 'badge-amber' : 'badge-emerald')}">
                                    ${percent}% Terisi
                                </span>
                            </div>
                            <div style="font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 2px;">
                                ${this.escapeHtml(r.nama_blok)}
                            </div>
                            <div class="rack-progress">
                                <div class="rack-progress-bar" style="width: ${percent}%; background: ${percent > 85 ? '#ef4444' : '#3b82f6'};"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted);">
                                <span>${used} / ${capacity} Sak</span>
                                <span>${Number(r.total_kg_tersisa).toLocaleString('id-ID')} KG</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        if (res.success) {
            const lotTable = document.getElementById('inventoryTableBody');
            if (lotTable) {
                if (res.data.length === 0) {
                    lotTable.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">Tidak ada stok aktif di gudang.</td></tr>';
                    return;
                }

                lotTable.innerHTML = res.data.map(lot => `
                    <tr>
                        <td><strong class="font-mono text-cyan">${this.escapeHtml(lot.kode_rak)}</strong></td>
                        <td><strong>${this.escapeHtml(lot.kode_produksi)}</strong></td>
                        <td>${this.escapeHtml(lot.nama_customer || 'Internal')}</td>
                        <td>${this.escapeHtml(lot.jenis_tembakau || 'Tembakau Rajang')}</td>
                        <td>
                            <strong>${Number(lot.jumlah_sak_tersisa).toLocaleString('id-ID')} Sak</strong>
                            <small style="color: var(--text-muted);">/ ${lot.jumlah_sak_masuk} Sak Masuk</small>
                        </td>
                        <td><strong>${Number(lot.berat_tersisa_kg).toLocaleString('id-ID')} KG</strong></td>
                        <td>
                            <span class="badge ${lot.status_stok === 'TERSEDIA' ? 'badge-emerald' : (lot.status_stok === 'SEBAGIAN_TERKIRIM' ? 'badge-amber' : 'badge-red')}">
                                ${lot.status_stok.replace('_', ' ')}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-purple">${this.escapeHtml(lot.storage_location || lot.kode_rak)}</span>
                        </td>
                    </tr>
                `).join('');
            }
        }
    },

    /* =========================================================================
     * PHASE 4B: OUTBOUND (DELIVERY ORDER / SURAT JALAN)
     * ========================================================================= */
    async loadOutbound() {
        const res = await this.fetchAPI('api/outbound.php');
        if (!res.success) return;

        const tableBody = document.getElementById('outboundTableBody');
        if (!tableBody) return;

        if (res.data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada Surat Jalan Pengiriman. Klik "+ Buat Surat Jalan Baru" untuk membuat pengiriman.</td></tr>';
            return;
        }

        tableBody.innerHTML = res.data.map(d => `
            <tr>
                <td><strong class="font-mono text-cyan">${this.escapeHtml(d.no_surat_jalan)}</strong></td>
                <td>${new Date(d.tanggal_kirim).toLocaleDateString('id-ID')}</td>
                <td class="strong">${this.escapeHtml(d.nama_customer)}</td>
                <td>${this.escapeHtml(d.plat_armada)} • ${this.escapeHtml(d.nama_sopir)}</td>
                <td><strong>${Number(d.jumlah_sak).toLocaleString('id-ID')} Sak</strong></td>
                <td><strong>${Number(d.total_berat_kg).toLocaleString('id-ID')} KG</strong></td>
                <td>
                    <span class="badge ${d.status_pengiriman === 'DITERIMA' ? 'badge-emerald' : (d.status_pengiriman === 'DALAM_PERJALANAN' ? 'badge-amber' : 'badge-blue')}">
                        ${d.status_pengiriman.replace('_', ' ')}
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 6px;">
                        <button class="btn btn-secondary btn-sm" onclick="App.openPrintDO(${d.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                            Cetak DO
                        </button>
                        ${!d.invoice_id ? `
                            <button class="btn btn-primary btn-sm" onclick="App.openNewInvoiceForDO(${d.id}, '${d.no_surat_jalan}')">
                                Buat Invoice
                            </button>
                        ` : `
                            <span class="badge badge-emerald">Invoiced</span>
                        `}
                    </div>
                </td>
            </tr>
        `).join('');
    },

    async openNewOutboundModal() {
        const codeRes = await this.fetchAPI('api/outbound.php?action=suggest_code');
        document.getElementById('doFormCode').value = codeRes.success ? codeRes.data.code : 'DO-' + Date.now();
        document.getElementById('doFormDate').value = new Date().toISOString().slice(0, 10);

        // Populate customers dropdown
        const custSelect = document.getElementById('doFormCustomer');
        custSelect.innerHTML = '<option value="">-- Pilih Klien Tujuan --</option>' +
            this.state.customers.map(c => `<option value="${c.id}" data-address="${this.escapeHtml(c.alamat || '')}">${this.escapeHtml(c.nama_customer)}</option>`).join('');

        // Fetch available production batches
        const prodRes = await this.fetchAPI('api/outbound.php?action=available_production');
        this.state.availableProduction = prodRes.success ? prodRes.data : [];

        const prodContainer = document.getElementById('doProductionRows');
        if (this.state.availableProduction.length === 0) {
            prodContainer.innerHTML = '<div style="color: var(--accent-red); font-size: 12px; padding: 10px;">Tidak ada batch produksi siap kirim. Silakan selesaikan produksi rajang terlebih dahulu.</div>';
        } else {
            prodContainer.innerHTML = this.state.availableProduction.map(p => `
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 8px 10px; background: #0b0f17; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); margin-bottom: 6px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="chk_do_${p.production_id}" value="${p.production_id}" onchange="App.toggleDoBatchSelection(${p.production_id}, ${p.available_sak}, ${p.target_bobot_sak_kg})" style="width: 16px; height: 16px; accent-color: #ef4444; cursor: pointer;">
                        <label for="chk_do_${p.production_id}" style="font-size: 12px; cursor: pointer;">
                            <strong class="font-mono text-cyan">${this.escapeHtml(p.kode_produksi)}</strong>
                            <span style="color: var(--text-secondary); margin-left: 4px;">${this.escapeHtml(p.nama_customer || 'Gudang')}</span>
                            <span class="badge badge-emerald" style="margin-left: 6px;">Siap: ${p.available_sak} Sak (${Number(p.available_kg).toLocaleString('id-ID')} KG)</span>
                        </label>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <input type="number" id="input_do_sak_${p.production_id}" min="1" max="${p.available_sak}" placeholder="Sak" disabled 
                               oninput="App.calcDoBatchRow(${p.production_id}, ${p.target_bobot_sak_kg})" 
                               class="form-control" style="width: 80px; padding: 4px 6px; font-size: 12px; text-align: right;">
                        <span style="font-size: 11px; color: var(--text-muted);">Sak =</span>
                        <input type="number" id="input_do_kg_${p.production_id}" step="0.5" placeholder="KG" disabled 
                               class="form-control" style="width: 100px; padding: 4px 6px; font-size: 12px; text-align: right;">
                    </div>
                </div>
            `).join('');
        }

        this.openModal('modalOutbound');
    },

    toggleDoBatchSelection(id, maxSak, kgPerSak) {
        const chk = document.getElementById(`chk_do_${id}`);
        const inputSak = document.getElementById(`input_do_sak_${id}`);
        const inputKg = document.getElementById(`input_do_kg_${id}`);

        if (chk.checked) {
            inputSak.disabled = false;
            inputKg.disabled = false;
            if (!inputSak.value) {
                inputSak.value = maxSak;
                inputKg.value = maxSak * kgPerSak;
            }
        } else {
            inputSak.disabled = true;
            inputKg.disabled = true;
            inputSak.value = '';
            inputKg.value = '';
        }
    },

    calcDoBatchRow(id, kgPerSak) {
        const sak = Number(document.getElementById(`input_do_sak_${id}`).value) || 0;
        document.getElementById(`input_do_kg_${id}`).value = sak * kgPerSak;
    },

    onCustomerSelectChange(select) {
        const selectedOpt = select.options[select.selectedIndex];
        const addr = selectedOpt.getAttribute('data-address');
        const destInput = document.getElementById('doFormDestination');
        if (destInput && addr) {
            destInput.value = addr;
        }
    },

    async submitOutboundForm(e) {
        e.preventDefault();

        const items = [];
        this.state.availableProduction.forEach(p => {
            const chk = document.getElementById(`chk_do_${p.production_id}`);
            const inputSak = document.getElementById(`input_do_sak_${p.production_id}`);
            const inputKg = document.getElementById(`input_do_kg_${p.production_id}`);
            if (chk && chk.checked && inputSak && inputKg) {
                const sak = Number(inputSak.value);
                const kg = Number(inputKg.value);
                if (sak > 0 && kg > 0) {
                    items.push({
                        production_id: p.production_id,
                        delivered_sak: sak,
                        delivered_kg: kg
                    });
                }
            }
        });

        if (items.length === 0) {
            this.toast('Pilih minimal 1 Batch Produksi untuk dikirim', 'error');
            return;
        }

        const payload = {
            no_surat_jalan: document.getElementById('doFormCode').value,
            tanggal_kirim: document.getElementById('doFormDate').value,
            customer_id: Number(document.getElementById('doFormCustomer').value),
            plat_armada: document.getElementById('doFormPlat').value,
            nama_sopir: document.getElementById('doFormDriver').value,
            tujuan_pengiriman: document.getElementById('doFormDestination').value,
            catatan: document.getElementById('doFormNotes').value,
            items: items
        };

        const res = await this.fetchAPI('api/outbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (res.success) {
            this.toast(res.message, 'success');
            this.closeModal('modalOutbound');
            this.loadOutbound();
            this.loadDashboard();
        } else {
            this.toast(res.message || 'Gagal membuat Surat Jalan', 'error');
        }
    },

    /* =========================================================================
     * PHASE 4C: INVOICE & BILLING
     * ========================================================================= */
    async loadInvoice() {
        const res = await this.fetchAPI('api/invoice.php');
        if (!res.success) return;

        const tableBody = document.getElementById('invoiceTableBody');
        if (!tableBody) return;

        if (res.data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada invoice penagihan. Buka menu Pengiriman DO untuk membuat invoice dari pengiriman.</td></tr>';
            return;
        }

        tableBody.innerHTML = res.data.map(inv => `
            <tr>
                <td><strong class="font-mono text-cyan">${this.escapeHtml(inv.no_invoice)}</strong></td>
                <td>${new Date(inv.tanggal_invoice).toLocaleDateString('id-ID')}</td>
                <td class="strong">${this.escapeHtml(inv.nama_customer)}</td>
                <td><span class="badge badge-purple">${this.escapeHtml(inv.no_surat_jalan)}</span></td>
                <td>${Number(inv.total_berat_kg).toLocaleString('id-ID')} KG (${inv.jumlah_sak} Sak)</td>
                <td><strong>Rp ${Number(inv.total_tagihan).toLocaleString('id-ID')}</strong></td>
                <td>
                    <span class="badge ${inv.status_pembayaran === 'LUNAS' ? 'badge-emerald' : 'badge-amber'}">
                        ${inv.status_pembayaran}
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 6px;">
                        <button class="btn btn-secondary btn-sm" onclick="App.openPrintInvoice(${inv.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                            Faktur
                        </button>
                        ${inv.status_pembayaran !== 'LUNAS' ? `
                            <button class="btn btn-emerald btn-sm" onclick="App.markInvoicePaid(${inv.id})">
                                Lunas
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    },

    async openNewInvoiceForDO(doId, doCode) {
        const codeRes = await this.fetchAPI('api/invoice.php?action=suggest_code');
        document.getElementById('invFormCode').value = codeRes.success ? codeRes.data.code : 'INV-' + Date.now();
        document.getElementById('invFormDoId').value = doId;
        document.getElementById('invFormDoRef').textContent = doCode;
        document.getElementById('invFormDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('invFormDueDate').value = new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10);

        this.openModal('modalInvoice');
    },

    async submitInvoiceForm(e) {
        e.preventDefault();
        const payload = {
            no_invoice: document.getElementById('invFormCode').value,
            pengiriman_id: Number(document.getElementById('invFormDoId').value),
            tanggal_invoice: document.getElementById('invFormDate').value,
            jatuh_tempo: document.getElementById('invFormDueDate').value,
            metode_tarif: document.getElementById('invFormRateMethod').value,
            tarif_satuan: Number(document.getElementById('invFormRate').value),
            tarif_sak_sendiri: Number(document.getElementById('invFormSackRate').value),
            biaya_bongkar_muat: Number(document.getElementById('invFormHandling').value) || 0,
            biaya_lain: Number(document.getElementById('invFormOtherCost').value) || 0,
            include_ppn: document.getElementById('invFormPpn').checked
        };

        const res = await this.fetchAPI('api/invoice.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (res.success) {
            this.toast(res.message, 'success');
            this.closeModal('modalInvoice');
            this.loadInvoice();
            this.loadOutbound();
        } else {
            this.toast(res.message || 'Gagal menerbitkan invoice', 'error');
        }
    },

    async markInvoicePaid(id) {
        if (!confirm('Tandai tagihan invoice ini sebagai LUNAS?')) return;

        const res = await this.fetchAPI('api/invoice.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, status_pembayaran: 'LUNAS' })
        });

        if (res.success) {
            this.toast(res.message, 'success');
            this.loadInvoice();
        } else {
            this.toast(res.message, 'error');
        }
    },

    /* =========================================================================
     * PHASE 5: AUDIT TRAIL
     * ========================================================================= */
    async loadAudit() {
        const entity = document.getElementById('auditEntityFilter')?.value || '';
        const action = document.getElementById('auditActionFilter')?.value || '';
        const res = await this.fetchAPI(`api/audit.php?entity=${encodeURIComponent(entity)}&action=${encodeURIComponent(action)}`);
        if (!res.success) return;

        const tableBody = document.getElementById('auditTableBody');
        if (!tableBody) return;

        if (res.data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">Tidak ada catatan audit yang cocok.</td></tr>';
            return;
        }

        tableBody.innerHTML = res.data.map(log => `
            <tr>
                <td style="font-family: var(--font-mono); font-size: 11px;">#${log.id}</td>
                <td>${new Date(log.waktu).toLocaleString('id-ID')}</td>
                <td>
                    <strong>${this.escapeHtml(log.nama_user)}</strong>
                    <div style="font-size: 11px; color: var(--text-muted);">${log.role}</div>
                </td>
                <td><span class="badge ${log.aksi === 'CREATE' ? 'badge-emerald' : (log.aksi === 'UPDATE' ? 'badge-amber' : 'badge-red')}">${log.aksi}</span></td>
                <td><span class="badge badge-purple">${this.escapeHtml(log.entity || 'SYSTEM')}</span></td>
                <td>${this.escapeHtml(log.keterangan_detail || '-')}</td>
                <td style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted);">${log.ip_address}</td>
            </tr>
        `).join('');
    },

    /* =========================================================================
     * PRINT HELPERS
     * ========================================================================= */
    openPrintLPB(id) {
        window.open(`print/lpb.php?id=${id}`, '_blank');
    },

    openPrintSPK(id) {
        window.open(`print/spk.php?id=${id}`, '_blank');
    },

    openPrintDO(id) {
        window.open(`print/surat_jalan.php?id=${id}`, '_blank');
    },

    openPrintInvoice(id) {
        window.open(`print/invoice.php?id=${id}`, '_blank');
    },

    /* =========================================================================
     * MODAL CONTROLLERS
     * ========================================================================= */
    openModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.add('active');
    },

    closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove('active');
    },

    escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});
