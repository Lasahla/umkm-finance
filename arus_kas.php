
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

// Filter tanggal
$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');

// Validasi tanggal
$validDate = function($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
};

if (!$validDate($tanggal_awal) || !$validDate($tanggal_akhir)) {
    die("Format tanggal tidak valid.");
}

if ($tanggal_awal > $tanggal_akhir) {
    die("Tanggal awal tidak boleh melebihi tanggal akhir.");
}

// Ambil akun kas dan bank
$stmt = $pdo->query("
    SELECT id, kode_akun, nama_akun, saldo_normal
    FROM accounts
    WHERE kategori = 'Aset'
      AND (
          nama_akun LIKE '%Kas%'
          OR nama_akun LIKE '%Bank%'
      )
    ORDER BY kode_akun ASC
");

$cashAccounts = $stmt->fetchAll();

$cashIds = array_column($cashAccounts, 'id');

$totalMasuk = 0;
$totalKeluar = 0;
$saldoAwal = 0;
$transactions = [];

// Mengambil transaksi kas dan bank
if (count($cashIds) > 0) {

    $placeholders = implode(',', array_fill(0, count($cashIds), '?'));

    // Saldo sebelum periode
    $sqlAwal = "
        SELECT
            jl.account_id,
            COALESCE(SUM(jl.debit), 0) AS total_debit,
            COALESCE(SUM(jl.kredit), 0) AS total_kredit
        FROM journal_lines jl
        JOIN journal_entries je
            ON je.id = jl.journal_id
        WHERE je.status = 'posted'
          AND je.tanggal < ?
          AND jl.account_id IN ($placeholders)
        GROUP BY jl.account_id
    ";

    $stmt = $pdo->prepare($sqlAwal);
    $stmt->execute(array_merge([$tanggal_awal], $cashIds));

    foreach ($stmt->fetchAll() as $row) {

        foreach ($cashAccounts as $account) {

            if ($account['id'] == $row['account_id']) {

                if ($account['saldo_normal'] === 'Debit') {
                    $saldoAwal += (float)$row['total_debit']
                        - (float)$row['total_kredit'];
                } else {
                    $saldoAwal += (float)$row['total_kredit']
                        - (float)$row['total_debit'];
                }

                break;
            }
        }
    }

    // Transaksi dalam periode
    $sql = "
        SELECT
            je.nomor_jurnal,
            je.tanggal,
            je.keterangan,
            jl.debit,
            jl.kredit,
            jl.account_id,
            a.nama_akun,
            a.saldo_normal
        FROM journal_lines jl
        JOIN journal_entries je
            ON je.id = jl.journal_id
        JOIN accounts a
            ON a.id = jl.account_id
        WHERE je.status = 'posted'
          AND je.tanggal BETWEEN ? AND ?
          AND jl.account_id IN ($placeholders)
        ORDER BY je.tanggal ASC, je.id ASC, jl.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(
        array_merge([$tanggal_awal, $tanggal_akhir], $cashIds)
    );

    $transactions = $stmt->fetchAll();

    foreach ($transactions as $tr) {

        if ($tr['saldo_normal'] === 'Debit') {
            $totalMasuk += (float)$tr['debit'];
            $totalKeluar += (float)$tr['kredit'];
        } else {
            $totalMasuk += (float)$tr['kredit'];
            $totalKeluar += (float)$tr['debit'];
        }
    }
}

$saldoAkhir = $saldoAwal + $totalMasuk - $totalKeluar;

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Arus Kas - UMKM Finance</title>

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

    <a href="laba_rugi.php">
        <i class="bi bi-graph-up me-2"></i> Laba Rugi
    </a>

    <a href="arus_kas.php" class="active">
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

        <h3 class="fw-bold">Laporan Arus Kas</h3>

        <p class="text-secondary">
            Laporan uang masuk dan keluar dari usaha UMKM.
        </p>

    </div>

    <!-- FILTER TANGGAL -->
    <div class="content-card mb-4 no-print">

        <h5 class="fw-bold mb-3">
            <i class="bi bi-calendar-range text-primary me-2"></i>
            Filter Periode
        </h5>

        <form method="GET" class="row g-3">

            <div class="col-md-5">

                <label class="form-label">Tanggal Awal</label>

                <input type="date"
                       name="tanggal_awal"
                       class="form-control"
                       value="<?= e($tanggal_awal) ?>"
                       required>

            </div>

            <div class="col-md-5">

                <label class="form-label">Tanggal Akhir</label>

                <input type="date"
                       name="tanggal_akhir"
                       class="form-control"
                       value="<?= e($tanggal_akhir) ?>"
                       required>

            </div>

            <div class="col-md-2 d-flex align-items-end">

                <button type="submit" class="btn btn-primary w-100">

                    <i class="bi bi-search me-1"></i>
                    Cari

                </button>

            </div>

        </form>

    </div>

    <!-- RINGKASAN -->
    <div class="row g-3 mb-4">

        <div class="col-md-3">

            <div class="content-card">

                <small class="text-secondary">Saldo Awal</small>

                <h5 class="fw-bold mt-2">
                    <?= rupiah($saldoAwal) ?>
                </h5>

            </div>

        </div>

        <div class="col-md-3">

            <div class="content-card">

                <small class="text-secondary">Uang Masuk</small>

                <h5 class="fw-bold text-success mt-2">
                    <?= rupiah($totalMasuk) ?>
                </h5>

            </div>

        </div>

        <div class="col-md-3">

            <div class="content-card">

                <small class="text-secondary">Uang Keluar</small>

                <h5 class="fw-bold text-danger mt-2">
                    <?= rupiah($totalKeluar) ?>
                </h5>

            </div>

        </div>

        <div class="col-md-3">

            <div class="content-card">

                <small class="text-secondary">Saldo Akhir</small>

                <h5 class="fw-bold text-primary mt-2">
                    <?= rupiah($saldoAkhir) ?>
                </h5>

            </div>

        </div>

    </div>

    <!-- LAPORAN -->
    <div class="content-card">

        <div class="text-center mb-4">

            <h4 class="fw-bold">LAPORAN ARUS KAS</h4>

            <h6>UMKM FINANCE</h6>

            <small class="text-secondary">
                Periode
                <?= date('d/m/Y', strtotime($tanggal_awal)) ?>
                -
                <?= date('d/m/Y', strtotime($tanggal_akhir)) ?>
            </small>

        </div>

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-light">

                    <tr>

                        <th>No</th>
                        <th>Tanggal</th>
                        <th>No. Jurnal</th>
                        <th>Keterangan</th>
                        <th>Akun Kas/Bank</th>
                        <th class="text-end">Uang Masuk</th>
                        <th class="text-end">Uang Keluar</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($transactions) > 0): ?>

                    <?php foreach ($transactions as $i => $tr): ?>

                        <?php
                        if ($tr['saldo_normal'] === 'Debit') {
                            $masuk = (float)$tr['debit'];
                            $keluar = (float)$tr['kredit'];
                        } else {
                            $masuk = (float)$tr['kredit'];
                            $keluar = (float)$tr['debit'];
                        }
                        ?>

                        <tr>

                            <td><?= $i + 1 ?></td>

                            <td>
                                <?= date('d/m/Y', strtotime($tr['tanggal'])) ?>
                            </td>

                            <td>
                                <?= e($tr['nomor_jurnal']) ?>
                            </td>

                            <td>
                                <?= e($tr['keterangan']) ?>
                            </td>

                            <td>
                                <?= e($tr['nama_akun']) ?>
                            </td>

                            <td class="text-end text-success">
                                <?= $masuk > 0 ? rupiah($masuk) : '-' ?>
                            </td>

                            <td class="text-end text-danger">
                                <?= $keluar > 0 ? rupiah($keluar) : '-' ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="7"
                            class="text-center text-secondary py-4">

                            Belum ada transaksi kas atau bank
                            pada periode ini.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

                <tfoot class="table-light fw-bold">

                    <tr>

                        <td colspan="5">TOTAL</td>

                        <td class="text-end text-success">
                            <?= rupiah($totalMasuk) ?>
                        </td>

                        <td class="text-end text-danger">
                            <?= rupiah($totalKeluar) ?>
                        </td>

                    </tr>

                </tfoot>

            </table>

        </div>

        <hr>

        <!-- SALDO AKHIR -->
        <div class="row justify-content-end">

            <div class="col-md-5">

                <table class="table table-bordered">

                    <tr>
                        <td>Saldo Awal</td>

                        <td class="text-end">
                            <?= rupiah($saldoAwal) ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Total Uang Masuk</td>

                        <td class="text-end text-success">
                            <?= rupiah($totalMasuk) ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Total Uang Keluar</td>

                        <td class="text-end text-danger">
                            <?= rupiah($totalKeluar) ?>
                        </td>
                    </tr>

                    <tr class="table-primary fw-bold">

                        <td>SALDO AKHIR</td>

                        <td class="text-end">
                            <?= rupiah($saldoAkhir) ?>
                        </td>

                    </tr>

                </table>

            </div>

        </div>

        <div class="d-flex justify-content-end mt-3 no-print">

            <button onclick="window.print()"
                    class="btn btn-outline-primary">

                <i class="bi bi-printer me-1"></i>
                Cetak Laporan

            </button>

        </div>

    </div>

</div>

</body>
</html>
