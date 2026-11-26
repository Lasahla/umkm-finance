
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

// Ambil seluruh jurnal
$stmt = $pdo->query("
    SELECT
        je.id,
        je.nomor_jurnal,
        je.tanggal,
        je.keterangan,
        je.status,
        COALESCE(SUM(jl.debit), 0) AS total_debit,
        COALESCE(SUM(jl.kredit), 0) AS total_kredit
    FROM journal_entries je
    LEFT JOIN journal_lines jl ON jl.journal_id = je.id
    GROUP BY
        je.id,
        je.nomor_jurnal,
        je.tanggal,
        je.keterangan,
        je.status
    ORDER BY je.tanggal DESC, je.id DESC
");

$jurnals = $stmt->fetchAll();

// Ambil detail jurnal
$details = [];

foreach ($jurnals as $journal) {
    $stmt = $pdo->prepare("
        SELECT
            a.kode_akun,
            a.nama_akun,
            jl.debit,
            jl.kredit
        FROM journal_lines jl
        JOIN accounts a ON a.id = jl.account_id
        WHERE jl.journal_id = ?
        ORDER BY jl.id ASC
    ");

    $stmt->execute([$journal['id']]);
    $details[$journal['id']] = $stmt->fetchAll();
}

$total_semua_debit = 0;
$total_semua_kredit = 0;

foreach ($jurnals as $journal) {
    $total_semua_debit += (float)$journal['total_debit'];
    $total_semua_kredit += (float)$journal['total_kredit'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Jurnal Umum - UMKM Finance</title>

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

.journal-detail {
    background: #f8faff;
    border-radius: 10px;
    padding: 15px;
    margin-top: 12px;
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
</style>
</head>

<body>

<div class="sidebar">

    <h4><i class="bi bi-wallet2"></i> UMKM Finance</h4>

    <small class="text-secondary">MENU UTAMA</small>

    <a href="../index.php">
        <i class="bi bi-grid me-2"></i> Dashboard
    </a>

    <a href="transaksi.php">
        <i class="bi bi-arrow-left-right me-2"></i> Transaksi
    </a>

    <a href="jurnal.php" class="active">
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

    <a href="arus_kas.php">
        <i class="bi bi-cash-stack me-2"></i> Arus Kas
    </a>

    <hr>

    <a href="../logout.php">
        <i class="bi bi-box-arrow-right me-2"></i> Logout
    </a>

</div>


<div class="main">

    <div class="mb-4">
        <h3 class="fw-bold">Jurnal Umum</h3>
        <p class="text-secondary">
            Catatan seluruh transaksi berdasarkan sistem debit dan kredit.
        </p>
    </div>

    <!-- RINGKASAN -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="content-card">
                <small class="text-secondary">Total Jurnal</small>
                <h4 class="fw-bold mt-2">
                    <?= count($jurnals) ?> Jurnal
                </h4>
            </div>
        </div>

        <div class="col-md-4">
            <div class="content-card">
                <small class="text-secondary">Total Debit</small>
                <h4 class="fw-bold text-primary mt-2">
                    <?= rupiah($total_semua_debit) ?>
                </h4>
            </div>
        </div>

        <div class="col-md-4">
            <div class="content-card">
                <small class="text-secondary">Total Kredit</small>
                <h4 class="fw-bold text-success mt-2">
                    <?= rupiah($total_semua_kredit) ?>
                </h4>
            </div>
        </div>

    </div>


    <!-- DAFTAR JURNAL -->
    <div class="content-card">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-bold mb-1">Daftar Jurnal Transaksi</h5>
                <small class="text-secondary">
                    Detail pencatatan setiap transaksi.
                </small>
            </div>

            <button onclick="window.print()"
                    class="btn btn-outline-primary">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
        </div>

        <?php if (count($jurnals) > 0): ?>

            <?php foreach ($jurnals as $journal): ?>

                <?php
                $debit = (float)$journal['total_debit'];
                $kredit = (float)$journal['total_kredit'];

                $balance = abs($debit - $kredit) < 0.005;
                ?>

                <div class="journal-detail mb-4">

                    <div class="d-flex flex-wrap justify-content-between gap-2">

                        <div>
                            <h6 class="fw-bold mb-1">
                                <?= e($journal['nomor_jurnal']) ?>
                            </h6>

                            <small class="text-secondary">
                                <?= date('d/m/Y', strtotime($journal['tanggal'])) ?>
                            </small>

                            <p class="mb-2 mt-2">
                                <?= e($journal['keterangan']) ?>
                            </p>
                        </div>

                        <div>
                            <?php if ($balance && $journal['status'] === 'posted'): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle"></i>
                                    Balance
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    Tidak Seimbang
                                </span>
                            <?php endif; ?>
                        </div>

                    </div>

                    <div class="table-responsive">

                        <table class="table table-sm table-bordered bg-white align-middle mb-2">

                            <thead class="table-light">
                                <tr>
                                    <th>Kode Akun</th>
                                    <th>Nama Akun</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Kredit</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($details[$journal['id']] as $detail): ?>

                                    <tr>
                                        <td><?= e($detail['kode_akun']) ?></td>
                                        <td><?= e($detail['nama_akun']) ?></td>

                                        <td class="text-end">
                                            <?= (float)$detail['debit'] > 0
                                                ? rupiah($detail['debit'])
                                                : '-' ?>
                                        </td>

                                        <td class="text-end">
                                            <?= (float)$detail['kredit'] > 0
                                                ? rupiah($detail['kredit'])
                                                : '-' ?>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="2">Total</td>
                                    <td class="text-end">
                                        <?= rupiah($debit) ?>
                                    </td>
                                    <td class="text-end">
                                        <?= rupiah($kredit) ?>
                                    </td>
                                </tr>
                            </tfoot>

                        </table>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="text-center py-5">
                <i class="bi bi-journal-x fs-1 text-secondary"></i>
                <h6 class="mt-3">Belum Ada Jurnal</h6>
                <p class="text-secondary">
                    Silakan tambahkan transaksi terlebih dahulu.
                </p>

                <a href="transaksi.php" class="btn btn-primary">
                    Tambah Transaksi
                </a>
            </div>

        <?php endif; ?>

    </div>

</div>

<style>
@media print {
    .sidebar, button {
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

    .journal-detail {
        break-inside: avoid;
    }
}
</style>

</body>
</html>
