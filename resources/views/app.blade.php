<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Karya Nexa') }} - TOPSIS Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell">
    <div id="auth-panel" class="auth-layout">
        <section class="auth-card">
            <div class="auth-card__header">
                <!-- <p class="eyebrow">Karya Nexa</p> -->
                <h1>Portal Login</h1>
                <p class="auth-copy">Masuk dengan akun yang sudah terdaftar untuk mengakses dashboard penilaian.</p>
            </div>

            <form id="login-form" class="form-stack">
                <label class="field">
                    <span>Email</span>
                    <input name="email" type="email" required autocomplete="email" placeholder="contoh@gmail.com">
                </label>

                <label class="field">
                    <span>Password</span>
                    <input name="password" type="password" required autocomplete="current-password" placeholder="Masukkan password">
                </label>

                <button id="login-submit" class="btn btn-primary btn-block" type="submit">Masuk</button>
            </form>
        </section>
    </div>

    <div id="password-panel" class="auth-layout hidden">
        <section class="auth-card">
            <div class="auth-card__header">
                <p class="eyebrow">Keamanan Akun</p>
                <h1>Ganti Password</h1>
                <p id="password-panel-copy" class="auth-copy">Password default perlu diganti sebelum melanjutkan ke dashboard.</p>
            </div>

            <form id="force-password-form" class="form-stack">
                <label class="field">
                    <span>Password Baru</span>
                    <input name="password" type="password" required autocomplete="new-password" minlength="8" placeholder="Minimal 8 karakter">
                </label>

                <label class="field">
                    <span>Konfirmasi Password Baru</span>
                    <input name="password_confirmation" type="password" required autocomplete="new-password" minlength="8" placeholder="Ulangi password baru">
                </label>

                <button id="force-password-submit" class="btn btn-primary btn-block" type="submit">Simpan Password Baru</button>
            </form>
        </section>
    </div>

    <div id="app-panel" class="dashboard hidden">
        <main class="main-panel">
            <header class="dashboard-header">
                <div class="brand-card">
                    <div class="dashboard-header__row">
                        <div>
                            <!-- <p class="eyebrow">Karya Nexa</p> -->
                            <h2>TOPSIS Dashboard</h2>
                            <p id="session-info" class="muted">Belum login.</p>
                        </div>

                        <div class="topbar__actions">
                            <div id="page-badge" class="status-chip">Siap</div>
                            <button id="logout-btn" class="btn btn-danger-outline">Logout</button>
                        </div>
                    </div>

                    <nav id="nav-tabs" class="nav-list"></nav>
                </div>
            </header>

            <header class="topbar">
                <div>
                    <p class="eyebrow">Panel Aktif</p>
                    <h1 id="page-title">Dashboard</h1>
                </div>

                <p class="muted">Kelola data dengan alur yang lebih cepat, lebih rapi, dan lebih mudah dibaca.</p>
            </header>

            <section id="content-area" class="content-card">
                <p class="muted">Memuat dashboard...</p>
            </section>
        </main>
    </div>

    <div id="modal-root" class="modal-root hidden"></div>
    <div id="toast" class="toast hidden"></div>
</body>
</html>
