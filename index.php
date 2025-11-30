<?php
require_once __DIR__ . '/game.php';

$usersFile = __DIR__ . '/users.json';

function loadUsers(string $file): array {
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveUsers(string $file, array $users): void {
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
}

function findUser(array $users, string $username): ?array {
    foreach ($users as $user) {
        if (isset($user['username']) && $user['username'] === $username) {
            return $user;
        }
    }
    return null;
}

$users = loadUsers($usersFile);
if (empty($users)) {
    $users[] = [
        'username' => 'demo',
        'password' => password_hash('demo123', PASSWORD_DEFAULT),
    ];
    saveUsers($usersFile, $users);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$loginError    = '';
$registerError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && empty($_SESSION['logged_in'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $loginError = "Username dan password tidak boleh kosong.";
    } else {
        $users = loadUsers($usersFile);
        $user  = findUser($users, $username);

        if (!$user || !password_verify($password, $user['password'])) {
            $loginError = "Username atau password salah.";
        } else {
            $_SESSION['logged_in']      = true;
            $_SESSION['username']       = $username;
            $_SESSION['tutorial_seen']  = false;
            unset($_SESSION['game']);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register']) && empty($_SESSION['logged_in'])) {
    $username = trim($_POST['reg_username'] ?? '');
    $password = $_POST['reg_password'] ?? '';
    $confirm  = $_POST['reg_password_confirm'] ?? '';

    if ($username === '' || $password === '' || $confirm === '') {
        $registerError = "Semua field registrasi wajib diisi.";
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        $registerError = "Username hanya boleh huruf, angka, underscore (3–20 karakter).";
    } elseif (strlen($password) < 4) {
        $registerError = "Password minimal 4 karakter.";
    } elseif ($password !== $confirm) {
        $registerError = "Konfirmasi password tidak sama.";
    } else {
        $users = loadUsers($usersFile);
        if (findUser($users, $username)) {
            $registerError = "Username sudah terdaftar, coba yang lain.";
        } else {
            $users[] = [
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ];
            saveUsers($usersFile, $users);

            $_SESSION['logged_in']      = true;
            $_SESSION['username']       = $username;
            $_SESSION['tutorial_seen']  = false;
            unset($_SESSION['game']);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

if (empty($_SESSION['logged_in'])):
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>FORTUNARY — Login / Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-shell">
    <div class="brand" style="margin-bottom: 18px;">
        <div class="brand-logo">
            <div class="brand-logo-inner">F</div>
        </div>
        <div>
            <div class="brand-text-title">FORTUNARY</div>
            <div class="brand-text-sub">
                Financial Probability Simulator &mdash; masuk atau daftar untuk memulai perjalananmu.
            </div>
        </div>
    </div>

    <div class="login-grid">
        <div class="login-card">
            <div class="login-title">Masuk</div>
            <div class="login-sub">
                Kamu bisa coba akun demo: <strong>demo / demo123</strong>
            </div>

            <?php if ($loginError !== ''): ?>
                <div class="login-error"><?= htmlspecialchars($loginError); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="login-form-group">
                    <div class="login-label">Username</div>
                    <input type="text" name="username" class="login-input"
                           placeholder="contoh: demo" autocomplete="off">
                </div>
                <div class="login-form-group">
                    <div class="login-label">Password</div>
                    <input type="password" name="password" class="login-input"
                           placeholder="password" autocomplete="off">
                </div>
                <button type="submit" name="login" value="1" class="login-submit">
                    Masuk ke FORTUNARY
                </button>
            </form>
        </div>

        <div class="login-card">
            <div class="login-title">Registrasi Akun Baru</div>
            <div class="login-sub">
                Buat identitas sendiri supaya progresmu terasa lebih personal.
            </div>

            <?php if ($registerError !== ''): ?>
                <div class="login-error"><?= htmlspecialchars($registerError); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="login-form-group">
                    <div class="login-label">Username</div>
                    <input type="text" name="reg_username" class="login-input"
                           placeholder="huruf/angka/underscore" autocomplete="off">
                </div>
                <div class="login-form-group">
                    <div class="login-label">Password</div>
                    <input type="password" name="reg_password" class="login-input"
                           placeholder="minimal 4 karakter" autocomplete="off">
                </div>
                <div class="login-form-group">
                    <div class="login-label">Konfirmasi Password</div>
                    <input type="password" name="reg_password_confirm" class="login-input"
                           placeholder="ketik ulang password" autocomplete="off">
                </div>
                <button type="submit" name="register" value="1" class="login-submit">
                    Daftar & Masuk
                </button>
            </form>

            <img
                src="https://images.pexels.com/photos/4968638/pexels-photo-4968638.jpeg?auto=compress&cs=tinysrgb&w=1200"
                alt="Ilustrasi perencanaan keuangan"
                class="login-side-image">
        </div>
    </div>

    <div class="login-footer">
        Akun disimpan di file <code>users.json</code> menggunakan format JSON sederhana (demo, bukan produksi).
    </div>
</div>
</body>
</html>
<?php
exit;
endif;

if (!isset($_SESSION['game'])) {
    $username = $_SESSION['username'] ?? 'Pemain';
    $player   = new Player($username);
    $game     = new Game($player);
    $_SESSION['game'] = serialize($game);
} else {
    $game = unserialize($_SESSION['game'], ['allowed_classes' => [Player::class, Game::class]]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tutorial_done'])) {
        $_SESSION['tutorial_seen'] = true;
    } elseif (!empty($_SESSION['tutorial_seen'])) {
        if (isset($_POST['update_settings'])) {
            $settingsRaw = [
                'debt'          => isset($_POST['opt_debt']),
                'highInflation' => isset($_POST['opt_inflation']),
                'medicalRisk'   => isset($_POST['opt_medical']),
                'sideHustle'    => isset($_POST['opt_sidehustle']),
                'volatileJob'   => isset($_POST['opt_volatile']),
            ];
            $game->updateSettings($settingsRaw);
        }

        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            $game->processTurn($action);
        }

        if (isset($_POST['reset_game'])) {
            unset($_SESSION['game']);
            $username = $_SESSION['username'] ?? 'Pemain';
            $player   = new Player($username);
            $game     = new Game($player);
        }

        $_SESSION['game'] = serialize($game);
    }
}

$stateArray         = $game->toArray();
$player             = $stateArray["player"];
$events             = $stateArray["events"];
$goal               = $stateArray["goal"] ?? [];
$settings           = $stateArray["settings"] ?? [];
$marketDescriptions = $stateArray["marketDescriptions"] ?? [];

if (empty($_SESSION['tutorial_seen'])):
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>FORTUNARY - Tutorial</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="tutorial-shell">
    <header class="header" style="margin-bottom:16px;">
        <div class="brand">
            <div class="brand-logo">
                <div class="brand-logo-inner">F</div>
            </div>
            <div>
                <div class="brand-text-title">FORTUNARY</div>
                <div class="brand-text-sub">
                    Financial Probability Simulator &mdash; tutorial singkat sebelum bermain.
                </div>
            </div>
        </div>
        <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
            <div class="user-chip">
                Masuk sebagai: <strong><?= htmlspecialchars($_SESSION['username']); ?></strong>
            </div>
            <form method="post" style="margin:0;">
                <button type="submit" name="logout" value="1"
                        class="btn-reset" style="width:auto; padding-inline:12px;">
                    Logout
                </button>
            </form>
        </div>
    </header>

    <main class="tutorial-grid">
        <section class="card tutorial-card">
            <div class="tutorial-title">Cara main FORTUNARY dalam 3 poin</div>

            <div class="tutorial-section-title">1. Objektif utama</div>
            <ul class="tutorial-list">
                <li>
                    <span class="tutorial-badge">Main Goal</span>
                    <span><?= htmlspecialchars($goal["main"] ?? ''); ?></span>
                </li>
                <li>
                    <span class="tutorial-badge">Special Ending</span>
                    <span><?= htmlspecialchars($goal["alt"] ?? ''); ?></span>
                </li>
                <li>
                    <span class="tutorial-badge">Limit Waktu</span>
                    <span>Permainan berakhir maksimal di bulan ke-24.</span>
                </li>
            </ul>

            <div class="tutorial-section-title">2. Aksi setiap bulan</div>
            <ul class="tutorial-list">
                <li><strong>Menabung</strong> – aman, bunga kecil, stress turun sedikit.</li>
                <li><strong>Investasi Risiko Rendah</strong> – peluang untung lumayan, rugi masih terbatas.</li>
                <li><strong>Investasi Risiko Tinggi</strong> – bisa sangat kaya, bisa juga sangat sengsara.</li>
                <li><strong>Hiburan</strong> – buang uang untuk nurunin stress & jaga health.</li>
                <li><strong>Pelatihan Keuangan</strong> – tingkatkan <em>Luck</em>, tapi bikin capek (stress naik).</li>
            </ul>

            <div class="tutorial-section-title">3. Kondisi pasar & Lucky</div>
            <ul class="tutorial-list">
                <li>
                    Setiap bulan, kondisi pasar berubah (Boom / Bullish / Sideways / Bearish / Crash)
                    dan mempengaruhi hasil investasi.
                </li>
                <li>
                    Atribut <strong>Luck</strong> meningkatkan peluang munculnya
                    <span class="tutorial-badge">LUCKY!</span> seperti nemu uang, undian kecil, atau ditraktir.
                </li>
                <li>
                    Anda bisa menyalakan tantangan tambahan seperti <em>utang, inflasi tinggi, kerja sampingan</em>, dll
                    di panel pengaturan dalam game.
                </li>
            </ul>

            <form method="post" style="margin-top:12px;">
                <button type="submit" name="tutorial_done" value="1" class="tutorial-start-btn">
                    Mengerti, mulai bermain
                </button>
            </form>
        </section>

        <section class="card tutorial-card">
            <div class="tutorial-section-title">Ringkasan kondisi pasar</div>
            <div class="market-legend">
                <?php foreach ($marketDescriptions as $name => $desc): ?>
                    <div class="market-legend-item">
                        <div class="market-legend-state"><?= htmlspecialchars($name); ?></div>
                        <div class="market-legend-desc"><?= htmlspecialchars($desc); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="tutorial-section-title" style="margin-top:10px;">Tips singkat</div>
            <ul class="tutorial-list">
                <li>Jaga <strong>Health</strong> jangan sampai 0 dan <strong>Stress</strong> jangan sampai 100.</li>
                <li>Gunakan investasi risiko tinggi hanya ketika saldo & kondisi pasar mendukung.</li>
                <li>Coba berbagai kombinasi pengaturan keuangan untuk melihat variasi ending.</li>
            </ul>
        </section>
    </main>
</div>
</body>
</html>
<?php
exit;
endif;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>FORTUNARY - Alpha Test</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page-shell">

    <header class="header">
        <div class="brand">
            <div class="brand-logo">
                <div class="brand-logo-inner">F</div>
            </div>
            <div>
                <div class="brand-text-title">FORTUNARY</div>
                <div class="brand-text-sub">
                    Financial Probability Simulator &mdash; uji insting dan strategi pengelolaan risiko.
                </div>
            </div>
        </div>
        <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
            <div class="user-chip">
                Masuk sebagai: <strong><?= htmlspecialchars($_SESSION['username']); ?></strong>
            </div>
            <form method="post" style="margin:0; display:flex; gap:6px;">
                <button type="submit" name="reset_game" value="1"
                        class="btn-reset" style="width:auto; padding-inline:12px;">
                    Reset Game
                </button>
                <button type="submit" name="logout" value="1"
                        class="btn-reset" style="width:auto; padding-inline:12px;">
                    Logout
                </button>
            </form>
        </div>
    </header>

    <main class="grid">

        <section class="card">
            <div class="card-header">
                <div class="card-title">
                    <span class="card-title-pill"></span>
                    <span>Profil Bulan Ini</span>
                </div>
                <div class="card-subtitle">
                    <?= htmlspecialchars($player["name"]); ?> &middot; Bulan <?= $player["month"]; ?>
                </div>
            </div>

            <div class="status-grid">
                <div>
                    <div class="stat-label">Saldo</div>
                    <div class="stat-value">
                        Rp <?= number_format($player["balance"], 0, ',', '.'); ?>
                    </div>
                </div>
                <div>
                    <div class="stat-label">Kondisi Pasar</div>
                    <div class="stat-value stat-highlight">
                        <?= $stateArray["marketState"]; ?>
                    </div>
                </div>
            </div>

            <div class="market-info">
                <div class="market-info-title">Penjelasan kondisi pasar saat ini</div>
                <div class="market-info-text">
                    <?= htmlspecialchars($marketDescriptions[$stateArray["marketState"]] ?? ''); ?>
                </div>
            </div>

            <div class="settings-box">
                <div class="settings-header-row">
                    <div class="settings-title">Tantangan Keuangan</div>
                    <div class="settings-subtitle">
                        Pilih hambatan yang ingin kamu aktifkan untuk membuat simulasi lebih menantang.
                    </div>
                </div>

                <form method="post" class="settings-form">
                    <div class="settings-items">

                        <label class="settings-item">
                            <input type="checkbox" name="opt_debt" <?= !empty($settings['debt']) ? 'checked' : ''; ?>>
                            <div class="settings-item-body">
                                <div class="settings-item-top">
                                    <span class="settings-item-title">Cicilan Utang</span>
                                    <span class="settings-chip chip-red">Tetap</span>
                                </div>
                                <div class="settings-item-text">
                                    Biaya cicilan bulanan yang selalu muncul, mengurangi ruang gerak keuanganmu.
                                </div>
                            </div>
                        </label>

                        <label class="settings-item">
                            <input type="checkbox" name="opt_inflation" <?= !empty($settings['highInflation']) ? 'checked' : ''; ?>>
                            <div class="settings-item-body">
                                <div class="settings-item-top">
                                    <span class="settings-item-title">Inflasi Tinggi</span>
                                    <span class="settings-chip chip-yellow">Tekanan Harga</span>
                                </div>
                                <div class="settings-item-text">
                                    Biaya hidup naik lebih cepat tiap bulan, memaksa strategi pengeluaran yang lebih ketat.
                                </div>
                            </div>
                        </label>

                        <label class="settings-item">
                            <input type="checkbox" name="opt_medical" <?= !empty($settings['medicalRisk']) ? 'checked' : ''; ?>>
                            <div class="settings-item-body">
                                <div class="settings-item-top">
                                    <span class="settings-item-title">Risiko Kesehatan Tinggi</span>
                                    <span class="settings-chip chip-blue">Health</span>
                                </div>
                                <div class="settings-item-text">
                                    Saat Health rendah, akan muncul biaya cek kesehatan tambahan yang menggerus saldo.
                                </div>
                            </div>
                        </label>

                        <label class="settings-item">
                            <input type="checkbox" name="opt_sidehustle" <?= !empty($settings['sideHustle']) ? 'checked' : ''; ?>>
                            <div class="settings-item-body">
                                <div class="settings-item-top">
                                    <span class="settings-item-title">Kerja Sampingan</span>
                                    <span class="settings-chip chip-green">Extra Income</span>
                                </div>
                                <div class="settings-item-text">
                                    Berpotensi memberi penghasilan tambahan, tapi menambah Stress dan bisa kadang zonk.
                                </div>
                            </div>
                        </label>

                        <label class="settings-item">
                            <input type="checkbox" name="opt_volatile" <?= !empty($settings['volatileJob']) ? 'checked' : ''; ?>>
                            <div class="settings-item-body">
                                <div class="settings-item-top">
                                    <span class="settings-item-title">Pekerjaan Fluktuatif</span>
                                    <span class="settings-chip chip-purple">Gaji Acak</span>
                                </div>
                                <div class="settings-item-text">
                                    Gaji bulanan bisa naik atau turun drastis, mensimulasikan pekerjaan yang sangat tidak stabil.
                                </div>
                            </div>
                        </label>

                    </div>

                    <button type="submit" name="update_settings" value="1" class="settings-button">
                        Terapkan Pengaturan Tantangan
                    </button>
                </form>
            </div>

            <div class="progress-group">
                <div class="progress-label-row">
                    <span>Health</span>
                    <span><?= $player["health"]; ?> / 100</span>
                </div>
                <div class="progress-shell">
                    <div class="progress-inner progress-health"
                         style="width: <?= $player["health"]; ?>%;"></div>
                </div>
            </div>

            <div class="progress-group">
                <div class="progress-label-row">
                    <span>Stress</span>
                    <span><?= $player["stress"]; ?> / 100</span>
                </div>
                <div class="progress-shell">
                    <div class="progress-inner progress-stress"
                         style="width: <?= $player["stress"]; ?>%;"></div>
                </div>
            </div>

            <div class="progress-group">
                <div class="progress-label-row">
                    <span>Luck</span>
                    <span><?= $player["luck"]; ?> / 100</span>
                </div>
                <div class="progress-shell">
                    <div class="progress-inner progress-luck"
                         style="width: <?= $player["luck"]; ?>%;"></div>
                </div>
            </div>

            <div class="status-strip <?= $stateArray["gameOver"] ? 'status-strip-bad' : ''; ?>">
                <div class="status-icon <?= $stateArray["gameOver"] ? 'status-icon-bad' : ''; ?>">
                    <?= $stateArray["gameOver"] ? '!' : '★'; ?>
                </div>
                <div>
                    <?php if ($stateArray["gameOver"]): ?>
                        <div class="status-text-main">
                            <?= htmlspecialchars($stateArray["endingTitle"] ?: 'Game Over'); ?>
                        </div>
                        <div class="status-text-sub">
                            <?= htmlspecialchars($stateArray["status"]); ?>
                        </div>
                        <?php if (!empty($stateArray["routeLabel"])): ?>
                            <div class="status-text-sub">
                                Rute dominan kamu: <strong><?= htmlspecialchars($stateArray["routeLabel"]); ?></strong>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="status-text-main">
                            Tujuan utama FORTUNARY
                        </div>
                        <div class="status-text-sub">
                            <?= htmlspecialchars($goal["main"] ?? ''); ?>
                        </div>
                        <?php if (!empty($goal["alt"])): ?>
                            <div class="status-text-sub">
                                Ending spesial: <?= htmlspecialchars($goal["alt"]); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post" class="actions">
                <button type="submit" name="action" value="save"
                        class="btn btn-main"
                        <?= $stateArray["gameOver"] ? 'disabled' : ''; ?>>
                    <span class="btn-label">
                        <div class="btn-title">Menabung</div>
                        <div class="btn-desc">Risiko rendah, bunga kecil, stabil dan menenangkan.</div>
                    </span>
                    <span class="btn-tag">safe & steady</span>
                </button>

                <button type="submit" name="action" value="invest_low"
                        class="btn btn-risk-low"
                        <?= $stateArray["gameOver"] ? 'disabled' : ''; ?>>
                    <span class="btn-label">
                        <div class="btn-title">Investasi Risiko Rendah</div>
                        <div class="btn-desc">Peluang untung cukup besar, rugi masih terbatas.</div>
                    </span>
                    <span class="btn-tag">±60% win rate</span>
                </button>

                <button type="submit" name="action" value="invest_high"
                        class="btn btn-risk-high"
                        <?= $stateArray["gameOver"] ? 'disabled' : ''; ?>>
                    <span class="btn-label">
                        <div class="btn-title">Investasi Risiko Tinggi</div>
                        <div class="btn-desc">Potensi cuan besar, tapi siap mental kalau anjlok.</div>
                    </span>
                    <span class="btn-tag">high variance</span>
                </button>

                <button type="submit" name="action" value="entertain"
                        class="btn btn-light"
                        <?= $stateArray["gameOver"] ? 'disabled' : ''; ?>>
                    <span class="btn-label">
                        <div class="btn-title">Hiburan</div>
                        <div class="btn-desc">Habiskan sedikit uang demi kesehatan mental.</div>
                    </span>
                    <span class="btn-tag">stress relief</span>
                </button>

                <button type="submit" name="action" value="train_skill"
                        class="btn btn-light"
                        <?= $stateArray["gameOver"] ? 'disabled' : ''; ?>>
                    <span class="btn-label">
                        <div class="btn-title">Pelatihan Keuangan</div>
                        <div class="btn-desc">Upgrade luck & pemahaman, tapi melelahkan.</div>
                    </span>
                    <span class="btn-tag">long-term edge</span>
                </button>
            </form>

            <div class="footer-hint">
                Tip: nyalakan beberapa pengaturan tantangan, lalu bandingkan hasil permainanmu.
                Perhatikan juga kondisi pasar dan event <strong>LUCKY!</strong> yang muncul secara acak.
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <div class="card-title">
                    <span class="card-title-pill"></span>
                    <span>Timeline & Event</span>
                </div>
                <div class="card-subtitle">
                    Rekap kejadian tiap bulan & daftar event dari <code>events.json</code>.
                </div>
            </div>

            <div class="card-subtitle" style="margin-bottom:6px;">
                Log Peristiwa
            </div>
            <div class="log-box">
                <?php if (!empty($stateArray["log"])): ?>
                    <?php foreach ($stateArray["log"] as $entry): ?>
                        <div class="log-entry"><?= htmlspecialchars($entry); ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="log-entry log-empty">Belum ada log. Pilih aksi untuk memulai simulasi.</div>
                <?php endif; ?>
            </div>

            <div class="card-subtitle" style="margin-top:10px; margin-bottom:4px;">
                Daftar Event & Probabilitas (dari JSON)
            </div>

            <div class="event-list">
                <?php if (!empty($events)): ?>
                    <?php foreach ($events as $ev): ?>
                        <?php
                        $type      = $ev['type'];
                        $typeClass = 'event-type-minor';
                        $typeLabel = 'Minor';

                        if ($type === 'bonus') {
                            $typeClass = 'event-type-bonus';
                            $typeLabel = 'Bonus';
                        } elseif ($type === 'major') {
                            $typeClass = 'event-type-major';
                            $typeLabel = 'Major';
                        }
                        ?>
                        <div class="event-item">
                            <div class="event-row-top">
                                <div class="event-name"><?= htmlspecialchars($ev['name']); ?></div>
                                <div class="event-type <?= $typeClass; ?>">
                                    <?= $typeLabel; ?>
                                </div>
                            </div>
                            <div class="event-meta">
                                Peluang muncul: <?= $ev['chance']; ?>%
                                <?php if (isset($ev['cost'])): ?>
                                    &middot; Biaya: -Rp <?= number_format($ev['cost'], 0, ',', '.'); ?>
                                <?php endif; ?>
                                <?php if (isset($ev['reward'])): ?>
                                    &middot; Reward: +Rp <?= number_format($ev['reward'], 0, ',', '.'); ?>
                                <?php endif; ?>
                                <?php if (isset($ev['percent_loss'])): ?>
                                    &middot; Kerugian: <?= $ev['percent_loss']; ?>% saldo
                                <?php endif; ?>
                                <?php if (isset($ev['months_loss'])): ?>
                                    &middot; Gaji hilang: <?= $ev['months_loss']; ?> bulan
                                <?php endif; ?>
                                <?php if (isset($ev['health']) && $ev['health'] != 0): ?>
                                    &middot; Health: <?= $ev['health']; ?>
                                <?php endif; ?>
                                <?php if (isset($ev['stress']) && $ev['stress'] != 0): ?>
                                    &middot; Stress: <?= $ev['stress']; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="log-empty">
                        events.json kosong atau tidak terbaca.
                    </div>
                <?php endif; ?>
            </div>

            <div class="card-subtitle" style="margin-top:10px; margin-bottom:4px;">
                Kondisi Pasar & Penjelasannya
            </div>
            <div class="market-legend">
                <?php foreach ($marketDescriptions as $name => $desc): ?>
                    <div class="market-legend-item">
                        <div class="market-legend-state"><?= htmlspecialchars($name); ?></div>
                        <div class="market-legend-desc"><?= htmlspecialchars($desc); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <img
                src="https://images.pexels.com/photos/3183150/pexels-photo-3183150.jpeg?auto=compress&cs=tinysrgb&w=1200"
                alt="Ilustrasi tim menganalisis keuangan"
                class="game-side-image">

            <div class="footer-hint">
                List ini adalah interpretasi dari file <code>events.json</code> dan kondisi pasar,
                sehingga pemain bisa memahami sumber risiko dan peluang.
            </div>
        </section>
    </main>
</div>
</body>
</html>
