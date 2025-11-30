<?php
// game.php — FORTUNARY core logic
// - Difficulty lebih ramah pemain
// - Lucky events lebih sering & lebih positif
// - Max 1 event JSON/bulan
// - Ending khusus untuk mode tantangan aktif

session_start();

/**
 * ===========================
 *  Player
 * ===========================
 */
class Player {
    private string $name;
    private int $balance;
    private int $health;
    private int $stress;
    private int $luck;
    private int $month;

    public function __construct(string $name = "Pemain") {
        // Buff awal agar tidak cepat “habis”
        $this->name    = $name;
        $this->balance = 1200000; // saldo awal lebih besar
        $this->health  = 80;
        $this->stress  = 20;
        $this->luck    = 50;
        $this->month   = 1;
    }

    // ===== GETTERS =====
    public function getName(): string { return $this->name; }
    public function getBalance(): int { return $this->balance; }
    public function getHealth(): int  { return $this->health; }
    public function getStress(): int  { return $this->stress; }
    public function getLuck(): int    { return $this->luck; }
    public function getMonth(): int   { return $this->month; }

    // ===== SETTERS =====
    // saldo boleh minus (cek batas di ending)
    public function setBalance(int $value): void {
        $this->balance = $value;
    }
    public function setHealth(int $value): void {
        $this->health = max(0, min(100, $value));
    }
    public function setStress(int $value): void {
        $this->stress = max(0, min(100, $value));
    }
    public function setLuck(int $value): void {
        $this->luck = max(0, min(100, $value));
    }
    public function nextMonth(): void {
        $this->month++;
    }

    // helpers
    public function addBalance(int $value): void { $this->setBalance($this->balance + $value); }
    public function addHealth(int $value): void  { $this->setHealth($this->health + $value); }
    public function addStress(int $value): void  { $this->setStress($this->stress + $value); }
    public function addLuck(int $value): void    { $this->setLuck($this->luck + $value); }

    public function toArray(): array {
        return [
            "name"    => $this->name,
            "balance" => $this->balance,
            "health"  => $this->health,
            "stress"  => $this->stress,
            "luck"    => $this->luck,
            "month"   => $this->month,
        ];
    }
}


/**
 * ===========================
 *  Game
 * ===========================
 */
class Game {
    private Player $player;
    private string $marketState;
    private array  $logMessages = [];
    private bool   $gameOver = false;
    private string $statusMessage = "";
    private bool   $salaryBlocked = false; // efek PHK sementara

    private array $events = [];

    // tracking route
    private array  $routeStats = [
        'save'        => 0,
        'invest_low'  => 0,
        'invest_high' => 0,
        'entertain'   => 0,
        'train_skill' => 0,
    ];
    private string $endingCode  = "";
    private string $endingTitle = "";
    private string $routeLabel  = "";

    // goals
    private array $goalInfo = [];

    // tantangan
    private array $settings = [
        'debt'          => false,
        'highInflation' => false,
        'medicalRisk'   => false,
        'sideHustle'    => false,
        'volatileJob'   => false,
    ];

    // deskripsi pasar (untuk UI)
    private array $marketDescriptions = [
        'Boom'     => 'Ekonomi sangat panas, harga aset melesat. Peluang profit besar, tapi risiko gelembung pecah juga tinggi.',
        'Bullish'  => 'Pasar tumbuh positif dan stabil. Probabilitas keuntungan meningkat, risiko masih wajar.',
        'Sideways' => 'Pasar cenderung datar. Naik-turun sedikit, profit kecil dan stabil, cocok untuk strategi konservatif.',
        'Bearish'  => 'Pasar menurun. Investasi semakin berisiko, peluang rugi meningkat terutama untuk high-risk.',
        'Crash'    => 'Krisis berat. Mayoritas aset jatuh tajam, spekulasi sangat berbahaya tapi kadang ada rebound kecil.',
    ];

    public function __construct(?Player $player = null, ?array $settings = null) {
        $this->player = $player ?? new Player();

        if ($settings !== null) {
            $this->applySettingsArray($settings);
        }

        $this->loadEventsFromJson();
        $this->rollMarketState();
        $this->goalInfo = [
            "main" => "Dalam 24 bulan, capai saldo minimal Rp 15.000.000 dengan Health ≥ 50 dan Stress ≤ 70.",
            "alt"  => "Ending spesial: sebelum bulan 18, capai saldo ≥ Rp 20.000.000 dengan Health ≥ 60 dan Stress ≤ 65."
        ];
        $this->log("FORTUNARY dimulai. Selamat datang, " . $this->player->getName() . "!");
        $this->log("Goal utama: " . $this->goalInfo["main"]);
        $this->log("Goal tambahan: " . $this->goalInfo["alt"]);
    }

    // ===== GETTERS =====
    public function getPlayer(): Player            { return $this->player; }
    public function getMarketState(): string       { return $this->marketState; }
    public function getLog(): array                { return $this->logMessages; }
    public function isGameOver(): bool             { return $this->gameOver; }
    public function getStatusMessage(): string     { return $this->statusMessage; }
    public function getEvents(): array             { return $this->events; }
    public function getRouteLabel(): string        { return $this->routeLabel; }
    public function getEndingTitle(): string       { return $this->endingTitle; }
    public function getEndingCode(): string        { return $this->endingCode; }
    public function getGoalInfo(): array           { return $this->goalInfo; }
    public function getSettings(): array           { return $this->settings; }
    public function getMarketDescriptions(): array { return $this->marketDescriptions; }

    private function log(string $message): void {
        $this->logMessages[] = $message;
    }

    // ===== Tantangan =====
    private function applySettingsArray(array $settings): void {
        $this->settings['debt']          = !empty($settings['debt']);
        $this->settings['highInflation'] = !empty($settings['highInflation']);
        $this->settings['medicalRisk']   = !empty($settings['medicalRisk']);
        $this->settings['sideHustle']    = !empty($settings['sideHustle']);
        $this->settings['volatileJob']   = !empty($settings['volatileJob']);
    }
    private function describeSettings(): string {
        $enabled = [];
        if ($this->settings['debt'])          $enabled[] = "Cicilan utang bulanan";
        if ($this->settings['highInflation']) $enabled[] = "Inflasi tinggi";
        if ($this->settings['medicalRisk'])   $enabled[] = "Risiko kesehatan tinggi";
        if ($this->settings['sideHustle'])    $enabled[] = "Kerja sampingan";
        if ($this->settings['volatileJob'])   $enabled[] = "Pekerjaan fluktuatif";
        return $enabled ? implode(", ", $enabled) : "Tidak ada tantangan tambahan";
    }
    public function updateSettings(array $rawSettings): void {
        $this->applySettingsArray($rawSettings);
        $this->log("Pengaturan keuangan diubah: " . $this->describeSettings());
    }

    // ===== Events JSON =====
    private function loadEventsFromJson(): void {
        $file = __DIR__ . '/events.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $data = json_decode($json, true);
            if (is_array($data)) $this->events = $data;
        }
        if (empty($this->events)) $this->events = [];
    }

    // ===== Market =====
    private function rollMarketState(): void {
        $roll = random_int(1, 100);
        // Lebih jarang Crash, lebih sering Sideways/Bullish
        if ($roll <= 7)         $this->marketState = "Crash";
        elseif ($roll <= 22)    $this->marketState = "Bearish";
        elseif ($roll <= 62)    $this->marketState = "Sideways";
        elseif ($roll <= 87)    $this->marketState = "Bullish";
        else                    $this->marketState = "Boom";
        $this->log("Kondisi pasar bulan ini: " . $this->marketState);
    }

    // ===== Income =====
    private function applyMonthlyIncome(): void {
        if ($this->salaryBlocked) {
            $this->log("Anda terkena PHK sementara. Gaji bulan ini tidak masuk.");
            $this->salaryBlocked = false; // hanya 1 bulan
            return;
        }

        $baseSalary = 600000; // dinaikkan
        if ($this->settings['volatileJob']) {
            $shiftPercent = random_int(-15, 20); // -15% s/d +20%
            $delta        = (int) floor($baseSalary * ($shiftPercent / 100));
            $baseSalary   = max(0, $baseSalary + $delta);
            $this->log("Volatile job: gaji bergeser ~{$shiftPercent}% bulan ini.");
        }

        if ($this->player->getStress() >= 80) {
            $baseSalary = (int) floor($baseSalary * 0.9);
            $this->log("Stress tinggi → gaji -10%.");
        }

        $this->player->addBalance($baseSalary);
        $this->log("Gaji bulanan: +" . number_format($baseSalary, 0, ',', '.'));
    }

    // ===== Expenses =====
    private function applyMonthlyExpenses(): void {
        $month        = $this->player->getMonth();
        $baseExpenses = 280000; // diturunkan
        $inflation    = 10000 * max(0, $month - 1); // lebih pelan

        if ($this->settings['highInflation']) {
            $inflation = (int) floor($inflation * 1.4);
        }

        $randomExtra = random_int(0, 1) ? random_int(10000, 30000) : 0;
        $total = $baseExpenses + $inflation + $randomExtra;

        if ($this->settings['debt']) {
            $debtInstallment = 90000; // cicilan lebih kecil
            $total += $debtInstallment;
            $this->log("Cicilan utang: -" . number_format($debtInstallment, 0, ',', '.'));
        }
        if ($this->settings['medicalRisk'] && $this->player->getHealth() < 60) {
            $medicalExtra = 40000;
            $total += $medicalExtra;
            $this->log("Biaya kesehatan tambahan: -" . number_format($medicalExtra, 0, ',', '.'));
        }

        $this->player->addBalance(-$total);
        $this->log("Pengeluaran wajib: -" . number_format($total, 0, ',', '.'));

        if ($this->settings['sideHustle']) {
            $chance = 80;
            $roll   = random_int(1, 100);
            if ($roll <= $chance) {
                $extra = random_int(120000, 220000);
                $this->player->addBalance($extra);
                $this->player->addStress(3);
                $this->log("Side hustle berhasil: +" . number_format($extra, 0, ',', '.') . " (Stress +3).");
            } else {
                $this->player->addStress(1);
                $this->log("Side hustle sepi (Stress +1).");
            }
        }
    }

    /**
     * ===========================
     *  Turn Processing
     * ===========================
     */
    public function processTurn(string $action): void {
        if ($this->gameOver) {
            $this->statusMessage = "FORTUNARY sudah berakhir. Reset untuk main lagi.";
            return;
        }

        $this->log(str_repeat("-", 30));
        $this->log("Bulan " . $this->player->getMonth());

        $this->applyMonthlyIncome();
        $this->applyMonthlyExpenses();

        $this->handleAction($action);

        $this->triggerRandomEvents();   // max 1 per bulan
        $this->triggerLuckyAccident();  // lebih sering & positif

        $this->checkEndConditions();

        if (!$this->gameOver) {
            $this->player->nextMonth();
            $this->rollMarketState();
        }
    }

    // route
    private function recordRoute(string $key): void {
        if (isset($this->routeStats[$key])) $this->routeStats[$key]++;
    }
    private function determineRouteLabel(): string {
        $maxKey = null; $maxVal = -1;
        foreach ($this->routeStats as $k => $v) { if ($v > $maxVal) { $maxVal = $v; $maxKey = $k; } }
        return match ($maxKey) {
            'save'        => "Rute Penabung Konservatif",
            'invest_low'  => "Rute Investor Hati-hati",
            'invest_high' => "Rute Penjudi Agresif",
            'entertain'   => "Rute Life Enjoyer",
            'train_skill' => "Rute Growth Mindset",
            default       => "Rute Campuran"
        };
    }
    private function setEnding(string $code, string $title, string $message): void {
        $this->gameOver      = true;
        $this->endingCode    = $code;
        $this->endingTitle   = $title;
        $this->routeLabel    = $this->determineRouteLabel();
        $this->statusMessage = $message;
        $this->log("=== ENDING: {$this->endingTitle} ({$this->routeLabel}) ===");
    }

    /**
     * ===========================
     *  Actions
     * ===========================
     */
    private function handleAction(string $action): void {
        switch ($action) {
            case "save":         $this->recordRoute('save');         $this->handleSave();            break;
            case "invest_low":   $this->recordRoute('invest_low');   $this->handleInvestLowRisk();   break;
            case "invest_high":  $this->recordRoute('invest_high');  $this->handleInvestHighRisk();  break;
            case "entertain":    $this->recordRoute('entertain');    $this->handleEntertainment();   break;
            case "train_skill":  $this->recordRoute('train_skill');  $this->handleTraining();        break;
            default: $this->log("Tidak ada aksi yang dipilih."); break;
        }
    }

    private function handleSave(): void {
        $bonus = random_int(2, 4); // 2–4%
        $gain  = (int) floor($this->player->getBalance() * ($bonus / 100));
        $this->player->addBalance($gain);
        $this->player->addStress(-5);
        $this->player->addLuck(1);
        $this->log("MENABUNG: bunga {$bonus}% → +" . number_format($gain, 0, ',', '.'));
    }

    private function adjustWinChanceByMarket(int $base): int {
        $adjust = match ($this->marketState) {
            'Boom'     => 12,
            'Bullish'  => 7,
            'Sideways' => 0,
            'Bearish'  => -6,
            'Crash'    => -12,
            default    => 0,
        };
        return max(5, min(95, $base + $adjust));
    }

    private function handleInvestLowRisk(): void {
        $amount = 200000;
        if ($this->player->getBalance() < $amount) { $this->log("Saldo tidak cukup."); return; }
        $this->player->addBalance(-$amount);
        $this->log("Investasi risiko rendah: -" . number_format($amount, 0, ',', '.'));

        $baseWinChance = 70;
        $luckBonus     = (int) floor($this->player->getLuck() / 10);
        $winChance     = $this->adjustWinChanceByMarket($baseWinChance + $luckBonus);

        $roll = random_int(1, 100);
        if ($roll <= $winChance) {
            $multiplier = match ($this->marketState) {
                "Boom"     => random_int(7, 13) / 100,
                "Bullish"  => random_int(5, 10) / 100,
                "Sideways" => random_int(2, 7)  / 100,
                "Bearish"  => random_int(0, 5)  / 100,
                default    => random_int(0, 3)  / 100,
            };
            $profit = (int) floor($amount * (1 + $multiplier));
            $this->player->addBalance($profit);
            $this->log("Investasi rendah UNTUNG: +" . number_format($profit, 0, ',', '.') . " (market: {$this->marketState}).");
        } else {
            $lossPercent = random_int(2, 7);
            $loss        = (int) floor($amount * ($lossPercent / 100));
            $this->player->addBalance(-$loss);
            $this->player->addStress(5);
            $this->log("Investasi rendah RUGI {$lossPercent}%: -" . number_format($loss, 0, ',', '.') . ".");
        }
    }

    private function handleInvestHighRisk(): void {
        $amount = 260000; // sedikit lebih murah
        if ($this->player->getBalance() < $amount) { $this->log("Saldo tidak cukup."); return; }
        $this->player->addBalance(-$amount);
        $this->log("Investasi risiko tinggi: -" . number_format($amount, 0, ',', '.'));

        $baseWinChance = 50;
        $luckBonus     = (int) floor($this->player->getLuck() / 15);
        $winChance     = $this->adjustWinChanceByMarket($baseWinChance + $luckBonus);

        $roll = random_int(1, 100);
        if ($roll <= $winChance) {
            if     ($this->marketState === "Boom")     $multiplier = random_int(40, 120) / 100;
            elseif ($this->marketState === "Bullish")  $multiplier = random_int(30, 90)  / 100;
            elseif ($this->marketState === "Sideways") $multiplier = random_int(15, 60)  / 100;
            elseif ($this->marketState === "Bearish")  $multiplier = random_int(5, 40)   / 100;
            else                                        $multiplier = random_int(0, 25)   / 100; // Crash

            $profit = (int) floor($amount * (1 + $multiplier));
            $this->player->addBalance($profit);
            $this->player->addStress(10);
            $this->log("Investasi tinggi UNTUNG besar: +" . number_format($profit, 0, ',', '.') . " (market: {$this->marketState}).");
        } else {
            $lossPercent = random_int(25, 70); // lebih lunak
            $loss        = (int) floor($amount * ($lossPercent / 100));
            $this->player->addBalance(-$loss);
            $this->player->addStress(14);
            $this->log("Investasi tinggi RUGI {$lossPercent}%: -" . number_format($loss, 0, ',', '.') . ".");
        }
    }

    private function handleEntertainment(): void {
        $cost = 100000;
        if ($this->player->getBalance() < $cost) { $this->log("Saldo tidak cukup."); return; }
        $this->player->addBalance(-$cost);
        $this->player->addStress(-22);
        $this->player->addHealth(5);
        $this->log("Hiburan: Stress turun besar, Health naik.");
    }

    private function handleTraining(): void {
        $cost = 180000;
        if ($this->player->getBalance() < $cost) { $this->log("Saldo tidak cukup."); return; }
        $this->player->addBalance(-$cost);
        $this->player->addLuck(8);
        $this->player->addStress(9);
        $this->log("Pelatihan keuangan: Luck +8, Stress +9.");
    }

    /**
     * ===========================
     *  Events & Lucky
     * ===========================
     */
    // Max 1 event JSON per bulan, bonus lebih mudah dengan Luck
    private function triggerRandomEvents(): void {
        if (empty($this->events)) return;

        $events = $this->events;
        shuffle($events);

        $maxPerMonth = 1;
        $fired = 0;
        $luck  = $this->player->getLuck();

        foreach ($events as $ev) {
            if ($fired >= $maxPerMonth) break;

            $baseChance = (int) ($ev['chance'] ?? 0);
            $type       = $ev['type'] ?? 'minor';

            if ($type === 'bonus') {
                $baseChance += (int) floor($luck / 12); // +0..+8
            }
            if ($type === 'major' && $luck >= 40) {
                $baseChance -= 10; // jauh lebih jarang
            }

            $baseChance = max(1, min(100, $baseChance));
            $roll       = random_int(1, 100);

            if ($roll <= $baseChance) {
                if ($type === 'major') {
                    $avoidChance = (int) floor($luck / 2); // Luck 60 → 30% lolos
                    $avoidRoll   = random_int(1, 100);
                    if ($avoidRoll <= $avoidChance) {
                        $this->log("LUCKY! Menghindari dampak berat: {$ev['name']}.");
                        continue;
                    }
                }
                $this->applyEvent($ev);
                $fired++;
            }
        }
    }

    private function applyEvent(array $ev): void {
        $type = $ev["type"] ?? "minor";

        if ($type === "minor") {
            $this->player->addBalance(-(int)$ev["cost"]);
            $this->player->addHealth((int)$ev["health"]);
            $this->player->addStress((int)$ev["stress"]);
            $this->log("EVENT MINOR: {$ev['name']} | -" . number_format((int)$ev['cost'], 0, ',', '.'));
        } elseif ($type === "bonus") {
            $this->player->addBalance((int)$ev["reward"]);
            $this->player->addStress((int)$ev["stress"]);
            $this->log("EVENT BONUS: {$ev['name']} | +" . number_format((int)$ev['reward'], 0, ',', '.'));
        } elseif ($type === "major") {
            if (isset($ev["percent_loss"])) {
                $loss = (int) floor($this->player->getBalance() * ((int)$ev["percent_loss"] / 100));
                $this->player->addBalance(-$loss);
                $this->log("EVENT MAJOR: {$ev['name']} | Kehilangan {$ev['percent_loss']}%: -" . number_format($loss, 0, ',', '.'));
            }
            if (!empty($ev["months_loss"])) {
                $this->salaryBlocked = true; // 1 bulan
                $this->log("EVENT MAJOR: {$ev['name']} | Gaji tidak masuk 1 bulan.");
            }
            $this->player->addHealth((int)$ev["health"]);
            $this->player->addStress((int)$ev["stress"]);
        }
    }

    // Lucky tidak sengaja — lebih sering & positif
    private function triggerLuckyAccident(): void {
        $luck       = $this->player->getLuck();
        $baseChance = 12;                         // dulu 3 → 7, kini 12
        $bonus      = (int) floor($luck / 8);     // Luck 40 = +5
        if ($this->settings['debt']) $baseChance -= 1;

        $chance = max(5, min(40, $baseChance + $bonus)); // 5–40%
        $roll   = random_int(1, 100);
        if ($roll > $chance) return;

        $luckyEvents = [
            [ 'name' => 'Menemukan uang di jalan',               'type' => 'money',        'min' => 90000,  'max' => 260000 ],
            [ 'name' => 'Ditraktir makan & nongkrong',           'type' => 'stress_relief','stress' => -20, 'health' => 4   ],
            [ 'name' => 'Menang undian kecil struk belanja',     'type' => 'money_luck',   'amount' => 320000, 'luck' => 7  ],
            [ 'name' => 'Diskon besar tagihan listrik',          'type' => 'bill_cut',     'amount' => 100000 ],
            [ 'name' => 'Proyek sampingan klien dadakan',        'type' => 'side_project', 'amount' => 220000, 'stress' => -4 ],
            [ 'name' => 'Voucher belanja kebutuhan pokok',       'type' => 'voucher',      'amount' => 150000 ],
        ];

        $ev = $luckyEvents[array_rand($luckyEvents)];

        switch ($ev['type']) {
            case 'money':
                $amount = random_int($ev['min'], $ev['max']);
                $this->player->addBalance($amount);
                $this->player->addStress(-3);
                $this->log("LUCKY! {$ev['name']} | +" . number_format($amount, 0, ',', '.') . " & stress turun.");
                break;
            case 'stress_relief':
                $this->player->addStress($ev['stress']);
                $this->player->addHealth($ev['health']);
                $this->log("LUCKY! {$ev['name']} | Stress turun & Health naik.");
                break;
            case 'money_luck':
                $this->player->addBalance($ev['amount']);
                $this->player->addLuck($ev['luck']);
                $this->log("LUCKY! {$ev['name']} | +" . number_format($ev['amount'], 0, ',', '.') . " & Luck +" . $ev['luck'] . ".");
                break;
            case 'bill_cut':
                $this->player->addBalance($ev['amount']);
                $this->log("LUCKY! {$ev['name']} | Pengeluaran bulan ini terasa lebih ringan (+"
                    . number_format($ev['amount'], 0, ',', '.') . ").");
                break;
            case 'side_project':
                $this->player->addBalance($ev['amount']);
                $this->player->addStress($ev['stress']);
                $this->log("LUCKY! {$ev['name']} | +" . number_format($ev['amount'], 0, ',', '.') . " & Stress " . $ev['stress'] . ".");
                break;
            case 'voucher':
                $this->player->addBalance($ev['amount']);
                $this->log("LUCKY! {$ev['name']} | Menghemat sekitar +"
                    . number_format($ev['amount'], 0, ',', '.') . ".");
                break;
        }
    }

    /**
     * ===========================
     *  Endings (termasuk mode tantangan)
     * ===========================
     */
    private function checkEndConditions(): void {
        $balance = $this->player->getBalance();
        $health  = $this->player->getHealth();
        $stress  = $this->player->getStress();
        $month   = $this->player->getMonth();

        // Hitung tantangan aktif
        $s = $this->settings;
        $challengeCount = 0;
        foreach (['debt','highInflation','medicalRisk','sideHustle','volatileJob'] as $k) {
            if (!empty($s[$k])) $challengeCount++;
        }

        // — Ending gagal cepat
        if ($balance <= -500000) {
            $this->setEnding("BANKRUPT", "Bangkrut Berat",
                "Saldo Anda turun di bawah -Rp 500.000. Tanda beban utang terlalu tinggi.");
            return;
        }
        if ($health <= 0) {
            $this->setEnding("BURNOUT", "Burnout Total",
                "Anda memaksa diri sampai kesehatan jatuh ke 0. Keseimbangan itu penting.");
            return;
        }
        if ($stress >= 100) {
            $this->setEnding("STRESS_OUT", "Mental Kolaps",
                "Stress mencapai puncak. Tekanan finansial menghancurkan mental.");
            return;
        }

        // — Ending spesial cepat
        if ($month <= 18 && $balance >= 20000000 && $health >= 60 && $stress <= 65) {
            $this->setEnding("EARLY_RETIRE", "Pensiun Dini",
                "Bebas finansial lebih cepat dengan kondisi prima. Ending spesial!");
            return;
        }

        // — FIN_FREE + Ending khusus tantangan
        $meetsFinFree = ($balance >= 15000000 && $health >= 50 && $stress <= 70 && $month <= 24);
        if ($meetsFinFree) {
            if ($challengeCount >= 3) {
                $this->setEnding(
                    "CHAMPION_HARDMODE", "Juara Mode Tantangan",
                    "Minimal tiga tantangan aktif dan Anda tetap mencapai target kebebasan finansial."
                );
                return;
            }
            if (!empty($s['debt']) && $balance >= 12000000 && $stress <= 75) {
                $this->setEnding(
                    "DEBT_CLEAR", "Bebas Cicilan!",
                    "Stabil secara finansial meski dibebani cicilan bulanan."
                );
                return;
            }
            if (!empty($s['highInflation']) && $balance >= 10000000) {
                $this->setEnding(
                    "INFLATION_TAMER", "Penjinak Inflasi",
                    "Di tengah inflasi tinggi, Anda tetap mencapai target."
                );
                return;
            }
            if (!empty($s['medicalRisk']) && $health >= 70) {
                $this->setEnding(
                    "HEALTHY_GROWTH", "Sehat & Tangguh",
                    "Target finansial tercapai sembari menjaga kesehatan prima."
                );
                return;
            }
            $trainSaveCount = ($this->routeStats['train_skill'] ?? 0) + ($this->routeStats['save'] ?? 0);
            if (!empty($s['sideHustle']) && $this->player->getLuck() >= 60 && $stress <= 80 && $trainSaveCount >= 5) {
                $this->setEnding(
                    "HUSTLE_MASTER", "Hustle Master",
                    "Kerja sampingan konsisten menghasilkan berkat skill & disiplin."
                );
                return;
            }
            // FIN_FREE biasa
            $this->setEnding("FIN_FREE", "Stabil & Terkendali",
                "Target utama tercapai: saldo kuat, kesehatan cukup, stress terkendali.");
            return;
        }

        // — Waktu habis (bulan 24)
        if ($month >= 24) {
            if (!empty($s['volatileJob']) && $balance > 0) {
                $this->setEnding(
                    "VOLATILITY_SURVIVOR", "Rollercoaster Survivor",
                    "Dengan pekerjaan fluktuatif, Anda bertahan sampai akhir dan saldo tetap positif."
                );
                return;
            }
            if ($balance >= 10000000) {
                $this->setEnding("SURVIVE", "Bertahan Tapi Belum Bebas",
                    "Bertahan 24 bulan tanpa kolaps, namun belum benar-benar bebas finansial.");
            } else {
                $this->setEnding("DRIFT", "Terombang-ambing Finansial",
                    "24 bulan berlalu; perlu strategi lebih disiplin ke depan.");
            }
        }
    }

    // ===== Export state ke array (dipakai index.php / endings.php) =====
    public function toArray(): array {
        return [
            "player"             => $this->player->toArray(),
            "marketState"        => $this->marketState,
            "gameOver"           => $this->gameOver,
            "status"             => $this->statusMessage,
            "log"                => $this->logMessages,
            "events"             => $this->events,
            "routeLabel"         => $this->routeLabel,
            "endingCode"         => $this->endingCode,
            "endingTitle"        => $this->endingTitle,
            "goal"               => $this->goalInfo,
            "settings"           => $this->settings,
            "marketDescriptions" => $this->marketDescriptions,
        ];
    }
}
