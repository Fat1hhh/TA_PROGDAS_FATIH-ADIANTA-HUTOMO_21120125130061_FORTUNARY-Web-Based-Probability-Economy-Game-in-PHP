<?php
session_start();
require_once __DIR__ . '/game.php';

$last = null;
if (isset($_SESSION['game'])) {
    $game = unserialize($_SESSION['game'], ['allowed_classes' => [Player::class, Game::class]]);
    if ($game instanceof Game) {
        $state = $game->toArray();
        $last = [
            'endingCode'  => $state['endingCode'] ?? '',
            'endingTitle' => $state['endingTitle'] ?? '',
            'routeLabel'  => $state['routeLabel'] ?? '',
            'month'       => $state['player']['month'] ?? '',
            'balance'     => $state['player']['balance'] ?? 0,
            'health'      => $state['player']['health'] ?? 0,
            'stress'      => $state['player']['stress'] ?? 0,
            'settings'    => $state['settings'] ?? [],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>FORTUNARY — Panduan Rute & Ending</title>
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
                    Panduan rute permainan & variasi ending (termasuk mode tantangan).
                </div>
            </div>
        </div>
        <div style="display:flex; gap:6px;">
            <a href="index.php" class="btn-reset" style="text-decoration:none; display:inline-block; width:auto; padding-inline:12px; text-align:center;">
                Kembali ke Game
            </a>
            <?php if (!empty($_SESSION['logged_in'])): ?>
                <form method="post" action="index.php" style="margin:0;">
                    <button class="btn-reset" type="submit" name="logout" value="1" style="width:auto; padding-inline:12px;">
                        Logout
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <main class="grid">
        <section class="card">
            <div class="card-header">
                <div class="card-title"><span class="card-title-pill"></span><span>Ringkasan Run Terakhir</span></div>
                <div class="card-subtitle">Jika kamu baru saja bermain, hasilnya tampil di sini.</div>
            </div>

            <?php if ($last): ?>
                <div class="status-grid" style="margin-bottom:8px;">
                    <div>
                        <div class="stat-label">Ending</div>
                        <div class="stat-value"><?= htmlspecialchars($last['endingTitle'] ?: 'Belum selesai'); ?></div>
                    </div>
                    <div>
                        <div class="stat-label">Rute Dominan</div>
                        <div class="stat-value"><?= htmlspecialchars($last['routeLabel'] ?: '-'); ?></div>
                    </div>
                </div>

                <div class="status-grid">
                    <div>
                        <div class="stat-label">Bulan</div>
                        <div class="stat-value"><?= (int) $last['month']; ?></div>
                    </div>
                    <div>
                        <div class="stat-label">Saldo</div>
                        <div class="stat-value">Rp <?= number_format($last['balance'], 0, ',', '.'); ?></div>
                    </div>
                </div>

                <div class="status-grid">
                    <div>
                        <div class="stat-label">Health</div>
                        <div class="stat-value"><?= (int) $last['health']; ?>/100</div>
                    </div>
                    <div>
                        <div class="stat-label">Stress</div>
                        <div class="stat-value"><?= (int) $last['stress']; ?>/100</div>
                    </div>
                </div>

                <div class="market-info" style="margin-top:10px;">
                    <div class="market-info-title">Tantangan Aktif Saat Itu</div>
                    <div class="market-info-text">
                        <?php
                        $s = $last['settings'] ?? [];
                        $aktif = [];
                        if (!empty($s['debt']))          $aktif[] = 'Cicilan Utang';
                        if (!empty($s['highInflation'])) $aktif[] = 'Inflasi Tinggi';
                        if (!empty($s['medicalRisk']))   $aktif[] = 'Risiko Kesehatan';
                        if (!empty($s['sideHustle']))    $aktif[] = 'Kerja Sampingan';
                        if (!empty($s['volatileJob']))   $aktif[] = 'Pekerjaan Fluktuatif';
                        echo $aktif ? htmlspecialchars(implode(' · ', $aktif)) : 'Tidak ada';
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="log-empty">Belum ada data. Mainkan game di <strong>index.php</strong> lalu kembali ke halaman ini 😊</div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-header">
                <div class="card-title"><span class="card-title-pill"></span><span>Rute Permainan</span></div>
                <div class="card-subtitle">Gaya main yang paling sering kamu pilih akan menjadi rute dominan.</div>
            </div>

            <div class="event-list" style="max-height:none;">
                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Rute Penabung Konservatif</div>
                        <div class="event-type event-type-bonus">Stabil</div>
                    </div>
                    <div class="event-meta">Fokus menabung. Risiko rendah, stres turun, pertumbuhan lambat namun aman.</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Rute Investor Hati-hati</div>
                        <div class="event-type event-type-bonus">Seimbang</div>
                    </div>
                    <div class="event-meta">Banyak investasi risiko rendah. Mengandalkan momen pasar bagus untuk konsisten tumbuh.</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Rute Penjudi Agresif</div>
                        <div class="event-type event-type-major">Berisiko</div>
                    </div>
                    <div class="event-meta">Sering ambil investasi risiko tinggi. Volatil, bisa meledak naik atau jatuh.</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Rute Life Enjoyer</div>
                        <div class="event-type event-type-minor">Wellbeing</div>
                    </div>
                    <div class="event-meta">Prioritas hiburan & kesehatan mental. Aman dari burnout, tapi keuangan lebih lambat naiknya.</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Rute Growth Mindset</div>
                        <div class="event-type event-type-bonus">Pengembangan</div>
                    </div>
                    <div class="event-meta">Sering ikut pelatihan. Luck naik untuk jangka panjang, konsekuensi jangka pendek: stres & biaya.</div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <div class="card-title"><span class="card-title-pill"></span><span>Ending Umum</span></div>
                <div class="card-subtitle">Ending yang bisa terjadi di mode standar.</div>
            </div>

            <div class="event-list" style="max-height:none;">
                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Bangkrut Berat (BANKRUPT)</div>
                        <div class="event-type event-type-major">Gagal</div>
                    </div>
                    <div class="event-meta">Saldo ≤ -Rp 500.000.</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Burnout Total (BURNOUT)</div>
                        <div class="event-type event-type-major">Gagal</div>
                    </div>
                    <div class="event-meta">Health ≤ 0.</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Mental Kolaps (STRESS_OUT)</div>
                        <div class="event-type event-type-major">Gagal</div>
                    </div>
                    <div class="event-meta">Stress ≥ 100.</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Pensiun Dini (EARLY_RETIRE)</div>
                        <div class="event-type event-type-bonus">Spesial</div>
                    </div>
                    <div class="event-meta">≤ bulan 18, saldo ≥ 20.000.000, Health ≥ 60, Stress ≤ 65.</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Stabil & Terkendali (FIN_FREE)</div>
                        <div class="event-type event-type-bonus">Baik</div>
                    </div>
                    <div class="event-meta">≤ bulan 24, saldo ≥ 15.000.000, Health ≥ 50, Stress ≤ 70.</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Bertahan Tapi Belum Bebas (SURVIVE)</div>
                        <div class="event-type event-type-minor">Netral</div>
                    </div>
                    <div class="event-meta">Bulan 24 tercapai, saldo ≥ 10.000.000 namun belum memenuhi <em>FIN_FREE</em>.</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Terombang-ambing Finansial (DRIFT)</div>
                        <div class="event-type event-type-minor">Kurang</div>
                    </div>
                    <div class="event-meta">Bulan 24 tercapai, saldo &lt; 10.000.000.</div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <div class="card-title"><span class="card-title-pill"></span><span>Ending Khusus — Mode Tantangan</span></div>
                <div class="card-subtitle">Akan muncul jika beberapa opsi tantangan dinyalakan.</div>
            </div>

            <div class="event-list" style="max-height:none;">
                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Juara Mode Tantangan (CHAMPION_HARDMODE)</div>
                        <div class="event-type event-type-bonus">Langka</div>
                    </div>
                    <div class="event-meta">Minimal 3 tantangan aktif dan memenuhi syarat <em>FIN_FREE</em> (≤ bulan 24).</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Bebas Cicilan! (DEBT_CLEAR)</div>
                        <div class="event-type event-type-bonus">Baik</div>
                    </div>
                    <div class="event-meta">Tantangan <strong>Cicilan Utang</strong> aktif, saldo ≥ 12.000.000 dan Stress ≤ 75 (≤ bulan 24).</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Penjinak Inflasi (INFLATION_TAMER)</div>
                        <div class="event-type event-type-bonus">Baik</div>
                    </div>
                    <div class="event-meta">Tantangan <strong>Inflasi Tinggi</strong> aktif, saldo ≥ 10.000.000 (≤ bulan 24).</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Sehat & Tangguh (HEALTHY_GROWTH)</div>
                        <div class="event-type event-type-bonus">Baik</div>
                    </div>
                    <div class="event-meta">Tantangan <strong>Risiko Kesehatan</strong> aktif, Health ≥ 70 (≤ bulan 24).</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Hustle Master (HUSTLE_MASTER)</div>
                        <div class="event-type event-type-bonus">Baik</div>
                    </div>
                    <div class="event-meta">Tantangan <strong>Kerja Sampingan</strong> aktif, Luck ≥ 60 & Stress ≤ 80 (≤ bulan 24).</div>
                </div>

                <div class="event-item">
                    <div class="event-row-top">
                        <div class="event-name">Rollercoaster Survivor (VOLATILITY_SURVIVOR)</div>
                        <div class="event-type event-type-minor">Khusus</div>
                    </div>
                    <div class="event-meta">Tantangan <strong>Pekerjaan Fluktuatif</strong> aktif, bertahan sampai bulan 24 dengan saldo &gt; 0.</div>
                </div>
            </div>

            <div class="footer-hint">Catatan: Ending khusus dievaluasi lebih dulu saat memenuhi kondisinya. Bila tidak, permainan mengikuti ending umum.</div>
        </section>
    </main>
</div>
</body>
</html>
