
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// Ringkasan akun: hanya jurnal yang sudah diposting
$sql = "
    SELECT
        a.kategori,
        a.kode_akun,
        SUM(CASE WHEN je.status = 'posted' THEN jl.debit ELSE 0 END) AS total_debit,
        SUM(CASE WHEN je.status = 'posted' THEN jl.kredit ELSE 0 END) AS total_kredit
    FROM accounts a
    LEFT JOIN journal_lines jl ON jl.account_id = a.id
    LEFT JOIN journal_entries je ON je.id = jl.journal_id
    GROUP BY a.id, a.kategori, a.kode_akun
";

$data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$kas = 0;
$pendapatan = 0;
$beban = 0;

foreach ($data as $row) {
    $debit = (float)$row['total_debit'];
    $kredit = (float)$row['total_kredit'];

    if ($row['kode_akun'] === '101') {
        $kas = $debit - $kredit;
    }

    if ($row['kategori'] === 'Pendapatan') {
        $pendapatan += $kredit - $debit;
    }

    if ($row['kategori'] === 'Beban') {
        $beban += $debit - $kredit;
    }
}

$laba = $pendapatan - $beban;

$total_jurnal = $pdo->query("
    SELECT COUNT(*)
    FROM journal_entries
    WHERE status = 'posted'
")->fetchColumn();

// Grafik pendapatan dan beban per bulan pada tahun ini
$tahun = date('Y');

$sqlGrafik = "
    SELECT
        MONTH(je.tanggal) AS bulan,
        SUM(
            CASE WHEN a.kategori = 'Pendapatan'
            THEN jl.kredit - jl.debit ELSE 0 END
        ) AS pendapatan,
        SUM(
            CASE WHEN a.kategori = 'Beban'
            THEN jl.debit - jl.kredit ELSE 0 END
        ) AS beban
    FROM journal_entries je
    JOIN journal_lines jl ON jl.journal_id = je.id
    JOIN accounts a ON a.id = jl.account_id
    WHERE je.status = 'posted'
      AND YEAR(je.tanggal) = ?
    GROUP BY MONTH(je.tanggal)
    ORDER BY MONTH(je.tanggal)
";

$stmtGrafik = $pdo->prepare($sqlGrafik);
$stmtGrafik->execute([$tahun]);
$hasilGrafik = $stmtGrafik->fetchAll(PDO::FETCH_ASSOC);

$namaBulan = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar',
    4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep',
    10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];

$grafikPendapatan = array_fill(0, 12, 0);
$grafikBeban = array_fill(0, 12, 0);

foreach ($hasilGrafik as $row) {
    $index = (int)$row['bulan'] - 1;
    $grafikPendapatan[$index] = (float)$row['pendapatan'];
    $grafikBeban[$index] = (float)$row['beban'];
}

// Lima jurnal terbaru
$sqlTerbaru = "
    SELECT nomor_jurnal, tanggal, keterangan
    FROM journal_entries
    WHERE status = 'posted'
    ORDER BY tanggal DESC, id DESC
    LIMIT 5
";

$jurnalTerbaru = $pdo->query($sqlTerbaru)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Dashboard - UMKM Finance</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
body {
    background: #f4f7fc;
    font-family: Arial, sans-serif;
}

.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    background: #102b50;
    color: white;
    padding: 25px 15px;
    overflow-y: auto;
}

.sidebar h4 {
    font-weight: bold;
    margin-bottom: 35px;
}

.sidebar a {
    display: block;
    color: #d6e2f3;
    text-decoration: none;
    padding: 13px 15px;
    margin-bottom: 7px;
    border-radius: 8px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #0d6efd;
    color: white;
}

.main {
    margin-left: 250px;
    padding: 30px;
}

.stat-card {
    border: none;
    border-radius: 13px;
    padding: 22px;
    box-shadow: 0 3px 15px rgba(0,0,0,.04);
    height: 100%;
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.chart-container {
    position: relative;
    height: 320px;
}

.table th {
    background: #f8f9fa;
    font-size: 14px;
}

.table td {
    font-size: 14px;
    vertical-align: middle;
}

@media(max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }

    .main {
        margin-left: 0;
        padding: 18px;
    }

    .chart-container {
        height: 260px;
    }
}
</style>
</head>

<body>

<div class="sidebar">

    <h4><i class="bi bi-wallet2"></i> UMKM Finance</h4>

    <small class="text-secondary">MENU UTAMA</small>

    <a href="index.php" class="active">
        <i class="bi bi-grid me-2"></i> Dashboard
    </a>

    <a href="pages/transaksi.php">
        <i class="bi bi-arrow-left-right me-2"></i> Transaksi
    </a>

    <a href="pages/jurnal.php">
        <i class="bi bi-journal-text me-2"></i> Jurnal Umum
    </a>

    <a href="pages/buku_besar.php">
        <i class="bi bi-book me-2"></i> Buku Besar
    </a>

    <a href="pages/akun.php">
        <i class="bi bi-list-ul me-2"></i> Data Akun
    </a>

    <br>

    <small class="text-secondary">LAPORAN</small>

    <a href="pages/neraca.php">
        <i class="bi bi-bar-chart me-2"></i> Neraca
    </a>

    <a href="pages/laba_rugi.php">
        <i class="bi bi-graph-up me-2"></i> Laba Rugi
    </a>

    <a href="pages/arus_kas.php">
        <i class="bi bi-cash-stack me-2"></i> Arus Kas
    </a>

    <hr>

    <a href="logout.php">
        <i class="bi bi-box-arrow-right me-2"></i> Logout
    </a>

</div>

<div class="main">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold">Dashboard</h3>
            <p class="text-secondary">
                Selamat datang, <?= htmlspecialchars($_SESSION['nama'] ?? 'Administrator') ?>!
            </p>
        </div>

        <span class="badge bg-primary p-2">Administrator</span>
    </div>

    <!-- KARTU RINGKASAN -->
    <div class="row g-3">

        <div class="col-md-6 col-xl-3">
            <div class="card stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-secondary">Saldo Kas</p>
                        <h5 class="fw-bold"><?= rupiah($kas) ?></h5>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-secondary">Pendapatan</p>
                        <h5 class="fw-bold"><?= rupiah($pendapatan) ?></h5>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-arrow-down-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-secondary">Total Beban</p>
                        <h5 class="fw-bold"><?= rupiah($beban) ?></h5>
                    </div>
                    <div class="stat-icon bg-danger-subtle text-danger">
                        <i class="bi bi-arrow-up-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-secondary">Laba / Rugi</p>
                        <h5 class="fw-bold"><?= rupiah($laba) ?></h5>
                    </div>
                    <div class="stat-icon bg-warning-subtle text-warning">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- GRAFIK DAN INFORMASI -->
    <div class="row mt-4 g-3">

        <div class="col-lg-8">
            <div class="card stat-card">
                <h5 class="fw-bold">Grafik Keuangan</h5>
                <p class="text-secondary">
                    Perbandingan pendapatan dan beban tahun <?= $tahun ?>.
                </p>

                <div class="chart-container">
                    <canvas id="grafikKeuangan"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card stat-card">
                <h5 class="fw-bold">Ringkasan Keuangan</h5>
                <p class="text-secondary">
                    Data berdasarkan jurnal yang sudah diposting.
                </p>

                <hr>

                <p class="mb-1">Total Jurnal Tercatat</p>
                <h3 class="fw-bold text-primary">
                    <?= $total_jurnal ?> Jurnal
                </h3>

                <p class="mb-1 mt-4">Metode pencatatan</p>
                <h6 class="fw-bold">Double Entry</h6>

                <p class="mb-1 mt-3">Prinsip akuntansi</p>
                <h6 class="fw-bold text-success">Debit = Kredit</h6>
            </div>
        </div>

    </div>

    <!-- JURNAL TERBARU -->
    <div class="card stat-card mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-1">Jurnal Terbaru</h5>
                <p class="text-secondary mb-0">
                    Lima jurnal terakhir yang sudah diposting.
                </p>
            </div>

            <a href="pages/jurnal.php" class="btn btn-primary btn-sm">
                Lihat Semua
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nomor Jurnal</th>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($jurnalTerbaru) > 0): ?>
                        <?php foreach ($jurnalTerbaru as $i => $jurnal): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars($jurnal['nomor_jurnal']) ?>
                                </td>
                                <td>
                                    <?= date('d-m-Y', strtotime($jurnal['tanggal'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($jurnal['keterangan']) ?>
                                </td>
                                <td>
                                    <span class="badge bg-success">Posted</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-4">
                                Belum ada jurnal yang diposting.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('grafikKeuangan');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_values($namaBulan)) ?>,
        datasets: [
            {
                label: 'Pendapatan',
                data: <?= json_encode(array_values($grafikPendapatan)) ?>,
                backgroundColor: '#198754',
                borderRadius: 5
            },
            {
                label: 'Beban',
                data: <?= json_encode(array_values($grafikBeban)) ?>,
                backgroundColor: '#dc3545',
                borderRadius: 5
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + Number(value).toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});
</script>

</body>
</html>
