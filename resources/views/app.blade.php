<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Karya Nexa') }} - Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
<div class="mx-auto max-w-7xl px-4 py-8">
    <header class="mb-6 rounded-2xl bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-medium text-blue-600">Karya Nexa TOPSIS</p>
                <h1 class="text-2xl font-bold">Admin & User Dashboard</h1>
                <p id="session-info" class="text-sm text-slate-500">Belum login.</p>
            </div>
            <button id="logout-btn" class="hidden rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                Logout
            </button>
        </div>
    </header>

    <section id="auth-panel" class="grid gap-4 md:grid-cols-2">
        <form id="login-form" class="rounded-2xl bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-semibold">Login</h2>
            <input name="email" type="email" required placeholder="Email" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <input name="password" type="password" required placeholder="Password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <button class="w-full rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-500">Masuk</button>
        </form>

        <form id="register-form" class="rounded-2xl bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-semibold">Register</h2>
            <input name="name" required placeholder="Nama" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <input name="password" type="password" minlength="8" required placeholder="Password min 8 karakter" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-500">Daftar</button>
        </form>
    </section>

    <section id="app-panel" class="hidden space-y-4">
        <div id="nav-tabs" class="flex flex-wrap gap-2"></div>
        <div id="content-area" class="rounded-2xl bg-white p-6 shadow-sm">
            <p class="text-slate-500">Pilih menu untuk menampilkan data.</p>
        </div>
    </section>

    <div id="toast" class="fixed bottom-6 right-6 hidden rounded-lg px-4 py-3 text-sm font-semibold text-white shadow-lg"></div>
</div>
</body>
</html>
