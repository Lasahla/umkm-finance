
<?php
require_once 'config/database.php';

$password = password_hash('123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    "UPDATE users SET password = ? WHERE username = 'admin'"
);

$stmt->execute([$password]);

if ($stmt->rowCount() > 0) {
    echo "Password admin berhasil diubah menjadi 123.";
} else {
    echo "Akun admin tidak ditemukan atau password sudah sama.";
}
