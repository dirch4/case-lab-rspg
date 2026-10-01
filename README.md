# Diagram laboratorium dan penentuan nilai kritis

Dokumentasi struktur dan data yang digunakan di `index.php`: 7 pemeriksaan hematologi, 9 kimia klinik, dan 3 AGD. Data aplikasi masih berupa array PHP, belum tabel database fisik. Nama, satuan, dan ambang mengikuti daftar pengguna.

## 1. Relasi seluruh tabel

Setiap baris pemeriksaan memiliki satu kategori. Tabel laboratorium merujuk pemeriksaan melalui `id_pemeriksaan`; kategori tidak diduplikasi di tabel laboratorium. Garis putus-putus menunjukkan relasi non-identifying: foreign key bukan bagian primary key.

```mermaid
erDiagram
    kategori ||..o{ pemeriksaan : mengelompokkan
    pemeriksaan ||..o{ hematologi : dirujuk
    pemeriksaan ||..o{ kimiaKlinik : dirujuk
    pemeriksaan ||..o{ agd : dirujuk

    kategori {
        int id PK
        varchar nama
        int umur_min "Nullable"
        varchar satuan_min "jam atau tahun"
        boolean min_inklusif
        int umur_max "NULL berarti tanpa batas maksimum"
        varchar satuan_max "jam atau tahun; nullable"
        boolean max_inklusif
    }
    pemeriksaan {
        int id PK
        int id_kategori FK
        varchar nama
        varchar satuan "Nullable"
        decimal batas_bawah "Nullable"
        decimal batas_atas "Nullable"
    }
    hematologi {
        int id PK
        int id_pemeriksaan FK
    }
    kimiaKlinik["kimia_klinik"] {
        int id PK
        int id_pemeriksaan FK
    }
    agd {
        int id PK
        int id_pemeriksaan FK
    }
```

## 2. Kategori dan batas umur

`rentang` pada array PHP adalah teks tampilan yang menjelaskan kolom batas umur. Inklusif berarti usia tepat pada batas ikut termasuk. Satu tahun mengacu pada ulang tahun pertama.

```mermaid
flowchart TB
    kategoriData["kategori"]
    kategoriData --> kategoriSatu["id=1 | Bayi lahir"]
    kategoriData --> kategoriDua["id=2 | Bayi"]
    kategoriData --> kategoriTiga["id=3 | Anak & dewasa"]
    kategoriSatu --> umurSatu["umur_min=0 jam; min_inklusif=true; umur_max=72 jam; max_inklusif=false"]
    kategoriDua --> umurDua["umur_min=72 jam; min_inklusif=false; umur_max=1 tahun; max_inklusif=false"]
    kategoriTiga --> umurTiga["umur_min=1 tahun; min_inklusif=false; umur_max=NULL; satuan_max=NULL; max_inklusif=false"]
    kategoriData -.-> catatanUsia["Usia tepat 72 jam dan tepat 1 tahun belum tercakup; kategori dipilih manual pada formulir"]
```

Pemeriksaan tanpa label usia dipetakan ke kategori 3 sesuai keputusan implementasi sebelumnya. Pemeriksaan berlabel bayi menggunakan kategori 2; bayi lahir menggunakan kategori 1. Tidak ada penggantian otomatis dengan ambang kategori lain.

## 3. Seluruh data dan relasi per laboratorium

Setiap diagram berikut menunjukkan baris tabel laboratorium, baris pemeriksaan yang dirujuk, satuan, batas bawah/atas, dan kategori. ID pada tabel laboratorium adalah nomor lokal; ID pemeriksaan unik untuk seluruh data. Angka menggunakan titik desimal tanpa pemisah ribuan: `30000` dan `1000000`. Batas `NULL` ditampilkan sebagai `-` dalam aplikasi. Kondisi kritis menggunakan operator `<` untuk batas bawah dan `>` untuk batas atas.

### Hematologi

```mermaid
flowchart LR
    lab1["hematologi: id=1; id_pemeriksaan=1"]
    pemeriksaan1["pemeriksaan: id=1; HCT; satuan=%; batas_bawah=20; batas_atas=60; id_kategori=3"]
    lab1 -->|"id_pemeriksaan"| pemeriksaan1
    pemeriksaan1 -->|"id_kategori"| kategori3
    lab2["hematologi: id=2; id_pemeriksaan=2"]
    pemeriksaan2["pemeriksaan: id=2; HCT (bayi); satuan=Vol %; batas_bawah=33; batas_atas=70; id_kategori=2"]
    lab2 -->|"id_pemeriksaan"| pemeriksaan2
    pemeriksaan2 -->|"id_kategori"| kategori2
    lab3["hematologi: id=3; id_pemeriksaan=3"]
    pemeriksaan3["pemeriksaan: id=3; HGB; satuan=g/dl; batas_bawah=5; batas_atas=20; id_kategori=3"]
    lab3 -->|"id_pemeriksaan"| pemeriksaan3
    pemeriksaan3 -->|"id_kategori"| kategori3
    lab4["hematologi: id=4; id_pemeriksaan=4"]
    pemeriksaan4["pemeriksaan: id=4; HGB (bayi); satuan=g/dl; batas_bawah=9.5; batas_atas=22; id_kategori=2"]
    lab4 -->|"id_pemeriksaan"| pemeriksaan4
    pemeriksaan4 -->|"id_kategori"| kategori2
    lab5["hematologi: id=5; id_pemeriksaan=5"]
    pemeriksaan5["pemeriksaan: id=5; Leukosit; satuan=g/dl; batas_bawah=200; batas_atas=30000; id_kategori=3"]
    lab5 -->|"id_pemeriksaan"| pemeriksaan5
    pemeriksaan5 -->|"id_kategori"| kategori3
    lab6["hematologi: id=6; id_pemeriksaan=6"]
    pemeriksaan6["pemeriksaan: id=6; Trombosit Dewasa; satuan=g/dl; batas_bawah=50; batas_atas=1000000; id_kategori=3"]
    lab6 -->|"id_pemeriksaan"| pemeriksaan6
    pemeriksaan6 -->|"id_kategori"| kategori3
    lab7["hematologi: id=7; id_pemeriksaan=7"]
    pemeriksaan7["pemeriksaan: id=7; Aktivated Platelet Thromboplastin Time (AppTT); satuan=NULL; batas_bawah=NULL; batas_atas=78; id_kategori=3"]
    lab7 -->|"id_pemeriksaan"| pemeriksaan7
    pemeriksaan7 -->|"id_kategori"| kategori3
    kategori3["kategori: id=3; Anak & dewasa"]
    kategori2["kategori: id=2; Bayi"]
```

### Kimia Klinik

```mermaid
flowchart LR
    lab8["kimia_klinik: id=1; id_pemeriksaan=8"]
    pemeriksaan8["pemeriksaan: id=8; Ureum; satuan=mg/dl; batas_bawah=NULL; batas_atas=200; id_kategori=3"]
    lab8 -->|"id_pemeriksaan"| pemeriksaan8
    pemeriksaan8 -->|"id_kategori"| kategori3
    lab9["kimia_klinik: id=2; id_pemeriksaan=9"]
    pemeriksaan9["pemeriksaan: id=9; Kreatinin; satuan=mg/dl; batas_bawah=NULL; batas_atas=9.5; id_kategori=3"]
    lab9 -->|"id_pemeriksaan"| pemeriksaan9
    pemeriksaan9 -->|"id_kategori"| kategori3
    lab10["kimia_klinik: id=3; id_pemeriksaan=10"]
    pemeriksaan10["pemeriksaan: id=10; Glukosa; satuan=mg/dl; batas_bawah=40; batas_atas=450; id_kategori=3"]
    lab10 -->|"id_pemeriksaan"| pemeriksaan10
    pemeriksaan10 -->|"id_kategori"| kategori3
    lab11["kimia_klinik: id=4; id_pemeriksaan=11"]
    pemeriksaan11["pemeriksaan: id=11; Glukosa Bayi; satuan=mg/dl; batas_bawah=35; batas_atas=450; id_kategori=2"]
    lab11 -->|"id_pemeriksaan"| pemeriksaan11
    pemeriksaan11 -->|"id_kategori"| kategori2
    lab12["kimia_klinik: id=5; id_pemeriksaan=12"]
    pemeriksaan12["pemeriksaan: id=12; Natrium; satuan=mmol/l; batas_bawah=120; batas_atas=160; id_kategori=3"]
    lab12 -->|"id_pemeriksaan"| pemeriksaan12
    pemeriksaan12 -->|"id_kategori"| kategori3
    lab13["kimia_klinik: id=6; id_pemeriksaan=13"]
    pemeriksaan13["pemeriksaan: id=13; Kalium; satuan=mmol/l; batas_bawah=2.8; batas_atas=6.2; id_kategori=3"]
    lab13 -->|"id_pemeriksaan"| pemeriksaan13
    pemeriksaan13 -->|"id_kategori"| kategori3
    lab14["kimia_klinik: id=7; id_pemeriksaan=14"]
    pemeriksaan14["pemeriksaan: id=14; Kalium (bayi lahir); satuan=mmol/l; batas_bawah=2.5; batas_atas=8; id_kategori=1"]
    lab14 -->|"id_pemeriksaan"| pemeriksaan14
    pemeriksaan14 -->|"id_kategori"| kategori1
    lab15["kimia_klinik: id=8; id_pemeriksaan=15"]
    pemeriksaan15["pemeriksaan: id=15; Klorida; satuan=mmol/l; batas_bawah=70; batas_atas=120; id_kategori=3"]
    lab15 -->|"id_pemeriksaan"| pemeriksaan15
    pemeriksaan15 -->|"id_kategori"| kategori3
    lab16["kimia_klinik: id=9; id_pemeriksaan=16"]
    pemeriksaan16["pemeriksaan: id=16; Bilirubin (bayi); satuan=mg/dl; batas_bawah=NULL; batas_atas=17; id_kategori=2"]
    lab16 -->|"id_pemeriksaan"| pemeriksaan16
    pemeriksaan16 -->|"id_kategori"| kategori2
    kategori3["kategori: id=3; Anak & dewasa"]
    kategori2["kategori: id=2; Bayi"]
    kategori1["kategori: id=1; Bayi lahir"]
```

### Analisa Gas Darah (AGD)

```mermaid
flowchart LR
    lab17["agd: id=1; id_pemeriksaan=17"]
    pemeriksaan17["pemeriksaan: id=17; PH; satuan=mmHg; batas_bawah=7.15; batas_atas=7.7; id_kategori=3"]
    lab17 -->|"id_pemeriksaan"| pemeriksaan17
    pemeriksaan17 -->|"id_kategori"| kategori3
    lab18["agd: id=2; id_pemeriksaan=18"]
    pemeriksaan18["pemeriksaan: id=18; PO2; satuan=mmHg; batas_bawah=40; batas_atas=NULL; id_kategori=3"]
    lab18 -->|"id_pemeriksaan"| pemeriksaan18
    pemeriksaan18 -->|"id_kategori"| kategori3
    lab19["agd: id=3; id_pemeriksaan=19"]
    pemeriksaan19["pemeriksaan: id=19; PCO2; satuan=mmHg; batas_bawah=20; batas_atas=60; id_kategori=3"]
    lab19 -->|"id_pemeriksaan"| pemeriksaan19
    pemeriksaan19 -->|"id_kategori"| kategori3
    kategori3["kategori: id=3; Anak & dewasa"]
```

## 4. Alur input dan penentuan kritis

Diagram mengikuti validasi formulir dan fungsi `tentukanKritis()` di `index.php`. Batas dibaca dari data pemeriksaan, bukan dari input pengguna. Tanda `-` atau `NULL` hanya diterima sebagai batas yang tidak tersedia; hasil pemeriksaan harus berupa angka.

```mermaid
flowchart TD
    mulai(["Mulai"])
    inputForm["Pilih laboratorium, pemeriksaan dan kategori; masukkan nilai hasil"]
    validForm{"Format input dan pilihan relasi valid?"}
    ambilAturan["Ambil pemeriksaan melalui id_pemeriksaan dan kategori melalui id_kategori"]
    validAngka{"Hasil dan batas tersedia berupa angka valid dan finite?"}
    normalisasi["Normalisasi koma desimal; batas NULL atau tanda - menjadi NULL"]
    urutanBatas{"Kedua batas tersedia dan batas bawah melebihi batas atas?"}
    kosong{"Kedua batas NULL?"}
    cekBawah{"Batas bawah tersedia dan hasil kurang dari batas bawah?"}
    cekAtas{"Batas atas tersedia dan hasil lebih dari batas atas?"}
    kritisBawah["Kritis: di bawah batas bawah; is_kritis=true"]
    kritisAtas["Kritis: di atas batas atas; is_kritis=true"]
    tidakKritis["Tidak kritis; is_kritis=false"]
    belumDiketahui["Tidak dapat ditentukan; is_kritis=NULL"]
    gagal["Tampilkan kesalahan validasi"]
    tampil["Tampilkan status, alasan, nilai, satuan, batas, dan relasi yang digunakan"]
    selesai(["Selesai"])

    mulai --> inputForm --> validForm
    validForm -->|"Tidak"| gagal
    validForm -->|"Ya"| ambilAturan --> validAngka
    validAngka -->|"Tidak"| gagal
    validAngka -->|"Ya"| normalisasi --> urutanBatas
    urutanBatas -->|"Ya"| gagal
    urutanBatas -->|"Tidak"| kosong
    kosong -->|"Ya"| belumDiketahui
    kosong -->|"Tidak"| cekBawah
    cekBawah -->|"Ya"| kritisBawah
    cekBawah -->|"Tidak"| cekAtas
    cekAtas -->|"Ya"| kritisAtas
    cekAtas -->|"Tidak"| tidakKritis
    kritisBawah --> tampil
    kritisAtas --> tampil
    tidakKritis --> tampil
    belumDiketahui --> tampil
    tampil --> selesai
    gagal --> selesai
```

Nilai tepat pada batas tidak dianggap kritis. Status tidak kritis bukan penetapan bahwa hasil normal. Input desimal menerima titik atau koma, tanpa pemisah ribuan. Hasil formulir tidak disimpan ke database.