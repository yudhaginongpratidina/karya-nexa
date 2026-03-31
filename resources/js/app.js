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
    users: { path: '/api/users', fields: ['name'] },
    periods: { path: '/api/periods', fields: ['period_name', 'is_finalized'] },
    categories: { path: '/api/categories', fields: ['name', 'weight'] },
    criterias: { path: '/api/criterias', fields: ['category_id', 'name', 'weight', 'type'] },
    performances: { path: '/api/performances', fields: ['user_id', 'criteria_id', 'period_id', 'score'] },
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

async function request(config) {
    try {
        const response = await api.request(config);
        return response.data;
    } catch (error) {
        if (error.response?.status === 401) {
            handleLogout(false);
            showToast('Session habis, silakan login kembali.', true);
            return null;
        }
        const message = error.response?.data?.message || 'Request gagal';
        showToast(message, true);
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

function fieldsToHtml(fields, existing = {}) {
    return fields.map((field) => {
        const value = existing[field] ?? '';
        if (field === 'type') {
            return `<select name="type" class="rounded border border-slate-300 px-2 py-1"><option value="benefit">benefit</option><option value="cost">cost</option></select>`;
        }

        if (field === 'is_finalized') {
            return `<label class="inline-flex items-center gap-2"><input name="is_finalized" type="checkbox" ${value ? 'checked' : ''}> Finalized</label>`;
        }

        return `<input name="${field}" value="${value}" placeholder="${field}" class="rounded border border-slate-300 px-2 py-1">`;
    }).join('');
}

async function renderCrudTab(key) {
    const schema = endpointSchema[key];
    const data = await request({ method: 'get', url: schema.path });
    if (!data) return;

    const rows = normalizePayload(data);

    contentArea.innerHTML = `
        <div class="space-y-4">
            <form id="create-form" class="flex flex-wrap items-center gap-2">
                ${fieldsToHtml(schema.fields)}
                <button class="rounded bg-emerald-600 px-3 py-1 text-white">Create</button>
            </form>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b">${Object.keys(rows[0] || { id: '', info: '' }).map((col) => `<th class="p-2">${col}</th>`).join('')}<th class="p-2">actions</th></tr></thead>
                    <tbody>
                        ${rows.map((row) => `<tr class="border-b align-top">${Object.values(row).map((v) => `<td class="p-2">${typeof v === 'object' ? JSON.stringify(v) : v}</td>`).join('')}<td class="p-2"><button data-id="${row.id}" class="delete-btn rounded bg-red-600 px-2 py-1 text-white">Delete</button></td></tr>`).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    document.getElementById('create-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(event.target);
        const payload = Object.fromEntries(formData.entries());
        if (schema.fields.includes('is_finalized')) payload.is_finalized = formData.get('is_finalized') === 'on';

        const res = await request({ method: 'post', url: schema.path, data: payload });
        if (res) {
            showToast('Data dibuat.');
            renderCrudTab(key);
        }
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

async function renderTopsisTab() {
    const periods = await request({ method: 'get', url: '/api/periods' });
    const topsis = await request({ method: 'get', url: '/api/topsis' });
    if (!periods || !topsis) return;

    const periodRows = normalizePayload(periods);
    const resultRows = normalizePayload(topsis);

    contentArea.innerHTML = `
        <div class="space-y-4">
            <form id="filter-form" class="flex items-center gap-2">
                <label>Period</label>
                <select name="period_id" class="rounded border border-slate-300 px-3 py-1">
                    <option value="">Semua</option>
                    ${periodRows.map((period) => `<option value="${period.id}">${period.period_name}</option>`).join('')}
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
