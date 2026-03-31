import './bootstrap';

const state = {
    token: localStorage.getItem('token') || '',
    me: null,
    activeTab: null,
};

const tabs = [
    { key: 'users', label: 'Users', roles: ['admin'] },
    { key: 'periods', label: 'Periods', roles: ['admin'] },
    { key: 'categories', label: 'Categories', roles: ['admin'] },
    { key: 'criterias', label: 'Criterias', roles: ['admin'] },
    { key: 'performances', label: 'Performances', roles: ['admin'] },
    { key: 'topsis', label: 'TOPSIS Results', roles: ['admin', 'user'] },
    { key: 'topsis-calculate', label: 'TOPSIS Calculate', roles: ['admin'] },
];

const authPanel = document.getElementById('auth-panel');
const appPanel = document.getElementById('app-panel');
const navTabs = document.getElementById('nav-tabs');
const contentArea = document.getElementById('content-area');
const sessionInfo = document.getElementById('session-info');
const logoutBtn = document.getElementById('logout-btn');
const toast = document.getElementById('toast');

const endpointSchema = {
    users: { path: '/api/users', fields: ['name', 'email', 'password', 'role'] },
    periods: { path: '/api/periods', fields: ['period_name', 'is_finalized'] },
    categories: { path: '/api/categories', fields: ['name', 'weight'] },
    criterias: { path: '/api/criterias', fields: ['category_id', 'name', 'weight', 'type'] },
};

const api = axios.create({ baseURL: '/' });

function setAuthToken(token) {
    state.token = token || '';
    localStorage.setItem('token', state.token);
    api.defaults.headers.common.Authorization = state.token ? `Bearer ${state.token}` : '';
}

function showToast(message, isError = false) {
    toast.className = `fixed bottom-6 right-6 rounded-lg px-4 py-3 text-sm font-semibold text-white shadow-lg ${isError ? 'bg-red-600' : 'bg-slate-900'}`;
    toast.textContent = message;
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 2500);
}

function normalizePayload(responseData) {
    if (responseData?.data?.data && Array.isArray(responseData.data.data)) return responseData.data.data;
    if (Array.isArray(responseData?.data)) return responseData.data;
    return [];
}

function toLabel(field) {
    return field.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

async function request(config, options = { suppressToast: false }) {
    try {
        const response = await api.request(config);
        return response.data;
    } catch (error) {
        if (error.response?.status === 401) {
            handleLogout(false);
            showToast('Session habis, silakan login kembali.', true);
            return null;
        }

        if (!options.suppressToast) {
            const message = error.response?.data?.message || 'Request gagal';
            showToast(message, true);
        }

        return null;
    }
}

function renderTabs() {
    navTabs.innerHTML = '';
    const allowed = tabs.filter((tab) => tab.roles.includes(state.me.role));

    allowed.forEach((tab) => {
        const btn = document.createElement('button');
        btn.textContent = tab.label;
        btn.className = `rounded-lg px-3 py-2 text-sm font-semibold ${state.activeTab === tab.key ? 'bg-blue-600 text-white' : 'bg-slate-200 hover:bg-slate-300'}`;
        btn.addEventListener('click', () => {
            state.activeTab = tab.key;
            renderTabs();
            renderActiveTab();
        });
        navTabs.appendChild(btn);
    });
}

function inputForField(field, value = '', mode = 'create') {
    if (field === 'type') {
        return `<select name="type" class="rounded border border-slate-300 px-2 py-1"><option value="benefit" ${value === 'benefit' ? 'selected' : ''}>benefit</option><option value="cost" ${value === 'cost' ? 'selected' : ''}>cost</option></select>`;
    }

    if (field === 'role') {
        return `<select name="role" class="rounded border border-slate-300 px-2 py-1"><option value="admin" ${value === 'admin' ? 'selected' : ''}>admin</option><option value="user" ${value === 'user' ? 'selected' : ''}>user</option></select>`;
    }

    if (field === 'is_finalized') {
        return `<label class="inline-flex items-center gap-2"><input name="is_finalized" type="checkbox" ${value ? 'checked' : ''}> Finalized</label>`;
    }

    const type = field === 'password' ? 'password' : 'text';
    const required = mode === 'create' || !['password', 'is_finalized'].includes(field) ? 'required' : '';
    return `<input type="${type}" name="${field}" value="${value ?? ''}" placeholder="${field}" ${required} class="rounded border border-slate-300 px-2 py-1">`;
}

function buildPayload(formData, fields, mode = 'create') {
    const payload = {};
    fields.forEach((field) => {
        if (field === 'is_finalized') {
            payload.is_finalized = formData.get('is_finalized') === 'on';
            return;
        }

        const value = formData.get(field);
        if (mode === 'update' && (value === null || value === '')) return;
        payload[field] = value;
    });

    if (mode === 'update' && payload.password === '') delete payload.password;
    return payload;
}

async function renderCrudTab(key) {
    const schema = endpointSchema[key];
    const data = await request({ method: 'get', url: schema.path });
    if (!data) return;

    const rows = normalizePayload(data);

    contentArea.innerHTML = `
        <div class="space-y-4">
            <form id="create-form" class="flex flex-wrap items-center gap-2">
                ${schema.fields.map((field) => inputForField(field)).join('')}
                <button class="rounded bg-emerald-600 px-3 py-1 text-white">Create</button>
            </form>
            <div id="edit-wrapper" class="hidden rounded-lg border border-slate-200 bg-slate-50 p-3">
                <h3 class="mb-2 text-sm font-semibold">Edit Data</h3>
                <form id="edit-form" class="flex flex-wrap items-center gap-2"></form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b">${Object.keys(rows[0] || { id: '', info: '' }).map((col) => `<th class="p-2">${col}</th>`).join('')}<th class="p-2">actions</th></tr></thead>
                    <tbody>
                        ${rows.map((row) => `<tr class="border-b align-top">${Object.values(row).map((v) => `<td class="p-2">${typeof v === 'object' ? JSON.stringify(v) : v}</td>`).join('')}<td class="p-2 space-x-2"><button data-id="${row.id}" class="edit-btn rounded bg-amber-500 px-2 py-1 text-white">Edit</button><button data-id="${row.id}" class="delete-btn rounded bg-red-600 px-2 py-1 text-white">Delete</button></td></tr>`).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    document.getElementById('create-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = buildPayload(new FormData(event.target), schema.fields, 'create');
        const res = await request({ method: 'post', url: schema.path, data: payload });
        if (res) {
            showToast('Data dibuat.');
            renderCrudTab(key);
        }
    });

    document.querySelectorAll('.edit-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = Number(btn.getAttribute('data-id'));
            const row = rows.find((item) => Number(item.id) === id);
            if (!row) return;

            const editWrapper = document.getElementById('edit-wrapper');
            const editForm = document.getElementById('edit-form');
            editWrapper.classList.remove('hidden');
            editForm.innerHTML = `
                ${schema.fields.map((field) => inputForField(field, row[field], 'update')).join('')}
                <button class="rounded bg-blue-600 px-3 py-1 text-white">Update</button>
                <button type="button" id="cancel-edit" class="rounded bg-slate-400 px-3 py-1 text-white">Cancel</button>
            `;

            editForm.onsubmit = async (event) => {
                event.preventDefault();
                const payload = buildPayload(new FormData(editForm), schema.fields, 'update');
                const res = await request({ method: 'patch', url: `${schema.path}/${id}`, data: payload });
                if (res) {
                    showToast('Data diperbarui.');
                    renderCrudTab(key);
                }
            };

            document.getElementById('cancel-edit').addEventListener('click', () => {
                editWrapper.classList.add('hidden');
            });
        });
    });

    document.querySelectorAll('.delete-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            const res = await request({ method: 'delete', url: `${schema.path}/${id}` });
            if (res) {
                showToast('Data dihapus.');
                renderCrudTab(key);
            }
        });
    });
}

async function renderPerformanceTab() {
    const summary = await request({ method: 'get', url: '/api/performances?summary=1' });
    if (!summary) return;

    const rows = normalizePayload(summary);

    contentArea.innerHTML = `
        <div class="space-y-4">
            <p class="text-sm text-slate-600">Daftar performa per user dan periode. Klik edit untuk ubah score per kriteria (hanya periode belum finalized).</p>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b"><th class="p-2">User</th><th class="p-2">Periode</th><th class="p-2">Jumlah Kriteria</th><th class="p-2">Rata-rata</th><th class="p-2">Status Periode</th><th class="p-2">Actions</th></tr></thead>
                    <tbody>
                        ${rows.map((row) => `<tr class="border-b"><td class="p-2">${row.user?.name ?? '-'}</td><td class="p-2">${row.period?.period_name ?? '-'}</td><td class="p-2">${row.criteria_count}</td><td class="p-2">${Number(row.average_score).toFixed(2)}</td><td class="p-2">${row.period?.is_finalized ? 'Finalized' : 'Open'}</td><td class="p-2"><button data-user="${row.user_id}" data-period="${row.period_id}" data-finalized="${row.period?.is_finalized ? 1 : 0}" class="perf-edit-btn rounded bg-blue-600 px-2 py-1 text-white">Edit</button></td></tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div id="performance-editor"></div>
        </div>
    `;

    document.querySelectorAll('.perf-edit-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const userId = Number(btn.getAttribute('data-user'));
            const periodId = Number(btn.getAttribute('data-period'));
            const finalized = Number(btn.getAttribute('data-finalized')) === 1;

            const matrixRes = await request({ method: 'get', url: `/api/performances/matrix?user_id=${userId}&period_id=${periodId}` });
            if (!matrixRes) return;

            const matrixRows = matrixRes.data?.rows ?? [];
            const editor = document.getElementById('performance-editor');

            editor.innerHTML = `
                <div class="rounded-lg border border-slate-200 p-4">
                    <h3 class="mb-3 text-sm font-semibold">Edit Score - ${matrixRes.data?.period?.period_name ?? '-'}</h3>
                    <form id="performance-edit-form" class="space-y-3">
                        ${matrixRows.map((row) => `<div class="grid grid-cols-12 items-center gap-2"><label class="col-span-8 text-sm">${row.criteria_name} <span class="text-xs text-slate-400">(${row.criteria_type})</span></label><input data-criteria-id="${row.criteria_id}" type="number" min="0" max="100" step="0.0001" value="${row.score ?? ''}" ${finalized ? 'disabled' : ''} class="col-span-4 rounded border border-slate-300 px-2 py-1"></div>`).join('')}
                        <button ${finalized ? 'disabled' : ''} class="rounded bg-emerald-600 px-3 py-1 text-white disabled:cursor-not-allowed disabled:bg-slate-400">Simpan</button>
                    </form>
                </div>
            `;

            if (finalized) {
                showToast('Periode sudah finalized, tidak dapat diubah.', true);
                return;
            }

            document.getElementById('performance-edit-form').addEventListener('submit', async (event) => {
                event.preventDefault();
                const scores = Array.from(event.target.querySelectorAll('input[data-criteria-id]')).map((input) => ({
                    criteria_id: Number(input.getAttribute('data-criteria-id')),
                    score: Number(input.value || 0),
                }));

                const res = await request({ method: 'patch', url: '/api/performances/matrix', data: { user_id: userId, period_id: periodId, scores } });
                if (res) {
                    showToast('Score performa berhasil diperbarui.');
                    renderPerformanceTab();
                }
            });
        });
    });
}

async function renderTopsisTab() {
    const topsis = await request({ method: 'get', url: '/api/topsis' });
    if (!topsis) return;

    const resultRows = normalizePayload(topsis);
    const uniquePeriods = [...new Map(resultRows.map((item) => [item.period_id, item.period])).values()].filter(Boolean);

    contentArea.innerHTML = `
        <div class="space-y-4">
            <form id="filter-form" class="flex items-center gap-2">
                <label>Period</label>
                <select name="period_id" class="rounded border border-slate-300 px-3 py-1">
                    <option value="">Semua</option>
                    ${uniquePeriods.map((period) => `<option value="${period.id}">${period.period_name}</option>`).join('')}
                </select>
                <button class="rounded bg-blue-600 px-3 py-1 text-white">Filter</button>
            </form>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b"><th class="p-2">User</th><th class="p-2">Period</th><th class="p-2">Preference</th><th class="p-2">Rank</th></tr></thead>
                    <tbody>
                        ${resultRows.map((row) => `<tr class="border-b"><td class="p-2">${row.user?.name ?? '-'}</td><td class="p-2">${row.period?.period_name ?? '-'}</td><td class="p-2">${row.preference_value}</td><td class="p-2">${row.rank}</td></tr>`).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    document.getElementById('filter-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const periodId = new FormData(event.target).get('period_id');
        const res = await request({ method: 'get', url: periodId ? `/api/topsis?period_id=${periodId}` : '/api/topsis' });
        if (res) {
            const rows = normalizePayload(res);
            const tbody = contentArea.querySelector('tbody');
            tbody.innerHTML = rows.map((row) => `<tr class="border-b"><td class="p-2">${row.user?.name ?? '-'}</td><td class="p-2">${row.period?.period_name ?? '-'}</td><td class="p-2">${row.preference_value}</td><td class="p-2">${row.rank}</td></tr>`).join('');
        }
    });
}

async function renderTopsisCalculateTab() {
    const periods = await request({ method: 'get', url: '/api/periods' });
    if (!periods) return;
    const periodRows = normalizePayload(periods);

    contentArea.innerHTML = `
        <div class="space-y-4">
            <form id="calculate-form" class="flex items-center gap-2">
                <select name="period_id" required class="rounded border border-slate-300 px-3 py-1">
                    ${periodRows.map((period) => `<option value="${period.id}">${period.period_name}</option>`).join('')}
                </select>
                <button class="rounded bg-violet-600 px-3 py-1 text-white">Hitung TOPSIS</button>
            </form>
            <pre id="calculate-output" class="overflow-auto rounded bg-slate-900 p-4 text-xs text-slate-100">Belum ada kalkulasi.</pre>
        </div>
    `;

    document.getElementById('calculate-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const periodId = Number(new FormData(event.target).get('period_id'));
        const res = await request({ method: 'post', url: '/api/topsis/calculate', data: { period_id: periodId } });
        if (res) {
            document.getElementById('calculate-output').textContent = JSON.stringify(res, null, 2);
            showToast('TOPSIS selesai dihitung.');
        }
    });
}

async function renderActiveTab() {
    if (!state.activeTab) return;
    if (endpointSchema[state.activeTab]) {
        await renderCrudTab(state.activeTab);
        return;
    }

    if (state.activeTab === 'performances') {
        await renderPerformanceTab();
        return;
    }

    if (state.activeTab === 'topsis') {
        await renderTopsisTab();
        return;
    }

    if (state.activeTab === 'topsis-calculate') {
        await renderTopsisCalculateTab();
    }
}

async function refreshMe() {
    const response = await request({ method: 'get', url: '/api/auth/me' });
    if (!response?.data) return false;
    state.me = response.data;
    sessionInfo.textContent = `Login sebagai ${state.me.name} (${state.me.role})`;
    return true;
}

function togglePanels(isAuthenticated) {
    authPanel.classList.toggle('hidden', isAuthenticated);
    appPanel.classList.toggle('hidden', !isAuthenticated);
    logoutBtn.classList.toggle('hidden', !isAuthenticated);
}

function handleLogout(withApi = true) {
    if (withApi) request({ method: 'post', url: '/api/auth/logout' });
    setAuthToken('');
    state.me = null;
    state.activeTab = null;
    togglePanels(false);
    sessionInfo.textContent = 'Belum login.';
    navTabs.innerHTML = '';
    contentArea.innerHTML = '<p class="text-slate-500">Pilih menu untuk menampilkan data.</p>';
}

async function bootstrap() {
    setAuthToken(state.token);

    document.getElementById('login-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(event.target).entries());
        const response = await request({ method: 'post', url: '/api/auth/login', data: payload });
        if (!response?.data?.token) return;
        setAuthToken(response.data.token);
        await afterAuth();
        showToast('Login berhasil.');
    });

    document.getElementById('register-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(event.target).entries());
        const response = await request({ method: 'post', url: '/api/auth/register', data: payload });
        if (!response?.data?.token) return;
        setAuthToken(response.data.token);
        await afterAuth();
        showToast('Register berhasil dan langsung login.');
    });

    logoutBtn.addEventListener('click', () => {
        handleLogout(true);
        showToast('Logout berhasil.');
    });

    if (state.token) {
        await afterAuth();
    }
}

async function afterAuth() {
    const ok = await refreshMe();
    if (!ok) {
        handleLogout(false);
        return;
    }

    togglePanels(true);
    const allowedTabs = tabs.filter((tab) => tab.roles.includes(state.me.role));
    state.activeTab = allowedTabs[0]?.key ?? null;
    renderTabs();
    await renderActiveTab();
}

bootstrap();
