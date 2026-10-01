<?php

/**
 * GAMBARAN RELASI TABEL
 *
 * kategori
 * - id (PK)
 * - nama: Bayi lahir, Bayi, Anak & dewasa
 * - umur_min (INT, nullable)
 * - satuan_min (VARCHAR: jam / tahun, nullable)
 * - min_inklusif (BOOLEAN)
 * - umur_max (INT, nullable)
 * - satuan_max (VARCHAR: jam / tahun, nullable)
 * - max_inklusif (BOOLEAN)
 *
 * Rentang sesuai permintaan (batas ketat):
 * id | nama          | min  | satuan | inklusif | max  | satuan | inklusif
 * 1  | Bayi lahir    | 0    | jam    | true     | 72   | jam    | false
 * 2  | Bayi          | 72   | jam    | false    | 1    | tahun  | false
 * 3  | Anak & dewasa | 1    | tahun  | false    | NULL | NULL   | false
 * NULL pada umur_max berarti tidak memiliki batas usia maksimum.
 * Usia negatif tidak valid. Batas ketat di atas belum mencakup usia tepat
 * 72 jam dan tepat 1 tahun. Keputusan memasukkan kedua batas itu ke suatu
 * kategori perlu ditentukan sebelum membuat pemilihan kategori otomatis.
 * Usia 1 tahun mengacu pada ulang tahun pertama, bukan konversi 365 hari.
 *
 * pemeriksaan
 * - id (PK)
 * - id_kategori (FK -> kategori.id, NOT NULL)
 * - nama
 * - satuan (VARCHAR, nullable)
 * - batas_bawah (DECIMAL, nullable)
 * - batas_atas  (DECIMAL, nullable)
 *
 * hematologi / kimia_klinik / agd (masing-masing tabel):
 * - id (PK)
 * - id_pemeriksaan (FK -> pemeriksaan.id)
 * Kategori didapat melalui pemeriksaan, tidak diduplikasi di tabel lab.
 *
 * kategori (1) ---- (N) pemeriksaan
 * pemeriksaan (1) ---- (N) hematologi / kimia_klinik / agd
 *
 * Contoh baris pemeriksaan:
 * id | nama | id_kategori
 * 1  | HCT  | 3             -> HCT untuk anak & dewasa
 * 2  | HCT (bayi) | 2      -> HCT untuk bayi
 * Masing-masing baris memiliki batas bawah/atas sesuai kategorinya.
 * Kategori disimpan eksplisit; entri tanpa label usia dipetakan ke kategori 3.
 * Jika aturan kategori tertentu tidak ditemukan, jangan gunakan ambang
 * kategori lain sebagai pengganti otomatis.
 *
 * Simpan batas yang tidak tersedia sebagai NULL di database; tanda '-'
 * hanya untuk input/tampilan, bukan disimpan ke kolom DECIMAL.
 */

/** Mengubah angka desimal (titik/koma); NULL atau '-' berarti tanpa batas. */
function normalisasiAngkaLab($input, bool $bolehTanpaBatas = false): ?float
{
    if (!is_scalar($input) && $input !== null) {
        throw new InvalidArgumentException('Nilai harus berupa angka.');
    }

    $teks = trim((string) $input);
    if ($bolehTanpaBatas && ($input === null || $teks === '-')) {
        return null;
    }

    if (!preg_match('/^[+-]?(?:\d+(?:[.,]\d+)?|[.,]\d+)$/D', $teks)) {
        throw new InvalidArgumentException('Masukkan angka valid tanpa pemisah ribuan.');
    }

    $angka = (float) str_replace(',', '.', $teks);
    if (!is_finite($angka)) {
        throw new InvalidArgumentException('Angka di luar rentang yang didukung.');
    }

    return $angka;
}

/**
 * Ambil batas dari pemeriksaan yang terkait dengan kategori pasien.
 * Kritis bila hasil < batas bawah ATAU hasil > batas atas.
 * Hasil tepat sama dengan batas dianggap tidak kritis.
 * is_kritis NULL berarti tidak dapat ditentukan, bukan tidak kritis.
 * Status tidak kritis tidak berarti hasil pemeriksaan normal.
 */
function tentukanKritis($hasil, $batasBawah, $batasAtas): array
{
    $nilai = normalisasiAngkaLab($hasil);
    $bawah = normalisasiAngkaLab($batasBawah, true);
    $atas = normalisasiAngkaLab($batasAtas, true);

    if ($bawah !== null && $atas !== null && $bawah > $atas) {
        throw new InvalidArgumentException('Batas bawah tidak boleh melebihi batas atas.');
    }

    if ($bawah === null && $atas === null) {
        return ['is_kritis' => null, 'status' => 'Tidak dapat ditentukan', 'alasan' => 'Kedua batas tidak tersedia.'];
    }

    if ($bawah !== null && $nilai < $bawah) {
        return ['is_kritis' => true, 'status' => 'Kritis', 'alasan' => 'Di bawah batas bawah.'];
    }

    if ($atas !== null && $nilai > $atas) {
        return ['is_kritis' => true, 'status' => 'Kritis', 'alasan' => 'Di atas batas atas.'];
    }

    return ['is_kritis' => false, 'status' => 'Tidak kritis', 'alasan' => 'Tidak melewati batas kritis yang tersedia.'];
}

/*
 * Contoh pemakaian (angka simulasi, bukan ambang klinis):
 * tentukanKritis(5, 10, 20);      // Kritis: di bawah batas bawah
 * tentukanKritis(25, '-', 20);    // Kritis: di atas batas atas
 * tentukanKritis(5, '-', 20);     // Tidak kritis: batas bawah diabaikan
 * tentukanKritis(20, 10, 20);     // Tidak kritis: sama dengan batas atas
 * tentukanKritis('15,5', 10, 20); // Tidak kritis
 * tentukanKritis(15, null, null); // Tidak dapat ditentukan
 *
 * Contoh query aturan hematologi (parameter diikat menggunakan PDO):
 * SELECT p.batas_bawah, p.batas_atas
 * FROM hematologi h
 * JOIN pemeriksaan p ON p.id = h.id_pemeriksaan
 * WHERE p.id_kategori = :id_kategori AND h.id_pemeriksaan = :id_pemeriksaan;
 *
 * Setelah fetch, pastikan baris ditemukan sebelum memanggil:
 * $status = tentukanKritis($hasil, $row['batas_bawah'], $row['batas_atas']);
 * Untuk kimia klinik / AGD, gunakan query dengan tabel kimia_klinik / agd.
 */


// Data dari daftar pengguna; satuan dan ambang disalin sesuai sumber.
$kategori = [
    1 => ['id' => 1, 'nama' => 'Bayi lahir', 'umur_min' => 0, 'satuan_min' => 'jam', 'min_inklusif' => true, 'umur_max' => 72, 'satuan_max' => 'jam', 'max_inklusif' => false, 'rentang' => '0 <= usia < 72 jam'],
    2 => ['id' => 2, 'nama' => 'Bayi', 'umur_min' => 72, 'satuan_min' => 'jam', 'min_inklusif' => false, 'umur_max' => 1, 'satuan_max' => 'tahun', 'max_inklusif' => false, 'rentang' => '72 jam < usia < 1 tahun'],
    3 => ['id' => 3, 'nama' => 'Anak & dewasa', 'umur_min' => 1, 'satuan_min' => 'tahun', 'min_inklusif' => false, 'umur_max' => null, 'satuan_max' => null, 'max_inklusif' => false, 'rentang' => 'usia > 1 tahun'],
];
$pemeriksaan = [
    1 => ['id' => 1, 'id_kategori' => 3, 'nama' => 'HCT', 'satuan' => '%', 'batas_bawah' => 20, 'batas_atas' => 60],
    2 => ['id' => 2, 'id_kategori' => 2, 'nama' => 'HCT (bayi)', 'satuan' => 'Vol %', 'batas_bawah' => 33, 'batas_atas' => 70],
    3 => ['id' => 3, 'id_kategori' => 3, 'nama' => 'HGB', 'satuan' => 'g/dl', 'batas_bawah' => 5, 'batas_atas' => 20],
    4 => ['id' => 4, 'id_kategori' => 2, 'nama' => 'HGB (bayi)', 'satuan' => 'g/dl', 'batas_bawah' => 9.5, 'batas_atas' => 22],
    5 => ['id' => 5, 'id_kategori' => 3, 'nama' => 'Leukosit', 'satuan' => 'g/dl', 'batas_bawah' => 200, 'batas_atas' => 30000],
    6 => ['id' => 6, 'id_kategori' => 3, 'nama' => 'Trombosit Dewasa', 'satuan' => 'g/dl', 'batas_bawah' => 50, 'batas_atas' => 1000000],
    7 => ['id' => 7, 'id_kategori' => 3, 'nama' => 'Aktivated Platelet Thromboplastin Time (AppTT)', 'satuan' => null, 'batas_bawah' => null, 'batas_atas' => 78],
    8 => ['id' => 8, 'id_kategori' => 3, 'nama' => 'Ureum', 'satuan' => 'mg/dl', 'batas_bawah' => null, 'batas_atas' => 200],
    9 => ['id' => 9, 'id_kategori' => 3, 'nama' => 'Kreatinin', 'satuan' => 'mg/dl', 'batas_bawah' => null, 'batas_atas' => 9.5],
    10 => ['id' => 10, 'id_kategori' => 3, 'nama' => 'Glukosa', 'satuan' => 'mg/dl', 'batas_bawah' => 40, 'batas_atas' => 450],
    11 => ['id' => 11, 'id_kategori' => 2, 'nama' => 'Glukosa Bayi', 'satuan' => 'mg/dl', 'batas_bawah' => 35, 'batas_atas' => 450],
    12 => ['id' => 12, 'id_kategori' => 3, 'nama' => 'Natrium', 'satuan' => 'mmol/l', 'batas_bawah' => 120, 'batas_atas' => 160],
    13 => ['id' => 13, 'id_kategori' => 3, 'nama' => 'Kalium', 'satuan' => 'mmol/l', 'batas_bawah' => 2.8, 'batas_atas' => 6.2],
    14 => ['id' => 14, 'id_kategori' => 1, 'nama' => 'Kalium (bayi lahir)', 'satuan' => 'mmol/l', 'batas_bawah' => 2.5, 'batas_atas' => 8.0],
    15 => ['id' => 15, 'id_kategori' => 3, 'nama' => 'Klorida', 'satuan' => 'mmol/l', 'batas_bawah' => 70, 'batas_atas' => 120],
    16 => ['id' => 16, 'id_kategori' => 2, 'nama' => 'Bilirubin (bayi)', 'satuan' => 'mg/dl', 'batas_bawah' => null, 'batas_atas' => 17],
    17 => ['id' => 17, 'id_kategori' => 3, 'nama' => 'PH', 'satuan' => 'mmHg', 'batas_bawah' => 7.15, 'batas_atas' => 7.7],
    18 => ['id' => 18, 'id_kategori' => 3, 'nama' => 'PO2', 'satuan' => 'mmHg', 'batas_bawah' => 40, 'batas_atas' => null],
    19 => ['id' => 19, 'id_kategori' => 3, 'nama' => 'PCO2', 'satuan' => 'mmHg', 'batas_bawah' => 20, 'batas_atas' => 60],
];
// Nomor lokal pada setiap tabel mengikuti urutan daftar pengguna.
$tabelLab = [];
foreach (['hematologi' => range(1, 7), 'kimia_klinik' => range(8, 16), 'agd' => range(17, 19)] as $lab => $ids) {
    foreach ($ids as $index => $id) {
        $tabelLab[$lab][] = ['id' => $index + 1, 'id_pemeriksaan' => $id];
    }
}
$namaLab = ['hematologi' => 'Hematologi', 'kimia_klinik' => 'Kimia Klinik', 'agd' => 'Analisa Gas Darah (AGD)'];

/** Operator hanya untuk tampilan; fungsi membandingkan angka mentah. */
function tampilanBatasLab($nilai, string $operator): string
{
    if ($nilai === null) {
        return '-';
    }
    $desimal = strlen(explode('.', (string) $nilai)[1] ?? '');
    return $operator . number_format((float) $nilai, $desimal, ',', '.');
}
function htmlLab($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inputLab(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    if (!is_string($value)) {
        throw new InvalidArgumentException('Format input tidak valid.');
    }
    return $value;
}

// Hasil JOIN array: lab -> pemeriksaan -> kategori.
$gabungan = [];
foreach ($tabelLab as $lab => $rows) {
    foreach ($rows as $row) {
        $p = $pemeriksaan[$row['id_pemeriksaan']];
        $gabungan[$lab . ':' . $row['id']] = ['lab' => $lab, 'id' => $row['id'], 'pemeriksaan' => $p, 'kategori' => $kategori[$p['id_kategori']]];
    }
}
$pilihan = 'hematologi:1';
$nilaiInput = '';
$hasilInput = null;
$error = null;
$aktif = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $pilihan = inputLab('aturan');
        $nilaiInput = inputLab('hasil');
        if (!isset($gabungan[$pilihan])) {
            throw new InvalidArgumentException('Pilih pemeriksaan dan kategori yang tersedia.');
        }
        $aktif = $gabungan[$pilihan];
        $p = $aktif['pemeriksaan'];
        $hasilInput = tentukanKritis($nilaiInput, $p['batas_bawah'], $p['batas_atas']);
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Simulasi nilai kritis laboratorium</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f2f5fa;color:#203047;font:15px/1.6 system-ui,sans-serif}main{max-width:1200px;margin:auto;padding:32px 20px}h1{font-size:30px;line-height:1.25}h2{font-size:21px;margin:0 0 14px}h3{font-size:17px;margin:0 0 12px}section{background:#fff;padding:24px;border:1px solid #dce4ef;border-radius:12px;margin:20px 0}.muted{color:#54657a}.notice{background:#fff3d5;border-left:4px solid #c88a14;padding:12px 16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}.card{padding:16px;border:1px solid #dce4ef;border-radius:8px}.scroll{overflow-x:auto}table{border-collapse:collapse;width:100%;white-space:nowrap;font-size:14px}th,td{padding:10px;text-align:left;border-bottom:1px solid #e3e9f0}th{background:#edf3fa}label{display:block;font-weight:600;margin:12px 0 6px}input,select,button{font:inherit;padding:11px;border-radius:7px;border:1px solid #aabbce;width:100%}button{background:#185dcc;color:white;border:0;width:auto;margin-top:16px;padding:11px 24px;cursor:pointer}.result{padding:16px;background:#edf3fa;border-radius:8px;margin-top:18px}.critical,.error{background:#ffeded;color:#942323}.ok{background:#e8f5ee;color:#1b633f}.relation{text-align:center;padding:18px;background:#edf3fa;border-radius:8px}.relation p{margin:8px}code{font-family:Consolas,monospace}small{display:block}.selected{background:#eaf2ff}summary{cursor:pointer;font-weight:600}a{color:#185dcc}
</style>
</head>
<body><main>
<p class="muted">LABORATORIUM / DATA PEMERIKSAAN</p>
<h1>Relasi pemeriksaan & nilai kritis</h1>
<p>Pilih aturan berdasarkan kategori pasien, masukkan hasil, lalu periksa statusnya.</p>
<p class="notice">Nama, satuan, dan ambang mengikuti daftar yang Anda berikan. Pemeriksaan tanpa label usia dipetakan ke anak &amp; dewasa; label bayi dan bayi lahir mengikuti kategorinya. Data masih berupa array PHP dan belum disimpan ke database.</p>

<section>
<h2>Input hasil pemeriksaan</h2>
<form method="post" action="">
<label for="aturan">Laboratorium / pemeriksaan / kategori</label>
<select id="aturan" name="aturan" required>
<?php foreach ($tabelLab as $lab => $rows): ?>
<optgroup label="<?= htmlLab($namaLab[$lab]) ?>">
<?php foreach ($rows as $row): $key = $lab . ':' . $row['id']; $g = $gabungan[$key]; $p = $g['pemeriksaan']; ?>
<option value="<?= htmlLab($key) ?>" <?= $key === $pilihan ? 'selected' : '' ?>><?= htmlLab($p['nama'] . ' / ' . $g['kategori']['nama'] . ' / ' . ($p['satuan'] ?? '-') . ' / kritis: ' . tampilanBatasLab($p['batas_bawah'], '<') . ' atau ' . tampilanBatasLab($p['batas_atas'], '>')) ?></option>
<?php endforeach; ?></optgroup>
<?php endforeach; ?>
</select>
<label for="hasil">Nilai hasil</label>
<input id="hasil" name="hasil" type="text" inputmode="decimal" value="<?= htmlLab($nilaiInput) ?>" placeholder="Contoh: 25 atau 15,5" required aria-describedby="format">
<small id="format" class="muted">Desimal boleh memakai titik atau koma. Jangan gunakan pemisah ribuan: ketik 30000, bukan 30.000. Satuan mengikuti pemeriksaan yang dipilih.</small>
<button type="submit">Periksa nilai kritis</button>
</form>
<?php if ($error !== null): ?><div role="alert" class="result error"><?= htmlLab($error) ?></div><?php endif; ?>
<?php if ($hasilInput !== null): $p = $aktif['pemeriksaan']; ?>
<div role="status" class="result <?= $hasilInput['is_kritis'] === true ? 'critical' : ($hasilInput['is_kritis'] === false ? 'ok' : '') ?>">
<strong><?= htmlLab($hasilInput['status']) ?></strong> &mdash; <?= htmlLab($hasilInput['alasan']) ?>
<small><?= htmlLab($aktif['lab'] . ' / ' . $p['nama'] . ' / ' . $aktif['kategori']['nama']) ?></small>
<small>Hasil: <?= htmlLab($nilaiInput) ?> <?= htmlLab($p['satuan'] ?? '-') ?> | Batas bawah: <?= htmlLab(tampilanBatasLab($p['batas_bawah'], '<')) ?> | Batas atas: <?= htmlLab(tampilanBatasLab($p['batas_atas'], '>')) ?></small>
<small>Relasi: <?= htmlLab($aktif['lab']) ?>.id <?= htmlLab($aktif['id']) ?> &rarr; pemeriksaan.id <?= htmlLab($p['id']) ?> &rarr; kategori.id <?= htmlLab($p['id_kategori']) ?></small>
</div>
<?php endif; ?>
<p class="muted">Kritis jika hasil &lt; batas bawah atau hasil &gt; batas atas. Nilai tepat pada batas tidak kritis. Tanda <code>-</code> berarti batas tidak tersedia dan diabaikan. Jika keduanya tidak tersedia, status tidak dapat ditentukan. Tidak kritis bukan berarti nilai normal.</p>
</section>

<section>
<h2>Daftar batas kritis</h2>
<p class="muted">19 pemeriksaan: 7 hematologi, 9 kimia klinik, dan 3 AGD. Tanda &lt; / &gt; menunjukkan kondisi kritis, bukan rentang normal.</p>
<?php foreach ($tabelLab as $lab => $rows): ?>
<h3><?= htmlLab($namaLab[$lab]) ?></h3>
<div class="scroll"><table><thead><tr><th>No.</th><th>Pemeriksaan</th><th>Kategori</th><th>Satuan</th><th>Batas Bawah</th><th>Batas Atas</th></tr></thead><tbody>
<?php foreach ($rows as $row): $p = $pemeriksaan[$row['id_pemeriksaan']]; ?>
<tr><td><?= htmlLab($row['id']) ?></td><td><?= htmlLab($p['nama']) ?></td><td><?= htmlLab($kategori[$p['id_kategori']]['nama']) ?></td><td><?= htmlLab($p['satuan'] ?? '-') ?></td><td><?= htmlLab(tampilanBatasLab($p['batas_bawah'], '<')) ?></td><td><?= htmlLab(tampilanBatasLab($p['batas_atas'], '>')) ?></td></tr>
<?php endforeach; ?></tbody></table></div><br>
<?php endforeach; ?>
</section>

<section>
<h2>Gambaran relasi tabel</h2>
<div class="relation" aria-label="Kategori memiliki banyak pemeriksaan. Setiap tabel laboratorium merujuk pemeriksaan.">
<strong>kategori</strong><p>1 &darr; N</p><strong>pemeriksaan</strong><p>1 &darr; N pada setiap tabel laboratorium</p>
<div class="grid"><div class="card">hematologi</div><div class="card">kimia_klinik</div><div class="card">agd</div></div>
</div>
<p><code>pemeriksaan.id_kategori &rarr; kategori.id</code><br><code>tabel_lab.id_pemeriksaan &rarr; pemeriksaan.id</code></p>
<p class="muted">HCT untuk bayi dan HCT untuk anak &amp; dewasa memiliki ID pemeriksaan berbeda. Kategori melekat pada pemeriksaan dan tidak diduplikasi di tabel laboratorium.</p>
<h3>Hasil penggabungan relasi</h3>
<div class="scroll"><table>
<thead><tr><th>Tabel lab</th><th>ID lab</th><th>ID pemeriksaan</th><th>Nama pemeriksaan</th><th>Satuan</th><th>ID kategori</th><th>Kategori</th><th>Bawah</th><th>Atas</th></tr></thead><tbody>
<?php foreach ($gabungan as $key => $g): $p = $g['pemeriksaan']; ?>
<tr class="<?= $key === $pilihan ? 'selected' : '' ?>"><td><?= htmlLab($g['lab']) ?></td><td><?= htmlLab($g['id']) ?></td><td><?= htmlLab($p['id']) ?></td><td><?= htmlLab($p['nama']) ?></td><td><?= htmlLab($p['satuan'] ?? '-') ?></td><td><?= htmlLab($p['id_kategori']) ?></td><td><?= htmlLab($g['kategori']['nama']) ?></td><td><?= htmlLab(tampilanBatasLab($p['batas_bawah'], '<')) ?></td><td><?= htmlLab(tampilanBatasLab($p['batas_atas'], '>')) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
</section>

<section>
<h2>Data tabel kategori</h2>
<div class="scroll"><table><thead><tr><th>id (PK)</th><th>nama</th><th>umur_min</th><th>satuan_min</th><th>min_inklusif</th><th>umur_max</th><th>satuan_max</th><th>max_inklusif</th><th>Rentang</th></tr></thead><tbody>
<?php foreach ($kategori as $k): ?><tr><td><?= htmlLab($k['id']) ?></td><td><?= htmlLab($k['nama']) ?></td><td><?= htmlLab($k['umur_min']) ?></td><td><?= htmlLab($k['satuan_min']) ?></td><td><?= $k['min_inklusif'] ? 'Ya' : 'Tidak' ?></td><td><?= htmlLab($k['umur_max'] ?? '-') ?></td><td><?= htmlLab($k['satuan_max'] ?? '-') ?></td><td><?= $k['max_inklusif'] ? 'Ya' : 'Tidak' ?></td><td><?= htmlLab($k['rentang']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<p class="muted">Inklusif berarti usia tepat pada batas ikut termasuk. Usia tepat 72 jam dan 1 tahun belum tercakup dalam rentang yang diminta; kategori dipilih secara manual. Satu tahun mengacu pada ulang tahun pertama.</p>
</section>

<section>
<h2>Data tabel pemeriksaan</h2>
<div class="scroll"><table><thead><tr><th>id (PK)</th><th>id_kategori (FK)</th><th>nama</th><th>satuan</th><th>batas_bawah</th><th>batas_atas</th></tr></thead><tbody>
<?php foreach ($pemeriksaan as $p): ?><tr><td><?= htmlLab($p['id']) ?></td><td><?= htmlLab($p['id_kategori']) ?></td><td><?= htmlLab($p['nama']) ?></td><td><?= htmlLab($p['satuan'] ?? '-') ?></td><td><?= htmlLab(tampilanBatasLab($p['batas_bawah'], '<')) ?></td><td><?= htmlLab(tampilanBatasLab($p['batas_atas'], '>')) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<p class="muted">Batas kosong disimpan sebagai NULL pada data, ditampilkan sebagai tanda -.</p>
</section>

<section><h2>Data tabel laboratorium</h2><div class="grid">
<?php foreach ($tabelLab as $lab => $rows): ?>
<div class="card"><h3><?= htmlLab($lab) ?></h3><table><thead><tr><th>id (PK)</th><th>id_pemeriksaan (FK)</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= htmlLab($row['id']) ?></td><td><?= htmlLab($row['id_pemeriksaan']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endforeach; ?>
</div></section>
</main></body></html>