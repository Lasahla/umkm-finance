
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value) {
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

// Ambil akun pendapatan dan beban
$stmt = $pdo->query("
    SELECT
        a.*,
        COALESCE(SUM(
            CASE
                WHEN je.status = 'posted'
                THEN jl.debit ELSE 0
            END
        ), 0) AS total_debit,
        COALESCE(SUM(
            CASE
                WHEN je.status = 'posted'
                THEN jl.kredit ELSE 0
            END
        ), 0) AS total_kredit
    FROM accounts a
    LEFT JOIN journal_lines jl
        ON jl.account_id = a.id
    LEFT JOIN journal_entries je
        ON je.id = jl.journal_id
    WHERE a.kategori IN ('Pendapatan', 'Beban')
    GROUP BY
        a.id,
        a.kode_akun,
        a.nama_akun,
        a.kategori,
        a.saldo_normal
    ORDER BY a.kode_akun ASC
");

$accounts = $stmt->fetchAll();

$pendapatan = [];
$beban = [];

$totalPendapatan = 0;
$totalBeban = 0;

// Hitung saldo akun
foreach ($accounts as $account) {

    $debit = (float)$account['total_debit'];
    $kredit = (float)$account['total_kredit'];

    if ($account['saldo_normal'] === 'Kredit') {
        $saldo = $kredit - $debit;
    } else {
        $saldo = $debit - $kredit;
    }

    $account['saldo'] = $saldo;

    if ($account['kategori'] === 'Pendapatan') {
        $pendapatan[] = $account;
        $totalPendapatan += $saldo;
    }

    if ($account['kategori'] === 'Beban') {
        $beban[] = $account;
        $totalBeban += $saldo;
    }
}

// Laba atau rugi bersih
$labaBersih = $totalPendapatan - $totalBeban;

?>

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Laba Rugi - UMKM Finance</title>

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

.content-card {
    background: white;
    border: none;
    border-radius: 13px;
    padding: 25px;
    box-shadow: 0 3px 15px rgba(0,0,0,.04);
}

@media(max-width:768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }

    .main {
        margin-left: 0;
        padding: 18px;
    }
}

@media print {
    .sidebar,
    .no-print {
        display: none !important;
    }

    .main {
        margin: 0;
        padding: 0;
    }

    body {
        background: white;
    }

    .content-card {
        box-shadow: none;
    }
}
</style>

</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <h4><i class="bi bi-wallet2"></i> UMKM Finance</h4>

    <small class="text-secondary">MENU UTAMA</small>

    <a href="../index.php">
        <i class="bi bi-grid me-2"></i> Dashboard
    </a>

    <a href="transaksi.php">
        <i class="bi bi-arrow-left-right me-2"></i> Transaksi
    </a>

    <a href="jurnal.php">
        <i class="bi bi-journal-text me-2"></i> Jurnal Umum
    </a>

    <a href="buku_besar.php">
        <i class="bi bi-book me-2"></i> Buku Besar
    </a>

    <a href="akun.php">
        <i class="bi bi-list-ul me-2"></i> Data Akun
    </a>

    <br>

    <small class="text-secondary">LAPORAN</small>

    <a href="neraca.php">
        <i class="bi bi-bar-chart me-2"></i> Neraca
    </a>

    <a href="laba_rugi.php" class="active">
        <i class="bi bi-graph-up me-2"></i> Laba Rugi
    </a>

    <a href="arus_kas.php">
        <i class="bi bi-cash-stack me-2"></i> Arus Kas
    </a>

    <hr>

    <a href="../logout.php">
        <i class="bi bi-box-arrow-right me-2"></i> Logout
    </a>

</div>

<!-- KONTEN -->
<div class="main">

    <div class="mb-4">
        <h3 class="fw-bold">Laporan Laba Rugi</h3>
        <p class="text-secondary">
            Laporan pendapatan, beban, dan hasil usaha UMKM.
        </p>
    </div>

    <!-- RINGKASAN -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="content-card">
                <small class="text-secondary">Total Pendapatan</small>
                <h5 class="fw-bold text-primary mt-2">
                    <?= rupiah($totalPendapatan) ?>
                </h5>
            </div>
        </div>

        <div class="col-md-4">
            <div class="content-card">
                <small class="text-secondary">Total Beban</small>
                <h5 class="fw-bold text-danger mt-2">
                    <?= rupiah($totalBeban) ?>
                </h5>
            </div>
        </div>

        <div class="col-md-4">
            <div class="content-card">
                <small class="text-secondary">
                    <?= $labaBersih >= 0 ? 'Laba Bersih' : 'Rugi Bersih' ?>
                </small>

                <h5 class="fw-bold <?= $labaBersih >= 0 ? 'text-success' : 'text-danger' ?> mt-2">
                    <?= rupiah(abs($labaBersih)) ?>
                </h5>
            </div>
        </div>

    </div>

    <!-- LAPORAN -->
    <div class="content-card">

        <div class="text-center mb-4">
            <h4 class="fw-bold">LAPORAN LABA RUGI</h4>
            <h6>UMKM FINANCE</h6>
            <small class="text-secondary">
                Laporan Keuangan Usaha
            </small>
        </div>

        <!-- PENDAPATAN -->
        <h5 class="fw-bold text-primary mb-3">
            A. PENDAPATAN
        </h5>

        <div class="table-responsive">

            <table class="table table-bordered align-middle">

                <thead class="table-light">
                    <tr>
                        <th width="15%">Kode Akun</th>
                        <th>Nama Akun</th>
                        <th class="text-end">Jumlah</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($pendapatan as $account): ?>

                    <tr>
                        <td><?= e($account['kode_akun']) ?></td>

                        <td><?= e($account['nama_akun']) ?></td>

                        <td class="text-end">
                            <?= rupiah($account['saldo']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (count($pendapatan) === 0): ?>
                    <tr>
                        <td colspan="3" class="text-center text-secondary">
                            Belum ada akun pendapatan.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

                <tfoot class="fw-bold table-light">
                    <tr>
                        <td colspan="2">TOTAL PENDAPATAN</td>
                        <td class="text-end">
                            <?= rupiah($totalPendapatan) ?>
                        </td>
                    </tr>
                </tfoot>

            </table>

        </div>

        <!-- BEBAN -->
        <h5 class="fw-bold text-danger mb-3 mt-4">
            B. BEBAN / PENGELUARAN
        </h5>

        <div class="table-responsive">

            <table class="table table-bordered align-middle">

                <thead class="table-light">
                    <tr>
                        <th width="15%">Kode Akun</th>
                        <th>Nama Akun</th>
                        <th class="text-end">Jumlah</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($beban as $account): ?>

                    <tr>
                        <td><?= e($account['kode_akun']) ?></td>

                        <td><?= e($account['nama_akun']) ?></td>

                        <td class="text-end">
                            <?= rupiah($account['saldo']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (count($beban) === 0): ?>
                    <tr>
                        <td colspan="3" class="text-center text-secondary">
                            Belum ada akun beban.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

                <tfoot class="fw-bold table-light">
                    <tr>
                        <td colspan="2">TOTAL BEBAN</td>
                        <td class="text-end">
                            <?= rupiah($totalBeban) ?>
                        </td>
                    </tr>
                </tfoot>

            </table>

        </div>

        <!-- LABA RUGI BERSIH -->
        <div class="table-responsive mt-4">

            <table class="table table-bordered">

                <tr class="fw-bold table-primary">
                    <td>
                        <?= $labaBersih >= 0
                            ? 'LABA BERSIH'
                            : 'RUGI BERSIH' ?>
                    </td>

                    <td class="text-end">
                        <?= rupiah(abs($labaBersih)) ?>
                    </td>
                </tr>

            </table>

        </div>

        <!-- CETAK -->
        <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-3">

            <div>
                <?php if ($labaBersih > 0): ?>
                    <span class="badge bg-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Usaha Mendapatkan Laba
                    </span>

                <?php elseif ($labaBersih < 0): ?>
                    <span class="badge bg-danger">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Usaha Mengalami Kerugian
                    </span>

                <?php else: ?>
                    <span class="badge bg-secondary">
                        Impas
                    </span>
                <?php endif; ?>
            </div>

            <button onclick="window.print()"
                    class="btn btn-outline-primary no-print">

                <i class="bi bi-printer me-1"></i>
                Cetak Laporan

            </button>

        </div>

    </div>

</div>

</body>
</html>
