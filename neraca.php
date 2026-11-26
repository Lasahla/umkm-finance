
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

// Ambil semua akun beserta total transaksi yang sudah diposting
$stmt = $pdo->query("
    SELECT
        a.*,
        COALESCE(SUM(jl.debit), 0) AS total_debit,
        COALESCE(SUM(jl.kredit), 0) AS total_kredit
    FROM accounts a
    LEFT JOIN journal_lines jl
        ON jl.account_id = a.id
    LEFT JOIN journal_entries je
        ON je.id = jl.journal_id
        AND je.status = 'posted'
    GROUP BY
        a.id,
        a.kode_akun,
        a.nama_akun,
        a.kategori,
        a.saldo_normal
    ORDER BY a.kode_akun ASC
");

$accounts = $stmt->fetchAll();

$aset = [];
$kewajiban = [];
$modal = [];

$totalAset = 0;
$totalKewajiban = 0;
$totalModal = 0;
$totalPendapatan = 0;
$totalBeban = 0;

// Menghitung saldo masing-masing akun
foreach ($accounts as $account) {

    $debit = (float)$account['total_debit'];
    $kredit = (float)$account['total_kredit'];

    if ($account['saldo_normal'] === 'Debit') {
        $saldo = $debit - $kredit;
    } else {
        $saldo = $kredit - $debit;
    }

    $account['saldo'] = $saldo;

    switch ($account['kategori']) {

        case 'Aset':
            $aset[] = $account;
            $totalAset += $saldo;
            break;

        case 'Kewajiban':
            $kewajiban[] = $account;
            $totalKewajiban += $saldo;
            break;

        case 'Modal':
            $modal[] = $account;

            // Modal kredit menambah ekuitas,
            // sedangkan akun modal debit seperti Prive menguranginya.
            if ($account['saldo_normal'] === 'Debit') {
                $totalModal -= $saldo;
            } else {
                $totalModal += $saldo;
            }
            break;

        case 'Pendapatan':
            $totalPendapatan += $saldo;
            break;

        case 'Beban':
            $totalBeban += $saldo;
            break;
    }
}

// Laba berjalan
$labaBersih = $totalPendapatan - $totalBeban;

// Modal akhir ditambah laba berjalan
$totalEkuitas = $totalModal + $labaBersih;

// Total kewajiban dan ekuitas
$totalPasiva = $totalKewajiban + $totalEkuitas;

// Selisih neraca
$selisih = $totalAset - $totalPasiva;

?>

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Neraca - UMKM Finance</title>

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

    <a href="neraca.php" class="active">
        <i class="bi bi-bar-chart me-2"></i> Neraca
    </a>

    <a href="laba_rugi.php">
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
        <h3 class="fw-bold">Laporan Neraca</h3>
        <p class="text-secondary">
            Laporan posisi keuangan UMKM Finance.
        </p>
    </div>

    <!-- RINGKASAN -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="content-card">
                <small class="text-secondary">Total Aset</small>
                <h5 class="fw-bold text-primary mt-2">
                    <?= rupiah($totalAset) ?>
                </h5>
            </div>
        </div>

        <div class="col-md-4">
            <div class="content-card">
                <small class="text-secondary">Total Kewajiban</small>
                <h5 class="fw-bold text-danger mt-2">
                    <?= rupiah($totalKewajiban) ?>
                </h5>
            </div>
        </div>

        <div class="col-md-4">
            <div class="content-card">
                <small class="text-secondary">Total Ekuitas</small>
                <h5 class="fw-bold text-success mt-2">
                    <?= rupiah($totalEkuitas) ?>
                </h5>
            </div>
        </div>

    </div>

    <!-- LAPORAN NERACA -->
    <div class="content-card">

        <div class="text-center mb-4">
            <h4 class="fw-bold">NERACA</h4>
            <h6>UMKM FINANCE</h6>
            <small class="text-secondary">
                Posisi Keuangan Saat Ini
            </small>
        </div>

        <div class="row g-4">

            <!-- ASET -->
            <div class="col-md-6">

                <h5 class="fw-bold text-primary mb-3">
                    A. ASET
                </h5>

                <table class="table table-sm">

                    <tbody>

                    <?php foreach ($aset as $account): ?>

                        <tr>
                            <td>
                                <?= e($account['kode_akun']) ?>
                                - <?= e($account['nama_akun']) ?>
                            </td>

                            <td class="text-end">
                                <?= rupiah($account['saldo']) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                    <tfoot class="fw-bold table-light">
                        <tr>
                            <td>Total Aset</td>
                            <td class="text-end">
                                <?= rupiah($totalAset) ?>
                            </td>
                        </tr>
                    </tfoot>

                </table>

            </div>

            <!-- KEWAJIBAN DAN EKUITAS -->
            <div class="col-md-6">

                <h5 class="fw-bold text-danger mb-3">
                    B. KEWAJIBAN
                </h5>

                <table class="table table-sm">

                    <tbody>

                    <?php foreach ($kewajiban as $account): ?>

                        <tr>
                            <td>
                                <?= e($account['kode_akun']) ?>
                                - <?= e($account['nama_akun']) ?>
                            </td>

                            <td class="text-end">
                                <?= rupiah($account['saldo']) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                    <tfoot class="fw-bold table-light">
                        <tr>
                            <td>Total Kewajiban</td>
                            <td class="text-end">
                                <?= rupiah($totalKewajiban) ?>
                            </td>
                        </tr>
                    </tfoot>

                </table>

                <h5 class="fw-bold text-success mb-3 mt-4">
                    C. EKUITAS / MODAL
                </h5>

                <table class="table table-sm">

                    <tbody>

                    <?php foreach ($modal as $account): ?>

                        <?php
                        $nilaiModal = $account['saldo_normal'] === 'Debit'
                            ? -$account['saldo']
                            : $account['saldo'];
                        ?>

                        <tr>
                            <td>
                                <?= e($account['kode_akun']) ?>
                                - <?= e($account['nama_akun']) ?>
                            </td>

                            <td class="text-end">
                                <?= rupiah($nilaiModal) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <tr>
                        <td>Laba Berjalan</td>
                        <td class="text-end">
                            <?= rupiah($labaBersih) ?>
                        </td>
                    </tr>

                    </tbody>

                    <tfoot class="fw-bold table-light">
                        <tr>
                            <td>Total Ekuitas</td>
                            <td class="text-end">
                                <?= rupiah($totalEkuitas) ?>
                            </td>
                        </tr>
                    </tfoot>

                </table>

                <table class="table table-bordered fw-bold">

                    <tr class="table-primary">
                        <td>Total Kewajiban + Ekuitas</td>
                        <td class="text-end">
                            <?= rupiah($totalPasiva) ?>
                        </td>
                    </tr>

                </table>

            </div>

        </div>

        <hr>

        <!-- STATUS NERACA -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

            <div>
                <h6 class="fw-bold mb-1">Pemeriksaan Neraca</h6>

                <?php if (abs($selisih) < 0.01): ?>

                    <span class="badge bg-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Neraca Seimbang
                    </span>

                <?php else: ?>

                    <span class="badge bg-danger">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Neraca Belum Seimbang
                    </span>

                <?php endif; ?>

                <p class="text-secondary small mt-2 mb-0">
                    Selisih: <?= rupiah($selisih) ?>
                </p>
            </div>

            <button onclick="window.print()"
                    class="btn btn-outline-primary no-print">

                <i class="bi bi-printer me-1"></i>
                Cetak Neraca

            </button>

        </div>

    </div>

</div>

</body>
</html>
