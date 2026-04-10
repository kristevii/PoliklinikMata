# 🏥 Eyethica Klinik

### Sistem Informasi Klinik Kesehatan Mata

Eyethica Klinik adalah sistem informasi berbasis web yang dikembangkan
untuk mendukung pengelolaan layanan klinik kesehatan mata secara
**terintegrasi, cepat, dan efisien**.
Sistem ini membantu proses administrasi klinik mulai dari pengelolaan
data pasien, jadwal dokter, rekam medis, antrian, hingga laporan
keuangan sehingga pelayanan kepada pasien menjadi lebih optimal.

## 「 ✦ Fitur Utama 」

### 👨‍⚕️ Manajemen Dokter

-   Tambah, ubah, dan hapus data dokter
-   Pengaturan jadwal praktik
-   Informasi profil serta spesialisasi dokter

### 🧑‍🦯 Manajemen Pasien

-   Pendaftaran pasien baru
-   Riwayat kunjungan lengkap
-   Rekam medis digital yang mudah diakses

### 📅 Jadwal Pemeriksaan

-   Pengelolaan jadwal dokter
-   Monitoring status pemeriksaan pasien

### 📑 Rekam Medis

-   Pencatatan detail pemeriksaan mata
-   Diagnosa dan catatan tindakan
-   Resep obat dan rekomendasi perawatan
-   Riwayat konsultasi pasien

### 🔔 Manajemen Antrian

-   Penomoran antrian otomatis
-   Panggilan pasien
-   Monitoring status antrian real-time

### 💳 Pembayaran & Administrasi

-   Input biaya tindakan & obat
-   Rekap transaksi harian, mingguan, dan bulanan

### 📊 Laporan & Dashboard

-   Statistik pasien dan kunjungan
-   Grafik visual menggunakan Chart.js
-   Laporan administrasi & keuangan

## 🛠️ Teknologi yang Digunakan

-   **PHP**
-   **Bootstrap** & **Tailwind CSS**
-   **MySQL Database**
-   **Able Pro**
-   **jQuery & AJAX**
-   **DataTables**
-   **Chart.js**
-   **SweetAlert**

## ⌞🖼️ Tampilan Antarmuka Pengguna⌝
Desain antarmuka dibuat intuitif dan bersih, memastikan staf klinik dapat menguasai sistem dengan cepat, mengurangi waktu pelatihan, dan meningkatkan efisiensi kerja sehari-hari.

### ╰⪼ 🔐 Login (Sebagai Dokter atau Staff)
Halaman login digunakan oleh pengguna yang memiliki akun terdaftar, baik sebagai dokter maupun staff.
Pengguna memasukkan username / email dan password, kemudian sistem akan memverifikasi kredensial.
Jika berhasil, pengguna diarahkan ke Dashboard sesuai role masing-masing.
Sistem juga dilengkapi validasi input serta pesan error apabila login gagal.
<img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/login.png" width="500">

### ╰⪼ 📊 Dashboard
Dashboard merupakan halaman utama setelah login yang menampilkan ringkasan informasi klinik.
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/dashboard1.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/dashboard2.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/dashboard3.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/dashboard4.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/dashboard5.png" width="455">
</div>

### ╰⪼ 📁 Data
Mengelola seluruh informasi klinik, seperti: 
- Data User
- Data Dokter
- Data Staff
- Data Pasien
- Data Rekam Medis
- Data Kontrol
- Data Antrian
- Data Transaksi

Dilengkapi pencarian, sorting, pagination DataTables, serta aksi edit dan hapus.
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/datausers.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/datadokter.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/datastaff.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/datarekammedis.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/datakontrol.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/dataantrian.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/panggilanantrian1.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/panggilanantrian2.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/datatransaksi.png" width="455">
</div>

### ╰⪼ ➕ Tambah Data
Untuk memasukkan data baru ke sistem, dilengkapi validasi input dan notifikasi sukses menggunakan SweetAlert.

<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/tambahuser.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/tambahdokter.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/tambahstaff.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/tambahrekammedis.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/tambahkontrol.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/tambahantrian.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/tambahtransaksi.png" width="455">
</div>

### ╰⪼ ✏️ Edit Data

Untuk memperbarui data yang sudah ada. Sistem menampilkan notifikasi berhasil serta mencatat aktivitas update.

<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/edituser.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/editdokter.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/editstaff.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/editrekammedis.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/editkontrol.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/editantrian.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/editransaksi.png" width="455">
</div>

### ╰⪼ 🗑️ Hapus Data
Menghapus data yang tidak diperlukan dengan dialog konfirmasi SweetAlert untuk mencegah kesalahan.

<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/hapususer.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/hapusdokter.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/hapusstaff.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/hapusrekammedis.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/hapuskontrol.png" width="455">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/hapusantrian.png" width="455">
</div>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/80eb1d76d582019ec27bbf02d4f85e6081beb1d0/preview/hapustransaksi.png" width="455">
</div>

## ── .✦ Rancangan Database 

#### ⤿ Tabel Users
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/users.png" width="500">
</div>

#### ⤿ Tabel Data Dokter
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/dokter.png" width="500">
</div>

#### ⤿ Tabel Data Staff
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/staff.png" width="500">
</div>

#### ⤿ Tabel Data Pasien
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/pasien.png" width="500">
</div>

#### ⤿ Tabel Data Rekam Medis
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/rekam.png" width="500">
</div>

#### ⤿ Tabel Data Kontrol
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/kontrol.png" width="500">
</div>

#### ⤿ Tabel Data Antrian
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/antrian.png" width="500">
</div>

#### ⤿ Tabel Data Transaksi
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/transaksi.png" width="500">
</div>

#### ⤿ Tabel Data Aktivitas Profile
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/aktivitasprofile.png" width="500">
</div>

#### ⤿ Tabel Data Aktivitas User
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
  <img src="https://github.com/kristevii/EyethicaKlinik/blob/845cf7e2306d96caa21de070cd2b14ce7b428b03/preview/aktivitasuser.png" width="500">
</div>
