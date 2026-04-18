import './bootstrap';

const state = {
    token: localStorage.getItem('token') || '',
    me: null,
    activeTab: null,
    modalState: null,
    filters: {
        criteriasCategoryId: '',
        performancesPeriodId: '',
    },
};

const tabs = [
    { key: 'users', label: 'User', description: 'Kelola akun dan role', roles: ['admin'] },
    { key: 'periods', label: 'Periode', description: 'Master periode penilaian', roles: ['admin'] },
    { key: 'categories', label: 'Kategori', description: 'Kelompok kriteria penilaian', roles: ['admin'] },
    { key: 'criterias', label: 'Kriteria', description: 'Detail kriteria dan tipe nilai', roles: ['admin'] },
    { key: 'performances', label: 'Performa', description: 'Input nilai user per periode', roles: ['admin'] },
    { key: 'calculate', label: 'Calculate', description: 'Hitung TOPSIS per periode', roles: ['admin'] },
    { key: 'results', label: 'TOPSIS Result', description: 'Riwayat ranking per periode', roles: ['admin', 'user'] },
    { key: 'account', label: 'Password', description: 'Ubah password akun', roles: ['admin', 'user'] },
];

const authPanel = document.getElementById('auth-panel');
const passwordPanel = document.getElementById('password-panel');
const appPanel = document.getElementById('app-panel');
const navTabs = document.getElementById('nav-tabs');
const contentArea = document.getElementById('content-area');
const sessionInfo = document.getElementById('session-info');
const logoutBtn = document.getElementById('logout-btn');
const pageTitle = document.getElementById('page-title');
const pageBadge = document.getElementById('page-badge');
const toast = document.getElementById('toast');
const modalRoot = document.getElementById('modal-root');
const loginSubmit = document.getElementById('login-submit');
const forcePasswordSubmit = document.getElementById('force-password-submit');
const passwordPanelCopy = document.getElementById('password-panel-copy');

const api = axios.create({ baseURL: '/' });

function setAuthToken(token) {
    state.token = token || '';

    if (state.token) {
        localStorage.setItem('token', state.token);
        api.defaults.headers.common.Authorization = `Bearer ${state.token}`;
    } else {
        localStorage.removeItem('token');
        delete api.defaults.headers.common.Authorization;
    }
}

function showToast(message, isError = false) {
    toast.textContent = message;
    toast.className = `toast ${isError ? 'is-error' : ''}`;
    toast.classList.remove('hidden');

    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => {
        toast.classList.add('hidden');
    }, 2800);
}

function normalizeRows(response) {
    if (Array.isArray(response?.data)) return response.data;
    if (Array.isArray(response?.data?.data)) return response.data.data;
    return [];
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

async function request(config, options = {}) {
    try {
        const response = await api.request(config);
        return response.data;
    } catch (error) {
        if (error.response?.status === 401) {
            handleLogout(false);
            showToast('Sesi berakhir. Silakan login kembali.', true);
            return null;
        }

        if (!options.silent) {
            const message =
                error.response?.data?.message ||
                Object.values(error.response?.data?.errors || {})[0]?.[0] ||
                'Permintaan gagal diproses.';

            showToast(message, true);
        }

        return null;
    }
}

function openModal({ title, description = '', body = '', actions = [] }) {
    state.modalState = true;
    modalRoot.classList.remove('hidden');
    modalRoot.innerHTML = `
        <div class="modal-card">
            <div class="modal-head">
                <div>
                    <h3>${escapeHtml(title)}</h3>
                    <p>${escapeHtml(description)}</p>
                </div>
                <button class="modal-close" type="button" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">${body}</div>
            <div class="modal-actions">
                ${actions
                    .map(
                        (action, index) =>
                            `<button type="${action.type || 'button'}" class="btn ${action.variant || 'btn-ghost'}" data-modal-action="${index}">${escapeHtml(action.label)}</button>`
                    )
                    .join('')}
            </div>
        </div>
    `;

    modalRoot.querySelector('[data-modal-close]')?.addEventListener('click', closeModal);
    modalRoot.addEventListener('click', handleBackdropClick);
    actions.forEach((action, index) => {
        modalRoot.querySelector(`[data-modal-action="${index}"]`)?.addEventListener('click', action.onClick);
    });
}

function closeModal() {
    state.modalState = null;
    modalRoot.classList.add('hidden');
    modalRoot.innerHTML = '';
    modalRoot.removeEventListener('click', handleBackdropClick);
}

function handleBackdropClick(event) {
    if (event.target === modalRoot) closeModal();
}

function showConfirmModal({ title, description, confirmLabel = 'Lanjutkan', onConfirm, variant = 'btn-primary' }) {
    openModal({
        title,
        description,
        body: `<p class="confirm-copy">${escapeHtml(description)}</p>`,
        actions: [
            { label: 'Batal', variant: 'btn-ghost', onClick: closeModal },
            {
                label: confirmLabel,
                variant,
                onClick: async (event) => {
                    const confirmButton = event.currentTarget;
                    const loadingLabel = `${confirmLabel}...`;

                    setModalLoading(true, loadingLabel);
                    setButtonBusy(confirmButton, true, confirmLabel, loadingLabel);

                    try {
                        await onConfirm();
                    } finally {
                        if (state.modalState) {
                            setModalLoading(false);
                            setButtonBusy(confirmButton, false, confirmLabel, loadingLabel);
                        }
                    }
                },
            },
        ],
    });
}

function setViewMode(mode) {
    authPanel.classList.toggle('hidden', mode !== 'login');
    passwordPanel.classList.toggle('hidden', mode !== 'force-password');
    appPanel.classList.toggle('hidden', mode !== 'dashboard');
}

function getAllowedTabs() {
    return tabs.filter((tab) => tab.roles.includes(state.me.role));
}

function getTabLabel(key) {
    return tabs.find((tab) => tab.key === key)?.label || 'Dashboard';
}

function setPageHeader(title, badge) {
    pageTitle.textContent = title;
    pageBadge.textContent = badge;
}

function setButtonBusy(button, isBusy, idleLabel, busyLabel = 'Memproses...') {
    if (!button) return;

    button.disabled = isBusy;
    button.classList.toggle('is-busy', isBusy);
    button.dataset.idleLabel = idleLabel;
    button.dataset.busyLabel = busyLabel;
    button.innerHTML = isBusy
        ? `<span class="btn-spinner" aria-hidden="true"></span><span>${escapeHtml(busyLabel)}</span>`
        : `<span>${escapeHtml(idleLabel)}</span>`;
}

function setModalLoading(isLoading, loadingText = 'Memproses data...') {
    const modalCard = modalRoot.querySelector('.modal-card');
    const modalCloseButton = modalRoot.querySelector('[data-modal-close]');

    if (!modalCard) return;

    modalCard.classList.toggle('is-loading', isLoading);
    modalCard.dataset.loadingText = loadingText;
    modalCloseButton?.toggleAttribute('disabled', isLoading);

    modalRoot.querySelectorAll('[data-modal-action]').forEach((button) => {
        if (button !== modalCloseButton) {
            button.disabled = isLoading;
        }
    });
}

function renderTabs() {
    const allowed = getAllowedTabs();

    navTabs.innerHTML = allowed
        .map(
            (tab) => `
                <button class="nav-item ${state.activeTab === tab.key ? 'is-active' : ''}" data-tab="${tab.key}">
                    ${escapeHtml(tab.label)}
                    <small>${escapeHtml(tab.description)}</small>
                </button>
            `
        )
        .join('');

    navTabs.querySelectorAll('[data-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            state.activeTab = button.getAttribute('data-tab');
            renderTabs();
            renderActiveTab();
        });
    });
}

function cardMetrics(items) {
    return `
        <div class="metrics-grid">
            ${items
                .map(
                    (item) => `
                        <article class="metric-card">
                            <span class="metric-card__label">${escapeHtml(item.label)}</span>
                            <strong class="metric-card__value">${escapeHtml(item.value)}</strong>
                        </article>
                    `
                )
                .join('')}
        </div>
    `;
}

function renderTable(columns, rows, emptyMessage = 'Data belum tersedia.') {
    if (!rows.length) {
        return `<div class="empty-state">${escapeHtml(emptyMessage)}</div>`;
    }

    return `
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>${columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('')}</tr>
                </thead>
                <tbody>${rows.join('')}</tbody>
            </table>
        </div>
    `;
}

function buildSection({ title, description, actions = '', metrics = '', content = '' }) {
    contentArea.innerHTML = `
        <div class="section-head">
            <div>
                <h2>${escapeHtml(title)}</h2>
                <p>${escapeHtml(description)}</p>
            </div>
            <div>${actions}</div>
        </div>
        ${metrics}
        ${content}
    `;
}

function createLabeledInput({ label, name, value = '', type = 'text', required = true, min = '', step = '' }) {
    return `
        <label class="field">
            <span>${escapeHtml(label)}</span>
            <input name="${escapeHtml(name)}" type="${escapeHtml(type)}" value="${escapeHtml(value)}" ${required ? 'required' : ''} ${min !== '' ? `min="${escapeHtml(min)}"` : ''} ${step !== '' ? `step="${escapeHtml(step)}"` : ''}>
        </label>
    `;
}

function createLabeledSelect({ label, name, options, value = '', required = true }) {
    return `
        <label class="field">
            <span>${escapeHtml(label)}</span>
            <select name="${escapeHtml(name)}" ${required ? 'required' : ''}>
                ${options
                    .map((option) => {
                        const selected = String(option.value) === String(value) ? 'selected' : '';
                        return `<option value="${escapeHtml(option.value)}" ${selected}>${escapeHtml(option.label)}</option>`;
                    })
                    .join('')}
            </select>
        </label>
    `;
}

function createFilterToolbar(fields = []) {
    return `
        <div class="toolbar-card">
            <div class="toolbar-row">
                ${fields.join('')}
            </div>
        </div>
    `;
}

async function renderUsersTab() {
    const response = await request({ method: 'get', url: '/api/users' });
    if (!response) return;

    const rows = normalizeRows(response);
    const defaultPassword = response.meta?.default_password || '12345678';

    setPageHeader('Menu User', `${rows.length} data`);
    buildSection({
        title: 'Manajemen User',
        description: 'Admin membuat akun user dengan password default. User yang masih memakai password default akan dipaksa menggantinya saat login pertama.',
        actions: `<button class="btn btn-primary" id="add-user-btn">Tambah User</button>`,
        metrics: cardMetrics([
            { label: 'Total User', value: rows.length },
            { label: 'Admin', value: rows.filter((item) => item.role === 'admin').length },
            { label: 'User', value: rows.filter((item) => item.role === 'user').length },
            { label: 'Perlu Ganti Password', value: rows.filter((item) => item.must_change_password).length },
        ]),
        content: renderTable(
            ['No', 'Nama User', 'Role', 'Status Password', 'Action'],
            rows.map(
                (row, index) => `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <strong>${escapeHtml(row.name)}</strong>
                            <div class="muted">${escapeHtml(row.email)}</div>
                        </td>
                        <td><span class="inline-chip">${escapeHtml(row.role)}</span></td>
                        <td><span class="table-chip ${row.must_change_password ? 'is-open' : 'is-final'}">${row.must_change_password ? 'Wajib Ganti' : 'Sudah Aman'}</span></td>
                        <td>
                            <div class="table-actions">
                                <button class="action-link is-primary" data-user-edit="${row.id}">Edit</button>
                                <button class="action-link" data-user-reset="${row.id}">Reset Password</button>
                                <button class="action-link is-danger" data-user-delete="${row.id}">Hapus</button>
                            </div>
                        </td>
                    </tr>
                `
            ),
            'Belum ada user.'
        ),
    });

    document.getElementById('add-user-btn')?.addEventListener('click', () => {
        openModal({
            title: 'Tambah User Baru',
            description: `Password default user baru adalah ${defaultPassword}.`,
            body: `
                <form id="user-create-form" class="modal-form">
                    ${createLabeledInput({ label: 'Nama User', name: 'name' })}
                    ${createLabeledSelect({
                        label: 'Role',
                        name: 'role',
                        options: [
                            { value: 'user', label: 'User' },
                            { value: 'admin', label: 'Admin' },
                        ],
                    })}
                </form>
            `,
            actions: [
                { label: 'Batal', variant: 'btn-ghost', onClick: closeModal },
                {
                    label: 'Simpan',
                    variant: 'btn-primary',
                    onClick: async () => {
                        const form = document.getElementById('user-create-form');
                        if (!form.reportValidity()) return;
                        const payload = Object.fromEntries(new FormData(form).entries());

                        showConfirmModal({
                            title: 'Simpan User Baru',
                            description: 'Apakah data user baru sudah benar dan ingin disimpan?',
                            confirmLabel: 'Ya, simpan',
                            onConfirm: async () => {
                                const result = await request({ method: 'post', url: '/api/users', data: payload });
                                if (!result) return;
                                closeModal();
                                showToast(`User berhasil ditambahkan. Password default: ${result.meta?.default_password || defaultPassword}`);
                                renderUsersTab();
                            },
                        });
                    },
                },
            ],
        });
    });

    rows.forEach((row) => {
        document.querySelector(`[data-user-edit="${row.id}"]`)?.addEventListener('click', () => {
            openModal({
                title: 'Edit User',
                description: 'Ubah nama user dan role sesuai kebutuhan.',
                body: `
                    <form id="user-edit-form" class="modal-form">
                        ${createLabeledInput({ label: 'Nama User', name: 'name', value: row.name })}
                        ${createLabeledSelect({
                            label: 'Role',
                            name: 'role',
                            value: row.role,
                            options: [
                                { value: 'user', label: 'User' },
                                { value: 'admin', label: 'Admin' },
                            ],
                        })}
                    </form>
                `,
                actions: [
                    { label: 'Batal', variant: 'btn-ghost', onClick: closeModal },
                    {
                        label: 'Update',
                        variant: 'btn-primary',
                        onClick: async () => {
                            const form = document.getElementById('user-edit-form');
                            if (!form.reportValidity()) return;
                            const payload = Object.fromEntries(new FormData(form).entries());

                            showConfirmModal({
                                title: 'Simpan Perubahan User',
                                description: `Apakah perubahan untuk ${row.name} ingin disimpan?`,
                                confirmLabel: 'Ya, update',
                                onConfirm: async () => {
                                    const result = await request({ method: 'patch', url: `/api/users/${row.id}`, data: payload });
                                    if (!result) return;
                                    closeModal();
                                    showToast('Data user berhasil diperbarui.');
                                    renderUsersTab();
                                },
                            });
                        },
                    },
                ],
            });
        });

        document.querySelector(`[data-user-reset="${row.id}"]`)?.addEventListener('click', () => {
            showConfirmModal({
                title: 'Reset Password User',
                description: `Password ${row.name} akan direset ke default ${defaultPassword}.`,
                confirmLabel: 'Ya, reset',
                onConfirm: async () => {
                    const result = await request({ method: 'patch', url: `/api/users/${row.id}/reset-password` });
                    if (!result) return;
                    closeModal();
                    showToast(`Password berhasil direset ke ${result.meta?.default_password || defaultPassword}.`);
                },
            });
        });

        document.querySelector(`[data-user-delete="${row.id}"]`)?.addEventListener('click', () => {
            showConfirmModal({
                title: 'Hapus User',
                description: `Apakah Anda yakin ingin menghapus user ${row.name}?`,
                confirmLabel: 'Ya, hapus',
                variant: 'btn-danger',
                onConfirm: async () => {
                    const result = await request({ method: 'delete', url: `/api/users/${row.id}` });
                    if (!result) return;
                    closeModal();
                    showToast('User berhasil dihapus.');
                    renderUsersTab();
                },
            });
        });
    });
}

function bindSimpleMasterActions(config, rows, renderFn) {
    document.getElementById(config.addButtonId)?.addEventListener('click', () => {
        openModal({
            title: config.createTitle,
            description: config.createDescription,
            body: `
                <form id="${config.formId}" class="modal-form">
                    ${createLabeledInput({ label: config.fieldLabel, name: config.fieldName })}
                </form>
            `,
            actions: [
                { label: 'Batal', variant: 'btn-ghost', onClick: closeModal },
                {
                    label: 'Simpan',
                    variant: 'btn-primary',
                    onClick: async () => {
                        const form = document.getElementById(config.formId);
                        if (!form.reportValidity()) return;
                        const payload = Object.fromEntries(new FormData(form).entries());

                        showConfirmModal({
                            title: config.confirmCreateTitle,
                            description: config.confirmCreateDescription,
                            confirmLabel: 'Ya, simpan',
                            onConfirm: async () => {
                                const result = await request({ method: 'post', url: config.endpoint, data: payload });
                                if (!result) return;
                                closeModal();
                                showToast(config.successCreateMessage);
                                renderFn();
                            },
                        });
                    },
                },
            ],
        });
    });

    rows.forEach((row) => {
        document.querySelector(`[data-edit-id="${row.id}"]`)?.addEventListener('click', () => {
            openModal({
                title: config.editTitle,
                description: config.editDescription,
                body: `
                    <form id="${config.formId}" class="modal-form">
                        ${createLabeledInput({ label: config.fieldLabel, name: config.fieldName, value: row[config.fieldName] })}
                    </form>
                `,
                actions: [
                    { label: 'Batal', variant: 'btn-ghost', onClick: closeModal },
                    {
                        label: 'Update',
                        variant: 'btn-primary',
                        onClick: async () => {
                            const form = document.getElementById(config.formId);
                            if (!form.reportValidity()) return;
                            const payload = Object.fromEntries(new FormData(form).entries());

                            showConfirmModal({
                                title: config.confirmEditTitle,
                                description: `Apakah perubahan ${row[config.fieldName]} ingin disimpan?`,
                                confirmLabel: 'Ya, update',
                                onConfirm: async () => {
                                    const result = await request({ method: 'patch', url: `${config.endpoint}/${row.id}`, data: payload });
                                    if (!result) return;
                                    closeModal();
                                    showToast(config.successEditMessage);
                                    renderFn();
                                },
                            });
                        },
                    },
                ],
            });
        });

        document.querySelector(`[data-delete-id="${row.id}"]`)?.addEventListener('click', () => {
            showConfirmModal({
                title: config.deleteTitle,
                description: `Apakah data ${row[config.fieldName]} ingin dihapus?`,
                confirmLabel: 'Ya, hapus',
                variant: 'btn-danger',
                onConfirm: async () => {
                    const result = await request({ method: 'delete', url: `${config.endpoint}/${row.id}` });
                    if (!result) return;
                    closeModal();
                    showToast(config.successDeleteMessage);
                    renderFn();
                },
            });
        });
    });
}

async function renderPeriodsTab() {
    const response = await request({ method: 'get', url: '/api/periods' });
    if (!response) return;

    const rows = normalizeRows(response);

    setPageHeader('Menu Periode', `${rows.length} periode`);
    buildSection({
        title: 'Master Periode',
        description: 'Tambah, ubah, dan hapus periode penilaian. Periode yang sudah selesai tidak bisa diedit.',
        actions: `<button class="btn btn-primary" id="add-period-btn">Tambah Periode</button>`,
        metrics: cardMetrics([
            { label: 'Total Periode', value: rows.length },
            { label: 'Selesai', value: rows.filter((item) => item.is_finalized).length },
            { label: 'Aktif', value: rows.filter((item) => !item.is_finalized).length },
        ]),
        content: renderTable(
            ['No', 'Nama Periode', 'Status', 'Ringkasan', 'Action'],
            rows.map(
                (row, index) => `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${escapeHtml(row.period_name)}</td>
                        <td><span class="table-chip ${row.is_finalized ? 'is-final' : 'is-open'}">${row.is_finalized ? 'Finished' : 'Open'}</span></td>
                        <td>${row.performances_count || 0} performa, ${row.topsis_results_count || 0} hasil</td>
                        <td>
                            <div class="table-actions">
                                <button class="action-link is-primary" data-edit-id="${row.id}" ${row.is_finalized ? 'disabled' : ''}>Edit</button>
                                <button class="action-link is-danger" data-delete-id="${row.id}">Hapus</button>
                            </div>
                        </td>
                    </tr>
                `
            )
        ),
    });

    bindSimpleMasterActions(
        {
            endpoint: '/api/periods',
            addButtonId: 'add-period-btn',
            formId: 'period-form',
            fieldLabel: 'Nama Periode',
            fieldName: 'period_name',
            createTitle: 'Tambah Periode',
            createDescription: 'Input nama periode baru.',
            confirmCreateTitle: 'Simpan Periode',
            confirmCreateDescription: 'Apakah periode baru ingin disimpan?',
            successCreateMessage: 'Periode berhasil ditambahkan.',
            editTitle: 'Edit Periode',
            editDescription: 'Ubah nama periode.',
            confirmEditTitle: 'Update Periode',
            successEditMessage: 'Periode berhasil diperbarui.',
            deleteTitle: 'Hapus Periode',
            successDeleteMessage: 'Periode berhasil dihapus.',
        },
        rows,
        renderPeriodsTab
    );
}

async function renderCategoriesTab() {
    const response = await request({ method: 'get', url: '/api/categories' });
    if (!response) return;

    const rows = normalizeRows(response);

    setPageHeader('Menu Kategori', `${rows.length} kategori`);
    buildSection({
        title: 'Master Kategori',
        description: 'Kelola kategori penilaian. Tabel menampilkan nomor biasa agar lebih mudah dibaca.',
        actions: `<button class="btn btn-primary" id="add-category-btn">Tambah Kategori</button>`,
        metrics: cardMetrics([
            { label: 'Total Kategori', value: rows.length },
            { label: 'Total Kriteria', value: rows.reduce((sum, item) => sum + (item.criterias_count || 0), 0) },
            { label: 'Kategori Aktif', value: rows.filter((item) => (item.criterias_count || 0) > 0).length },
        ]),
        content: renderTable(
            ['No', 'Nama Kategori', 'Jumlah Kriteria', 'Action'],
            rows.map(
                (row, index) => `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${escapeHtml(row.name)}</td>
                        <td>${row.criterias_count || 0}</td>
                        <td>
                            <div class="table-actions">
                                <button class="action-link is-primary" data-edit-id="${row.id}">Edit</button>
                                <button class="action-link is-danger" data-delete-id="${row.id}">Hapus</button>
                            </div>
                        </td>
                    </tr>
                `
            )
        ),
    });

    bindSimpleMasterActions(
        {
            endpoint: '/api/categories',
            addButtonId: 'add-category-btn',
            formId: 'category-form',
            fieldLabel: 'Nama Kategori',
            fieldName: 'name',
            createTitle: 'Tambah Kategori',
            createDescription: 'Input nama kategori baru.',
            confirmCreateTitle: 'Simpan Kategori',
            confirmCreateDescription: 'Apakah kategori baru ingin disimpan?',
            successCreateMessage: 'Kategori berhasil ditambahkan.',
            editTitle: 'Edit Kategori',
            editDescription: 'Ubah nama kategori.',
            confirmEditTitle: 'Update Kategori',
            successEditMessage: 'Kategori berhasil diperbarui.',
            deleteTitle: 'Hapus Kategori',
            successDeleteMessage: 'Kategori berhasil dihapus.',
        },
        rows,
        renderCategoriesTab
    );
}

async function renderCriteriasTab() {
    const query = state.filters.criteriasCategoryId ? `?category_id=${state.filters.criteriasCategoryId}` : '';
    const response = await request({ method: 'get', url: `/api/criterias${query}` });
    if (!response) return;

    const rows = normalizeRows(response);
    const categories = response.meta?.categories || [];
    const selectedCategoryId = String(response.meta?.selected_category_id || state.filters.criteriasCategoryId || '');
    state.filters.criteriasCategoryId = selectedCategoryId;

    setPageHeader('Menu Kriteria', `${rows.length} kriteria`);
    buildSection({
        title: 'Master Kriteria',
        description: 'Filter berdasarkan kategori agar kriteria lebih cepat dibaca, diedit, dan dikelola.',
        actions: `<button class="btn btn-primary" id="add-criteria-btn">Tambah Kriteria</button>`,
        metrics: cardMetrics([
            { label: 'Total Kriteria', value: rows.length },
            { label: 'Benefit', value: rows.filter((item) => item.type === 'benefit').length },
            { label: 'Cost', value: rows.filter((item) => item.type === 'cost').length },
        ]),
        content:
            createFilterToolbar([
                createLabeledSelect({
                    label: 'Filter Kategori',
                    name: 'criteria_category_filter',
                    value: selectedCategoryId,
                    required: false,
                    options: [
                        { value: '', label: 'Semua kategori' },
                        ...categories.map((item) => ({ value: item.id, label: item.name })),
                    ],
                }),
            ]) +
            renderTable(
                ['No', 'Kategori', 'Nama Kriteria', 'Jenis', 'Bobot', 'Action'],
                rows.map(
                    (row, index) => `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${escapeHtml(row.category?.name || '-')}</td>
                            <td>${escapeHtml(row.name)}</td>
                            <td><span class="inline-chip">${escapeHtml(row.type)}</span></td>
                            <td>${row.weight}</td>
                            <td>
                                <div class="table-actions">
                                    <button class="action-link is-primary" data-criteria-edit="${row.id}">Edit</button>
                                    <button class="action-link is-danger" data-criteria-delete="${row.id}">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    `
                ),
                selectedCategoryId ? 'Belum ada kriteria pada kategori ini.' : 'Belum ada kriteria.'
            ),
    });

    document.querySelector('[name="criteria_category_filter"]')?.addEventListener('change', (event) => {
        state.filters.criteriasCategoryId = event.target.value;
        renderCriteriasTab();
    });

    const criteriaFormBody = (row = null) => `
        <form id="criteria-form" class="modal-form">
            ${createLabeledSelect({
                label: 'Kategori',
                name: 'category_id',
                value: row?.category_id || '',
                options: [
                    { value: '', label: 'Pilih kategori' },
                    ...categories.map((item) => ({ value: item.id, label: item.name })),
                ],
            })}
            ${createLabeledInput({ label: 'Nama Kriteria', name: 'name', value: row?.name || '' })}
            ${createLabeledSelect({
                label: 'Jenis',
                name: 'type',
                value: row?.type || 'benefit',
                options: [
                    { value: 'benefit', label: 'Benefit' },
                    { value: 'cost', label: 'Cost' },
                ],
            })}
            ${createLabeledInput({ label: 'Bobot', name: 'weight', value: row?.weight || '1', type: 'number', min: '0.0001', step: '0.0001' })}
        </form>
    `;

    document.getElementById('add-criteria-btn')?.addEventListener('click', () => {
        openModal({
            title: 'Tambah Kriteria',
            description: 'Pilih kategori, isi nama kriteria, jenis, dan bobot.',
            body: criteriaFormBody(),
            actions: [
                { label: 'Batal', variant: 'btn-ghost', onClick: closeModal },
                {
                    label: 'Simpan',
                    variant: 'btn-primary',
                    onClick: async () => {
                        const form = document.getElementById('criteria-form');
                        if (!form.reportValidity()) return;
                        const payload = Object.fromEntries(new FormData(form).entries());

                        showConfirmModal({
                            title: 'Simpan Kriteria',
                            description: 'Apakah data kriteria baru ingin disimpan?',
                            confirmLabel: 'Ya, simpan',
                            onConfirm: async () => {
                                const result = await request({ method: 'post', url: '/api/criterias', data: payload });
                                if (!result) return;
                                closeModal();
                                showToast('Kriteria berhasil ditambahkan.');
                                renderCriteriasTab();
                            },
                        });
                    },
                },
            ],
        });
    });

    rows.forEach((row) => {
        document.querySelector(`[data-criteria-edit="${row.id}"]`)?.addEventListener('click', () => {
            openModal({
                title: 'Edit Kriteria',
                description: 'Sesuaikan kategori, nama, jenis, atau bobot kriteria.',
                body: criteriaFormBody(row),
                actions: [
                    { label: 'Batal', variant: 'btn-ghost', onClick: closeModal },
                    {
                        label: 'Update',
                        variant: 'btn-primary',
                        onClick: async () => {
                            const form = document.getElementById('criteria-form');
                            if (!form.reportValidity()) return;
                            const payload = Object.fromEntries(new FormData(form).entries());

                            showConfirmModal({
                                title: 'Update Kriteria',
                                description: `Apakah perubahan pada kriteria ${row.name} ingin disimpan?`,
                                confirmLabel: 'Ya, update',
                                onConfirm: async () => {
                                    const result = await request({ method: 'patch', url: `/api/criterias/${row.id}`, data: payload });
                                    if (!result) return;
                                    closeModal();
                                    showToast('Kriteria berhasil diperbarui.');
                                    renderCriteriasTab();
                                },
                            });
                        },
                    },
                ],
            });
        });

        document.querySelector(`[data-criteria-delete="${row.id}"]`)?.addEventListener('click', () => {
            showConfirmModal({
                title: 'Hapus Kriteria',
                description: `Apakah kriteria ${row.name} ingin dihapus?`,
                confirmLabel: 'Ya, hapus',
                variant: 'btn-danger',
                onConfirm: async () => {
                    const result = await request({ method: 'delete', url: `/api/criterias/${row.id}` });
                    if (!result) return;
                    closeModal();
                    showToast('Kriteria berhasil dihapus.');
                    renderCriteriasTab();
                },
            });
        });
    });
}

function buildPerformanceFormBody(options, selectedUserId = '', selectedPeriodId = '', groupData = null, lockSelection = false) {
    const users = options.users || [];
    const periods = options.periods || [];
    const categories = groupData?.rows || options.categories || [];
    const userFieldName = lockSelection ? 'user_id_display' : 'user_id';
    const periodFieldName = lockSelection ? 'period_id_display' : 'period_id';

    return `
        <form id="performance-form" class="modal-form">
            <div class="two-col">
                ${createLabeledSelect({
                    label: 'User yang Dinilai',
                    name: userFieldName,
                    value: selectedUserId,
                    options: [{ value: '', label: 'Pilih user' }, ...users.map((item) => ({ value: item.id, label: item.name }))],
                })}
                ${createLabeledSelect({
                    label: 'Periode',
                    name: periodFieldName,
                    value: selectedPeriodId,
                    options: [{ value: '', label: 'Pilih periode' }, ...periods.map((item) => ({ value: item.id, label: item.period_name }))],
                })}
            </div>
            ${lockSelection ? `<input type="hidden" name="user_id" value="${escapeHtml(selectedUserId)}">` : ''}
            ${lockSelection ? `<input type="hidden" name="period_id" value="${escapeHtml(selectedPeriodId)}">` : ''}

            <div class="form-stack">
                ${categories
                    .map(
                        (category) => `
                            <section class="criteria-group">
                                <div class="criteria-group__head">
                                    <strong>${escapeHtml(category.category_name || category.name)}</strong>
                                    <span class="inline-chip">${(category.criterias || []).length} kriteria</span>
                                </div>
                                <div class="criteria-list">
                                    ${(category.criterias || [])
                                        .map(
                                            (criteria) => `
                                                <div class="criteria-row">
                                                    <div class="criteria-meta">
                                                        <strong>${escapeHtml(criteria.criteria_name || criteria.name)}</strong>
                                                        <small>${escapeHtml(criteria.type)}${criteria.weight ? ` • bobot ${criteria.weight}` : ''}</small>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        name="score_${criteria.criteria_id || criteria.id}"
                                                        min="0"
                                                        max="100"
                                                        step="0.01"
                                                        required
                                                        value="${escapeHtml(criteria.score ?? '')}"
                                                        data-criteria-id="${criteria.criteria_id || criteria.id}"
                                                    >
                                                </div>
                                            `
                                        )
                                        .join('')}
                                </div>
                            </section>
                        `
                    )
                    .join('')}
            </div>
        </form>
    `;
}

function extractPerformancePayload(form) {
    const formData = new FormData(form);
    const userId = Number(formData.get('user_id') || form.querySelector('[name="user_id_display"]')?.value || 0);
    const periodId = Number(formData.get('period_id') || form.querySelector('[name="period_id_display"]')?.value || 0);
    const scores = Array.from(form.querySelectorAll('[data-criteria-id]')).map((input) => ({
        criteria_id: Number(input.getAttribute('data-criteria-id')),
        score: Number(input.value),
    }));

    return { user_id: userId, period_id: periodId, scores };
}

function hasExistingPerformance(groupData) {
    return (groupData?.rows || []).some((category) =>
        (category.criterias || []).some((criteria) => criteria.score !== null && criteria.score !== '')
    );
}

async function openPerformanceForm(mode, row = null) {
    const optionsResponse = await request({ method: 'get', url: '/api/performances/form-options' });
    if (!optionsResponse) return;

    const options = optionsResponse.data || {};
    let groupData = null;

    if (row) {
        const groupResponse = await request({
            method: 'get',
            url: `/api/performances/group?user_id=${row.user_id}&period_id=${row.period_id}`,
        });
        if (!groupResponse) return;
        groupData = groupResponse.data;
    }

    openModal({
        title: mode === 'create' ? 'Tambah Data Performa' : 'Edit Data Performa',
        description:
            mode === 'create'
                ? 'Isi user, periode, lalu masukkan nilai untuk setiap kriteria yang tampil.'
                : 'Perbarui nilai performa user pada periode yang dipilih.',
        body: buildPerformanceFormBody(options, row?.user_id || '', row?.period_id || '', groupData, mode !== 'create'),
        actions: [
            { label: 'Batal', variant: 'btn-ghost', onClick: closeModal },
            {
                label: mode === 'create' ? 'Simpan' : 'Update',
                variant: 'btn-primary',
                onClick: async () => {
                    const form = document.getElementById('performance-form');
                    if (!form.reportValidity()) return;
                    const payload = extractPerformancePayload(form);

                    if (!payload.user_id || !payload.period_id) {
                        showToast('User dan periode wajib dipilih.', true);
                        return;
                    }

                    showConfirmModal({
                        title: mode === 'create' ? 'Simpan Data Performa' : 'Update Data Performa',
                        description:
                            mode === 'create'
                                ? 'Apakah seluruh nilai performa sudah benar dan ingin disimpan?'
                                : 'Apakah perubahan nilai performa ingin disimpan?',
                        confirmLabel: mode === 'create' ? 'Ya, simpan' : 'Ya, update',
                        onConfirm: async () => {
                            const result = await request({
                                method: mode === 'create' ? 'post' : 'patch',
                                url: mode === 'create' ? '/api/performances' : '/api/performances/group',
                                data: payload,
                            });
                            if (!result) return;
                            closeModal();
                            showToast(mode === 'create' ? 'Data performa berhasil ditambahkan.' : 'Data performa berhasil diperbarui.');
                            renderPerformancesTab();
                        },
                    });
                },
            },
        ],
    });

    const form = document.getElementById('performance-form');
    const userSelect = form.querySelector(mode === 'create' ? '[name="user_id"]' : '[name="user_id_display"]');
    const periodSelect = form.querySelector(mode === 'create' ? '[name="period_id"]' : '[name="period_id_display"]');

    if (mode === 'create') {
        const refreshCriteria = async () => {
            const userId = userSelect.value;
            const periodId = periodSelect.value;

            if (!userId || !periodId) return;

            const groupResponse = await request({
                method: 'get',
                url: `/api/performances/group?user_id=${userId}&period_id=${periodId}`,
            }, { silent: true });

            if (hasExistingPerformance(groupResponse?.data)) {
                openPerformanceForm('edit', { user_id: userId, period_id: periodId });
            }
        };

        userSelect.addEventListener('change', refreshCriteria);
        periodSelect.addEventListener('change', refreshCriteria);
    } else {
        userSelect.setAttribute('disabled', 'disabled');
        periodSelect.setAttribute('disabled', 'disabled');
    }
}

async function renderPerformancesTab() {
    const query = state.filters.performancesPeriodId ? `?period_id=${state.filters.performancesPeriodId}` : '';
    const response = await request({ method: 'get', url: `/api/performances${query}` });
    if (!response) return;

    const rows = normalizeRows(response);
    const periods = response.meta?.periods || [];
    const selectedPeriodId = String(response.meta?.selected_period_id || state.filters.performancesPeriodId || '');
    state.filters.performancesPeriodId = selectedPeriodId;

    setPageHeader('Menu Performa', `${rows.length} data`);
    buildSection({
        title: 'Input dan Kelola Performa',
        description: 'Filter berdasarkan periode agar pembacaan dan pengelolaan data performa jadi lebih fokus.',
        actions: `<button class="btn btn-primary" id="add-performance-btn">Tambah Data Performa</button>`,
        metrics: cardMetrics([
            { label: 'Total Data', value: rows.length },
            { label: 'Periode Open', value: rows.filter((item) => !item.period?.is_finalized).length },
            { label: 'Periode Finished', value: rows.filter((item) => item.period?.is_finalized).length },
        ]),
        content:
            createFilterToolbar([
                createLabeledSelect({
                    label: 'Filter Periode',
                    name: 'performance_period_filter',
                    value: selectedPeriodId,
                    required: false,
                    options: [
                        { value: '', label: 'Semua periode' },
                        ...periods.map((item) => ({ value: item.id, label: item.period_name })),
                    ],
                }),
            ]) +
            renderTable(
                ['No', 'Periode', 'Nama User', 'Ringkasan', 'Action'],
                rows.map(
                    (row, index) => `
                        <tr>
                            <td>${index + 1}</td>
                            <td>
                                <strong>${escapeHtml(row.period?.period_name || '-')}</strong>
                                <div><span class="table-chip ${row.period?.is_finalized ? 'is-final' : 'is-open'}">${row.period?.is_finalized ? 'Finished' : 'Open'}</span></div>
                            </td>
                            <td>${escapeHtml(row.user?.name || '-')}</td>
                            <td>${row.criteria_count} kriteria, rata-rata ${Number(row.average_score || 0).toFixed(2)}</td>
                            <td>
                                <div class="table-actions">
                                    <button class="action-link" data-performance-view="${row.user_id}_${row.period_id}">View</button>
                                    <button class="action-link is-primary" data-performance-edit="${row.user_id}_${row.period_id}" ${row.period?.is_finalized ? 'disabled' : ''}>Edit</button>
                                    <button class="action-link is-danger" data-performance-delete="${row.user_id}_${row.period_id}" ${row.period?.is_finalized ? 'disabled' : ''}>Hapus</button>
                                </div>
                            </td>
                        </tr>
                    `
                ),
                selectedPeriodId ? 'Belum ada data performa pada periode ini.' : 'Belum ada data performa.'
            ),
    });

    document.querySelector('[name="performance_period_filter"]')?.addEventListener('change', (event) => {
        state.filters.performancesPeriodId = event.target.value;
        renderPerformancesTab();
    });

    document.getElementById('add-performance-btn')?.addEventListener('click', () => openPerformanceForm('create'));

    rows.forEach((row) => {
        const key = `${row.user_id}_${row.period_id}`;

        document.querySelector(`[data-performance-view="${key}"]`)?.addEventListener('click', async () => {
            const detail = await request({
                method: 'get',
                url: `/api/performances/group?user_id=${row.user_id}&period_id=${row.period_id}`,
            });
            if (!detail) return;

            openModal({
                title: 'Preview Data Performa',
                description: `${detail.data?.user?.name || '-'} pada periode ${detail.data?.period?.period_name || '-'}.`,
                body: `
                    <div class="form-stack">
                        ${(detail.data?.rows || [])
                            .map(
                                (category) => `
                                    <section class="criteria-group">
                                        <div class="criteria-group__head">
                                            <strong>${escapeHtml(category.category_name)}</strong>
                                            <span class="inline-chip">${category.criterias.length} kriteria</span>
                                        </div>
                                        <div class="criteria-list">
                                            ${category.criterias
                                                .map(
                                                    (criteria) => `
                                                        <div class="criteria-row">
                                                            <div class="criteria-meta">
                                                                <strong>${escapeHtml(criteria.criteria_name)}</strong>
                                                                <small>${escapeHtml(criteria.type)}</small>
                                                            </div>
                                                            <div class="preview-box">${criteria.score ?? '-'}</div>
                                                        </div>
                                                    `
                                                )
                                                .join('')}
                                        </div>
                                    </section>
                                `
                            )
                            .join('')}
                    </div>
                `,
                actions: [{ label: 'Tutup', variant: 'btn-primary', onClick: closeModal }],
            });
        });

        document.querySelector(`[data-performance-edit="${key}"]`)?.addEventListener('click', () => openPerformanceForm('edit', row));

        document.querySelector(`[data-performance-delete="${key}"]`)?.addEventListener('click', () => {
            showConfirmModal({
                title: 'Hapus Data Performa',
                description: `Apakah data performa ${row.user?.name || '-'} pada periode ${row.period?.period_name || '-'} ingin dihapus?`,
                confirmLabel: 'Ya, hapus',
                variant: 'btn-danger',
                onConfirm: async () => {
                    const result = await request({
                        method: 'delete',
                        url: `/api/performances/group?user_id=${row.user_id}&period_id=${row.period_id}`,
                    });
                    if (!result) return;
                    closeModal();
                    showToast('Data performa berhasil dihapus.');
                    renderPerformancesTab();
                },
            });
        });
    });
}

async function renderAccountTab() {
    setPageHeader('Menu Password', 'akun');
    buildSection({
        title: 'Keamanan Akun',
        description: 'Perbarui password akun secara mandiri kapan saja langsung dari dashboard.',
        metrics: cardMetrics([
            { label: 'Nama Akun', value: state.me?.name || '-' },
            { label: 'Role', value: state.me?.role || '-' },
            { label: 'Status', value: state.me?.must_change_password ? 'Wajib Ganti' : 'Aman' },
        ]),
        content: `
            <div class="toolbar-card">
                <form id="account-password-form" class="form-stack">
                    <div class="two-col">
                        ${createLabeledInput({ label: 'Password Saat Ini', name: 'current_password', type: 'password' })}
                        ${createLabeledInput({ label: 'Password Baru', name: 'password', type: 'password' })}
                    </div>
                    ${createLabeledInput({ label: 'Konfirmasi Password Baru', name: 'password_confirmation', type: 'password' })}
                    <div class="section-tools">
                        <button class="btn btn-primary" id="account-password-submit" type="submit">Update Password</button>
                    </div>
                </form>
            </div>
        `,
    });

    document.getElementById('account-password-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const form = event.target;
        if (!form.reportValidity()) return;

        const submitButton = document.getElementById('account-password-submit');
        setButtonBusy(submitButton, true, 'Update Password');

        const payload = Object.fromEntries(new FormData(form).entries());
        const response = await request({ method: 'post', url: '/api/auth/update-password', data: payload });

        if (response) {
            form.reset();
            await refreshMe();
            renderTabs();
            showToast('Password berhasil diperbarui.');
        }

        setButtonBusy(submitButton, false, 'Update Password');
    });
}

async function renderCalculateTab() {
    const periodsResponse = await request({ method: 'get', url: '/api/periods' });
    if (!periodsResponse) return;

    const periods = normalizeRows(periodsResponse).filter((item) => !item.is_finalized);

    setPageHeader('Menu Calculate', `${periods.length} periode siap hitung`);
    buildSection({
        title: 'Perhitungan TOPSIS',
        description: 'Pilih periode, preview matriks alternatif dan nilai C1, C2, dan seterusnya, lalu jalankan perhitungan.',
        metrics: cardMetrics([
            { label: 'Periode Open', value: periods.length },
            { label: 'Periode Selesai', value: normalizeRows(periodsResponse).filter((item) => item.is_finalized).length },
            { label: 'Siap Hitung', value: periods.length },
        ]),
        content: `
            <div class="form-stack">
                <div class="two-col">
                    ${createLabeledSelect({
                        label: 'Pilih Periode',
                        name: 'calculate_period',
                        options: [{ value: '', label: 'Pilih periode' }, ...periods.map((item) => ({ value: item.id, label: item.period_name }))],
                    })}
                    <div class="field">
                        <span>Aksi</span>
                        <button class="btn btn-secondary" id="preview-calculate-btn" type="button">Tampilkan Data</button>
                    </div>
                </div>
                <div id="calculate-preview" class="empty-state">Pilih periode lalu klik "Tampilkan Data" untuk melihat matriks perhitungan.</div>
            </div>
        `,
    });

    document.getElementById('preview-calculate-btn')?.addEventListener('click', async () => {
        const periodId = document.querySelector('[name="calculate_period"]').value;
        if (!periodId) {
            showToast('Silakan pilih periode terlebih dahulu.', true);
            return;
        }

        const preview = await request({ method: 'get', url: `/api/topsis/preview?period_id=${periodId}` });
        if (!preview) return;

        const period = preview.data?.period;
        const criterias = preview.data?.criterias || [];
        const alternatives = preview.data?.alternatives || [];

        const previewBox = document.getElementById('calculate-preview');
        previewBox.className = '';
        previewBox.innerHTML = `
            <div class="section-head">
                <div>
                    <h2>Preview Matrix ${escapeHtml(period?.period_name || '')}</h2>
                    <p>Format alternatif menggunakan kode A, dan kriteria menggunakan kode C.</p>
                </div>
                <div>
                    <button class="btn btn-primary" id="run-calculate-btn">Hitung TOPSIS</button>
                </div>
            </div>
            ${renderTable(
                ['Alternatif', 'Nama User', ...criterias.map((item) => item.code)],
                alternatives.map(
                    (item) => `
                        <tr>
                            <td>${escapeHtml(item.alternative_code)}</td>
                            <td>${escapeHtml(item.user_name)}</td>
                            ${item.scores.map((score) => `<td>${score.score}</td>`).join('')}
                        </tr>
                    `
                ),
                'Belum ada data performa lengkap untuk periode ini.'
            )}
        `;

        document.getElementById('run-calculate-btn')?.addEventListener('click', () => {
            showConfirmModal({
                title: 'Lanjutkan Perhitungan TOPSIS',
                description: 'Jika dilanjutkan, periode akan ditandai selesai dan data terkait tidak dapat diedit sampai hasil perhitungannya dihapus.',
                confirmLabel: 'Ya, hitung sekarang',
                onConfirm: async () => {
                    const result = await request({ method: 'post', url: '/api/topsis/calculate', data: { period_id: Number(periodId) } });
                    if (!result) return;
                    closeModal();
                    showToast('Perhitungan TOPSIS berhasil dijalankan.');
                    renderCalculateTab();
                },
            });
        });
    });
}

async function renderResultsTab() {
    const response = await request({ method: 'get', url: '/api/topsis' });
    if (!response) return;

    const rows = normalizeRows(response);

    setPageHeader('TOPSIS Result', `${rows.length} histori`);
    buildSection({
        title: state.me.role === 'admin' ? 'Riwayat Hasil Perhitungan' : 'Hasil Perangkingan',
        description:
            state.me.role === 'admin'
                ? 'Lihat ranking per periode, dan untuk admin tersedia aksi hapus hasil perhitungan.'
                : 'Role user hanya dapat melihat hasil perankingan per periode.',
        metrics: cardMetrics([
            { label: 'Total Histori', value: rows.length },
            { label: 'Periode Selesai', value: rows.filter((item) => item.is_finalized).length },
            { label: 'Ranking Tersimpan', value: rows.reduce((sum, item) => sum + (item.topsis_results_count || 0), 0) },
        ]),
        content: renderTable(
            ['No', 'Nama Periode', 'Status', 'Action'],
            rows.map(
                (row, index) => `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${escapeHtml(row.period_name)}</td>
                        <td><span class="table-chip ${row.is_finalized ? 'is-final' : 'is-open'}">${row.is_finalized ? 'Finished' : 'Open'}</span></td>
                        <td>
                            <div class="table-actions">
                                <button class="action-link is-primary" data-result-view="${row.id}">View</button>
                                ${
                                    state.me.role === 'admin'
                                        ? `<button class="action-link is-danger" data-result-delete="${row.id}">Hapus Hasil</button>`
                                        : ''
                                }
                            </div>
                        </td>
                    </tr>
                `
            ),
            'Belum ada hasil perhitungan.'
        ),
    });

    rows.forEach((row) => {
        document.querySelector(`[data-result-view="${row.id}"]`)?.addEventListener('click', async () => {
            const detail = await request({ method: 'get', url: `/api/topsis/period/${row.id}` });
            if (!detail) return;

            openModal({
                title: `Ranking ${detail.data?.period?.period_name || ''}`,
                description: 'Hasil ranking tersusun dari nilai preferensi tertinggi ke terendah.',
                body: renderTable(
                    ['Rank', 'Nama User', 'Nilai Preferensi'],
                    (detail.data?.results || []).map(
                        (item) => `
                            <tr>
                                <td>${item.rank}</td>
                                <td>${escapeHtml(item.user_name)}</td>
                                <td>${Number(item.preference_value).toFixed(6)}</td>
                            </tr>
                        `
                    )
                ),
                actions: [{ label: 'Tutup', variant: 'btn-primary', onClick: closeModal }],
            });
        });

        if (state.me.role === 'admin') {
            document.querySelector(`[data-result-delete="${row.id}"]`)?.addEventListener('click', () => {
                showConfirmModal({
                    title: 'Hapus Hasil Perhitungan',
                    description: `Hasil perhitungan periode ${row.period_name} akan dihapus dan periode dibuka kembali. Lanjutkan?`,
                    confirmLabel: 'Ya, hapus hasil',
                    variant: 'btn-danger',
                    onConfirm: async () => {
                        const result = await request({ method: 'delete', url: `/api/topsis/period/${row.id}` });
                        if (!result) return;
                        closeModal();
                        showToast('Hasil perhitungan berhasil dihapus dan periode dibuka kembali.');
                        renderResultsTab();
                    },
                });
            });
        }
    });
}

async function renderActiveTab() {
    switch (state.activeTab) {
        case 'users':
            await renderUsersTab();
            break;
        case 'periods':
            await renderPeriodsTab();
            break;
        case 'categories':
            await renderCategoriesTab();
            break;
        case 'criterias':
            await renderCriteriasTab();
            break;
        case 'performances':
            await renderPerformancesTab();
            break;
        case 'calculate':
            await renderCalculateTab();
            break;
        case 'results':
            await renderResultsTab();
            break;
        case 'account':
            await renderAccountTab();
            break;
        default:
            contentArea.innerHTML = '<div class="empty-state">Menu belum tersedia.</div>';
    }
}

async function refreshMe() {
    const response = await request({ method: 'get', url: '/api/auth/me' }, { silent: true });
    if (!response?.data) return false;

    state.me = response.data;
    sessionInfo.textContent = `Login sebagai ${state.me.name} (${state.me.role})`;
    return true;
}

function showForcePasswordPanel() {
    setViewMode('force-password');
    pageBadge.textContent = 'Keamanan';
    passwordPanelCopy.textContent = `${state.me?.name || 'Akun ini'} masih menggunakan password default. Ganti password terlebih dahulu untuk melanjutkan ke dashboard.`;
}

function handleLogout(withApi = true) {
    if (withApi) {
        request({ method: 'post', url: '/api/auth/logout' }, { silent: true });
    }

    setAuthToken('');
    state.me = null;
    state.activeTab = null;
    closeModal();
    setViewMode('login');
    pageBadge.textContent = 'Siap';
}

async function afterAuth() {
    const ok = await refreshMe();
    if (!ok) {
        handleLogout(false);
        return;
    }

    if (state.me.must_change_password) {
        showForcePasswordPanel();
        return;
    }

    setViewMode('dashboard');
    state.activeTab = getAllowedTabs()[0]?.key || null;
    renderTabs();
    renderActiveTab();
}

async function bootstrap() {
    setAuthToken(state.token);

    document.getElementById('login-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        setButtonBusy(loginSubmit, true, 'Masuk');

        const payload = Object.fromEntries(new FormData(event.target).entries());
        const response = await request({ method: 'post', url: '/api/auth/login', data: payload });

        if (response?.data?.token) {
            setAuthToken(response.data.token);
            await afterAuth();
            showToast('Login berhasil.');
            event.target.reset();
        }

        setButtonBusy(loginSubmit, false, 'Masuk');
    });

    document.getElementById('force-password-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        setButtonBusy(forcePasswordSubmit, true, 'Simpan Password Baru');

        const payload = Object.fromEntries(new FormData(event.target).entries());
        const response = await request({ method: 'post', url: '/api/auth/force-change-password', data: payload });

        if (response) {
            event.target.reset();
            showToast('Password berhasil diperbarui.');
            await afterAuth();
        }

        setButtonBusy(forcePasswordSubmit, false, 'Simpan Password Baru');
    });

    logoutBtn?.addEventListener('click', () => {
        handleLogout(true);
        showToast('Logout berhasil.');
    });

    if (state.token) {
        await afterAuth();
    }
}

bootstrap();
