<!DOCTYPE html>
<html>
<head>
    <title>Pilihan Warna</title>
</head>
<body>

    <h2>Pilih Warna</h2>

    <form method="post">
        <label>Pilih Warna:</label>
        <select name="warna">
            <option value="Merah">Merah</option>
            <option value="Biru">Biru</option>
            <option value="Hijau">Hijau</option>
            <option value="Kuning">Kuning</option>
            <option value="Hitam">Hitam</option>
        </select>
        <br><br>
        <input type="submit" value="Pilih">
    </form>

    <?php
    if (isset($_POST['warna'])) {
        $warna = $_POST['warna'];
        echo "<h3>Warna yang Anda pilih: $warna</h3>";
    }
    ?>

</body>
</html>