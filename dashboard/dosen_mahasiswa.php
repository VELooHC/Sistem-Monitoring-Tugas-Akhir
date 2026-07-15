<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/dosen_layout.php';

requireLogin(['dosen']);

$user = currentUser();

$sidebarMenus = dosenSidebarMenus('/dashboard/dosen_mahasiswa.php');

renderDosenLayoutStart(
    'Data Mahasiswa',
    'Data Mahasiswa',
    'Daftar mahasiswa bimbingan.',
    $user,
    $sidebarMenus
);

$sql = "
SELECT
    nim,
    nama_lengkap,
    judul_ta
FROM mahasiswa
ORDER BY nama_lengkap ASC
";

$result = mysqli_query($koneksi, $sql);
?>
<div class="grid-two-column">
     <div class="content-column">
        <section class="info-card full-width">

            <div class="card-header">
                <h2>Data Mahasiswa</h2>
                <input
                type="text"
                    id="searchInput"
                    class="search-box"
                    placeholder="Cari mahasiswa...">
            </div>

            <div class="table-responsive">
                <table class="custom-table" id="tableMahasiswa">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIM</th>
                            <th>Nama Mahasiswa</th>
                            <th>Judul Tugas Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)):
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= e($row['nim']) ?></td>
                            <td><?= e($row['nama_lengkap']) ?></td>
                            <td><?= e($row['judul_ta']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>  
        </section>
    </div>
</div>
<style>

.card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.search-box{
    width:260px;
    padding:10px 15px;
    border:1px solid #dbe2ea;
    border-radius:10px;
    outline:none;
    font-size:.95rem;
    transition:.2s;
}

.search-box:focus{
    border-color:#1e4f91;
}

.table-responsive{
    overflow-x:auto;
}

.custom-table{
    width:100%;
    border-collapse:collapse;
}

.custom-table thead{
    background:#edf3fb;
}

.custom-table th{
    padding:14px;
    text-align:left;
    font-size:.95rem;
    font-weight:600;
}

.custom-table td{
    padding:14px;
    border-bottom:1px solid #eef2f7;
    font-size:.93rem;
}

.custom-table tbody tr:hover{
    background:#f8fbff;
}

.status{
    display:inline-block;
    padding:6px 14px;
    border-radius:20px;
    font-size:.85rem;
    font-weight:600;
}

.status-draft{
    background:#fff3cd;
    color:#856404;
}

.status-revisi{
    background:#fde2e2;
    color:#c0392b;
}

.status-disetujui{
    background:#d4edda;
    color:#155724;
}

.status-ditolak{
    background:#f8d7da;
    color:#721c24;
}

.status-proses{
    background:#d1ecf1;
    color:#0c5460;
}

</style>

<script>

document.getElementById("searchInput").addEventListener("keyup", function(){

    let filter = this.value.toLowerCase();

    let rows = document.querySelectorAll("#tableMahasiswa tbody tr");

    rows.forEach(function(row){

        let text = row.innerText.toLowerCase();

        row.style.display = text.includes(filter) ? "" : "none";

    });

});

</script>

<?php renderDosenLayoutEnd(); 
?>