
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

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';

// PROSES SIMPAN TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )) {
            throw new Exception("Permintaan tidak valid. Silakan refresh halaman.");
        }

        $jenis = $_POST['jenis'] ?? '';
        $tanggal = $_POST['tanggal'] ?? '';
        $keterangan = trim($_POST['keterangan'] ?? '');
        $nominal = filter_var(
            $_POST['nominal'] ?? '',
            FILTER_VALIDATE_FLOAT
        );
        $account_id = (int)($_POST['account_id'] ?? 0);

        if (!in_array($jenis, ['pemasukan', 'pengeluaran'], true)) {
            throw new Exception("Jenis transaksi tidak valid.");
        }

        $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

        if (!$date || $date->format('Y-m-d') !== $tanggal) {
            throw new Exception("Tanggal transaksi tidak valid.");
        }

        if ($date > new DateTime('today')) {
            throw new Exception("Tanggal tidak boleh melebihi hari ini.");
        }

        if ($keterangan === '' || strlen($keterangan) > 1000) {
            throw new Exception("Keterangan wajib diisi maksimal 1000 karakter.");
        }

        if ($nominal === false || !is_finite($nominal) ||
            $nominal <= 0 || $nominal > 999999999999999) {
            throw new Exception("Nominal transaksi tidak valid.");
        }

        $nominal = round($nominal, 2);

        if ($nominal <= 0) {
            throw new Exception("Nominal harus lebih dari nol.");
        }

        // Cari akun kas
        $stmt = $pdo->prepare(
            "SELECT id FROM accounts WHERE kode_akun = '101'"
        );
        $stmt->execute();
        $kas = $stmt->fetch();

        if (!$kas) {
            throw new Exception("Akun Kas belum tersedia.");
        }

        // Validasi akun pendapatan atau beban
        $kategori = $jenis === 'pemasukan'
            ? 'Pendapatan'
            : 'Beban';

        $stmt = $pdo->prepare(
            "SELECT id FROM accounts
             WHERE id = ? AND kategori = ?"
        );

        $stmt->execute([$account_id, $kategori]);
        $akun = $stmt->fetch();

        if (!$akun) {
            throw new Exception("Akun transaksi tidak valid.");
        }

        // Debit dan kredit otomatis
        if ($jenis === 'pemasukan') {
            $debit_id = $kas['id'];
            $kredit_id = $akun['id'];
        } else {
            $debit_id = $akun['id'];
            $kredit_id = $kas['id'];
        }

        $total_debit = $nominal;
        $total_kredit = $nominal;

        // Validasi keseimbangan
        if (round($total_debit, 2) !== round($total_kredit, 2)) {
            throw new Exception("Debit dan kredit tidak seimbang.");
        }

        // Simpan jurnal secara atomik
        $pdo->beginTransaction();

        $nomor_jurnal = 'JRN-' . date('YmdHis') .
            '-' . strtoupper(bin2hex(random_bytes(3)));

        $stmt = $pdo->prepare(
            "INSERT INTO journal_entries
            (nomor_jurnal, tanggal, keterangan, status)
            VALUES (?, ?, ?, 'draft')"
        );

        $stmt->execute([
            $nomor_jurnal,
            $tanggal,
            $keterangan
        ]);

        $journal_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO journal_lines
            (journal_id, account_id, debit, kredit)
            VALUES (?, ?, ?, ?)"
        );

        // Baris debit
        $stmt->execute([
            $journal_id,
            $debit_id,
            $nominal,
            0
        ]);

        // Baris kredit
        $stmt->execute([
            $journal_id,
            $kredit_id,
            0,
            $nominal
        ]);

        // Hanya posting jika jurnal seimbang
        $stmt = $pdo->prepare(
            "SELECT
                SUM(debit) AS total_debit,
                SUM(kredit) AS total_kredit
             FROM journal_lines
             WHERE journal_id = ?"
        );

        $stmt->execute([$journal_id]);
        $balance = $stmt->fetch();

        if (
            round((float)$balance['total_debit'], 2) !==
            round((float)$balance['total_kredit'], 2)
        ) {
            throw new Exception("Jurnal gagal diseimbangkan.");
        }

        $stmt = $pdo->prepare(
            "UPDATE journal_entries
             SET status = 'posted'
             WHERE id = ?"
        );

        $stmt->execute([$journal_id]);

        $pdo->commit();

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header("Location: transaksi.php?success=1");
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

// PESAN BERHASIL
if (isset($_GET['success'])) {
    $success = "Transaksi berhasil disimpan dan jurnal sudah seimbang!";
}

// AMBIL AKUN
$accounts = $pdo->query(
    "SELECT * FROM accounts
     WHERE kategori IN ('Pendapatan', 'Beban')
     ORDER BY kode_akun ASC"
)->fetchAll();

// AMBIL RIWAYAT TRANSAKSI
$stmt = $pdo->query(
    "SELECT
        je.id,
        je.nomor_jurnal,
        je.tanggal,
        je.keterangan,
        je.status,
        SUM(jl.debit) AS nominal
     FROM journal_entries je
     INNER JOIN journal_lines jl ON jl.journal_id = je.id
     WHERE je.status = 'posted'
     GROUP BY
        je.id,
        je.nomor_jurnal,
        je.tanggal,
        je.keterangan,
        je.status
     ORDER BY je.tanggal DESC, je.id DESC"
);

$transactions = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Transaksi - UMKM Finance</title>

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

.form-control, .form-select {
    padding: 11px;
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

<!-- SIDEBAR -->
<div class="sidebar">

    <h4><i class="bi bi-wallet2"></i> UMKM Finance</h4>

    <small class="text-secondary">MENU UTAMA</small>

    <a href="../index.php">
        <i class="bi bi-grid me-2"></i> Dashboard
    </a>

    <a href="transaksi.php" class="active">
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
        <h3 class="fw-bold">Transaksi Keuangan</h3>
        <p class="text-secondary">
            Catat pemasukan dan pengeluaran usaha kamu.
        </p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>


    <!-- FORM TRANSAKSI -->
    <div class="content-card mb-4">

        <h5 class="fw-bold mb-4">
            <i class="bi bi-plus-circle text-primary me-2"></i>
            Tambah Transaksi
        </h5>

        <form method="POST">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <div class="row g-3">

                <div class="col-md-6">

                    <label class="form-label fw-semibold">
                        Jenis Transaksi
                    </label>

                    <select name="jenis"
                            id="jenis"
                            class="form-select"
                            required>

                        <option value="">Pilih Jenis Transaksi</option>
                        <option value="pemasukan">Pemasukan</option>
                        <option value="pengeluaran">Pengeluaran</option>

                    </select>

                </div>


                <div class="col-md-6">

                    <label class="form-label fw-semibold">
                        Tanggal
                    </label>

                    <input type="date"
                           name="tanggal"
                           class="form-control"
                           value="<?= date('Y-m-d') ?>"
                           max="<?= date('Y-m-d') ?>"
                           required>

                </div>


                <div class="col-md-6">

                    <label class="form-label fw-semibold">
                        Kategori Akun
                    </label>

                    <select name="account_id"
                            id="account_id"
                            class="form-select"
                            required
                            disabled>

                        <option value="">Pilih jenis transaksi dahulu</option>

                        <?php foreach ($accounts as $account): ?>

                            <option
                                value="<?= $account['id'] ?>"
                                data-kategori="<?= e($account['kategori']) ?>">

                                <?= e($account['kode_akun']) ?> -
                                <?= e($account['nama_akun']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-md-6">

                    <label class="form-label fw-semibold">
                        Nominal (Rp)
                    </label>

                    <input type="number"
                           name="nominal"
                           class="form-control"
                           placeholder="Contoh: 500000"
                           min="0.01"
                           max="999999999999999"
                           step="0.01"
                           required>

                </div>


                <div class="col-12">

                    <label class="form-label fw-semibold">
                        Keterangan
                    </label>

                    <textarea name="keterangan"
                              class="form-control"
                              rows="3"
                              maxlength="1000"
                              placeholder="Contoh: Penjualan produk bulan November"
                              required></textarea>

                </div>

            </div>


            <div class="alert alert-info mt-4">
                <i class="bi bi-info-circle me-2"></i>
                Sistem akan membuat jurnal debit dan kredit secara otomatis.
            </div>


            <button type="submit" class="btn btn-primary px-4 py-2">
                <i class="bi bi-save me-2"></i>
                Simpan Transaksi
            </button>

        </form>

    </div>


    <!-- RIWAYAT TRANSAKSI -->
    <div class="content-card">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>
                <h5 class="fw-bold mb-1">Riwayat Transaksi</h5>
                <small class="text-secondary">
                    Daftar transaksi yang sudah tercatat.
                </small>
            </div>

            <span class="badge bg-primary">
                <?= count($transactions) ?> Transaksi
            </span>

        </div>


        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>No. Jurnal</th>
                        <th>Keterangan</th>
                        <th>Nominal</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($transactions) > 0): ?>

                    <?php foreach ($transactions as $i => $tr): ?>

                        <tr>

                            <td><?= $i + 1 ?></td>

                            <td>
                                <?= date('d/m/Y', strtotime($tr['tanggal'])) ?>
                            </td>

                            <td>
                                <small><?= e($tr['nomor_jurnal']) ?></small>
                            </td>

                            <td><?= e($tr['keterangan']) ?></td>

                            <td class="fw-semibold">
                                <?= rupiah($tr['nominal']) ?>
                            </td>

                            <td>
                                <span class="badge bg-success">
                                    Tercatat
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6"
                            class="text-center text-secondary py-4">

                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Belum ada transaksi.

                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<script>

const jenis = document.getElementById('jenis');
const account = document.getElementById('account_id');

jenis.addEventListener('change', function() {

    const selected = this.value;

    account.value = '';
    account.disabled = selected === '';

    Array.from(account.options).forEach(option => {

        if (!option.value) return;

        const kategori = option.dataset.kategori;

        option.hidden =
            (selected === 'pemasukan' && kategori !== 'Pendapatan') ||
            (selected === 'pengeluaran' && kategori !== 'Beban');

    });

    account.options[0].text = selected
        ? 'Pilih kategori akun'
        : 'Pilih jenis transaksi dahulu';

});

</script>

</body>
</html>
