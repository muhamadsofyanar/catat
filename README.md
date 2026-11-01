# LegaVoice — GitHub dan Coolify

Aplikasi PHP untuk proyek, klien, mitra, transaksi, piutang, hutang, dan dokumen. Paket ini menyiapkan PHP 8.4 + Apache + SQLite melalui Docker. Tidak perlu Node.js, Composer, atau database MySQL terpisah untuk menjalankan aplikasi.

## Import data lama melalui aplikasi

Versi ini menyediakan menu **admin → Import Data Lama**. Jika aplikasi sudah terpasang, unggah isi paket kode terbaru ke repository yang sama, commit perubahan, lalu deploy ulang di Coolify. Environment dan persistent storage tetap menggunakan pengaturan yang sudah dibuat.

File **`legavoice-data-lama.json` diberikan terpisah** dari paket kode. File tersebut berisi data bisnis pribadi; simpan di komputer Anda dan jangan unggah ke GitHub.

1. Setelah deploy ulang selesai, login dengan akun admin saat ini.
2. Klik nama admin di kanan atas, kemudian **Import Data Lama**. Alternatif: buka `/public/import_data.php` pada domain aplikasi.
3. Pilih `legavoice-data-lama.json`, lalu klik **Periksa File**.
4. Cocokkan ringkasannya: **55 proyek, 69 transaksi, 49 klien, 2 mitra, 5 layanan**.
5. Ketik **IMPORT DATA LAMA**, lalu klik **Import Sekarang**.
6. Buka Dashboard. Halaman import juga menyediakan unduhan database tujuan sebelum import.

Import hanya berlaku pada aplikasi yang belum berisi proyek, transaksi, klien, layanan, atau data bisnis terkait. Proyek internal kosong yang dibuat otomatis saat membuka Transaksi Umum tidak menghalangi import. Aplikasi menggantikan proyek internal kosong tersebut di dalam transaksi, setelah backup dibuat; proyek internal yang memiliki nilai, catatan, transaksi, biaya, atau dokumen tetap dilindungi. Jika ada data bisnis lain, halaman menampilkan jumlah data penyebab blokir. Import tidak menggabungkan data ke aplikasi yang sudah digunakan dan tidak menghapus data bisnis yang telah ada. Jangan jalankan metode penyalinan SQLite manual dan metode import JSON pada instalasi yang sama.

Akun login saat ini dan log aktivitas aplikasi baru tetap dipertahankan. Tiga akun serta log aktivitas dari database lama tidak dimasukkan; buat kembali akun staf melalui Manajemen User bila dibutuhkan. Identitas perusahaan, mitra bawaan, dan pengaturan akan mengikuti sumber lama. Paket import menyertakan dua berkas unggahan lama; database sumber tidak memiliki record dokumen proyek sehingga import tidak membuat relasi dokumen baru secara otomatis.

File sumber memiliki enam referensi layanan kosong (`service_id = ""`); paket mengubahnya menjadi `null`. Nominal proyek dan transaksi dipertahankan. Status `completed` dinormalisasi menjadi `done`, sesuai standar aplikasi hasil perbaikan.

Import memeriksa format, ID, relasi, jenis berkas, checksum, CSRF, dan hak admin. Aplikasi membuat snapshot SQLite sebelum penulisan, menjalankan perubahan dalam transaksi, menolak import berulang, dan membersihkan berkas yang baru dibuat bila transaksi gagal. Backup database tidak mencakup folder uploads; pertahankan ZIP sumber asli sebagai cadangan lengkap data lama.

## 1. Upload ke GitHub

1. Ekstrak ZIP hasil perbaikan.
2. Buat repository GitHub; gunakan **Private** untuk aplikasi internal.
3. Upload **isi folder `legavoice`**, bukan ZIP atau folder induknya. Pastikan `Dockerfile`, `.gitignore`, `.dockerignore`, `.env.example`, `docker/`, `public/`, dan `.github/` ikut terunggah. `Dockerfile` harus berada pada root repository.
4. Jangan upload `.env`, database SQLite, folder `uploads`, folder `tmp`, atau ZIP lama. Paket kode sudah memisahkan data tersebut.

Alternatif melalui Git, jalankan dari folder yang berisi Dockerfile:

```bash
git init
git add .
git commit -m "Prepare LegaVoice for Coolify"
git branch -M main
git remote add origin https://github.com/USERNAME/REPOSITORY.git
git push -u origin main
```

Ganti USERNAME dan REPOSITORY sesuai repository Anda. Folder belum berisi remote Git atau kredensial GitHub.

## 2. Buat aplikasi di Coolify

Hubungkan repository melalui GitHub App atau deploy key untuk repository private. Pilih **Dockerfile** sebagai Build Pack/Build Strategy.

| Pengaturan | Nilai |
|---|---|
| Branch | `main` |
| Base Directory | `/` |
| Dockerfile Location | `/Dockerfile` |
| Ports Exposes | `80` |
| Domain | Domain HTTPS milik Anda |
| Health Check Path | `/public/healthz.php` |
| Health Check Port | `80` |
| Health Check Method | `GET` |
| Health Check Expected Status | `200` |

Apache mendengarkan port 80 di dalam container. Gunakan satu instance aplikasi untuk SQLite. Matikan rolling deployment bila tersedia agar container lama dan baru tidak menulis database yang sama bersamaan.

## 3. Isi Environment Variables

Masukkan sebagai **runtime variables** di Coolify. Jangan masukkan rahasia ke Dockerfile, repository, atau build arguments.

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/storage/database/database.sqlite
ADMIN_USERNAME=admin
ADMIN_PASSWORD=ISI_PASSWORD_UNIK_MINIMAL_12_KARAKTER
JWT_SECRET=ISI_SECRET_ACAK_MINIMAL_32_KARAKTER
SESSION_SECURE_COOKIE=true
```

Buat secret sendiri, misalnya dengan `openssl rand -hex 32`. Jangan gunakan teks contoh sebagai password/secret. Aktifkan HTTPS sebelum login; cookie Secure tidak dikirim browser melalui HTTP.

`ADMIN_USERNAME` dan `ADMIN_PASSWORD` hanya membuat admin ketika tabel pengguna masih kosong. Mengubah variabel ini tidak mengganti password pengguna pada database yang sudah ada. Gunakan menu Users untuk perubahan password berikutnya. `JWT_SECRET` wajib tetap sama antar-redeploy; menggantinya membatalkan token API lama.

## 4. Tambahkan Persistent Storage SEBELUM deploy

Gunakan tiga Volume Mount berikut. Nama volume bebas; **Destination Path wajib tepat**.

| Contoh nama | Destination Path | Isi |
|---|---|---|
| `legavoice-storage` | `/var/www/html/storage` | Database SQLite |
| `legavoice-uploads` | `/var/www/html/uploads` | Dokumen dan logo yang diunggah |
| `legavoice-sessions` | `/var/www/html/tmp` | Sesi login |

Jangan mount seluruh `/var/www/html`; mount tersebut menutupi kode aplikasi dari image. Jangan memakai satu volume yang sama untuk ketiga tujuan tersebut. Entrypoint menyiapkan izin tulis untuk pengguna Apache (`www-data`).

Pilih **Deploy**, periksa build/runtime logs, lalu buka domain HTTPS Anda. Login mengarah ke `/public/login.php`; halaman utama tersedia pada `/public/index.php`.

Setelah membuat proyek uji, lakukan redeploy dan pastikan proyek tetap ada. Uji juga upload dan download dokumen. Volume mempertahankan data saat redeploy, tetapi tidak menggantikan backup.

## 5. Memindahkan data lama dari ZIP asli

Paket kode hasil perbaikan menggunakan database kosong agar data bisnis dan sesi lama tidak masuk ke GitHub. ZIP asli Anda tetap memuat database dan dokumen lama.

Untuk migrasi pertama, gunakan **Directory Mount** agar lokasi sumber pada VPS mudah diakses melalui SFTP:

| Source Path VPS, contoh | Destination Path container |
|---|---|
| `/data/legavoice/storage` | `/var/www/html/storage` |
| `/data/legavoice/uploads` | `/var/www/html/uploads` |
| `/data/legavoice/tmp` | `/var/www/html/tmp` |

Sebelum aplikasi pertama kali dijalankan:

1. Buat `/data/legavoice/storage/database` pada VPS.
2. Salin `config/database.sqlite` dari ZIP asli ke `/data/legavoice/storage/database/database.sqlite`.
3. Salin **isi** folder `uploads` dari ZIP asli ke `/data/legavoice/uploads`, dengan struktur subfolder tetap sama.
4. Jika logo lama disimpan pada `public/logo.png`, unggah ulang melalui Settings setelah login. Logo Settings yang baru disimpan pada volume uploads.
5. Biarkan folder sesi baru kosong. Jangan migrasikan file sesi dari `tmp` lama.
6. Deploy. Aplikasi menggunakan akun yang tersimpan pada database lama dan menjalankan migrasi skema yang diperlukan. Password pada environment tidak mereset akun lama.

Jangan menimpa database aplikasi yang sedang berjalan. Untuk mengganti database setelah aplikasi digunakan, hentikan aplikasi, cadangkan database dan uploads, lakukan penggantian saat tidak ada penulisan, lalu jalankan kembali. Pertahankan salinan data sebelum migrasi agar rollback tetap tersedia.

## 6. Backup dan pembaruan

Menu backup admin menghasilkan snapshot SQLite konsisten menggunakan `VACUUM INTO`. Backup ini hanya berisi database; cadangkan uploads secara terpisah. Jadwalkan backup storage ke lokasi terpisah dan uji restore. Setelah mengunggah perubahan ke GitHub, deploy commit terbaru melalui Coolify. Gunakan rebuild tanpa cache bila perlu memperbarui base image PHP.

## 7. Pengujian

Workflow `.github/workflows/checks.yml` membangun image, memeriksa sintaks PHP, menjalankan regresi, serta memeriksa startup dan endpoint health. Workflow baru berjalan setelah repository menerima push.

Pengujian manual melalui Docker:

```bash
docker build --pull -t legavoice .
docker run --rm --entrypoint php legavoice tests/regression.php
```

Pengujian yang sudah dijalankan pada lingkungan pemeriksaan: parser PHP 8.4 untuk 93 berkas; 23 pemeriksaan regresi umum dan 21 pemeriksaan import; permintaan halaman utama, penyimpanan proyek/transaksi/prive; serta login API, JWT invalid, Settings, upload/download dokumen, dan backup SQLite. Runtime pengujian memakai PHP WebAssembly 8.4.25. Build Docker/Apache dan deployment VPS belum dijalankan pada lingkungan pemeriksaan ini. Lihat `HASIL_PEMERIKSAAN.md` untuk rincian dan batas pengujian.

## Batas penggunaan

Paket deployment ini menetapkan SQLite. Dukungan MySQL pada kode lama belum tervalidasi dan tidak diaktifkan oleh entrypoint. Gunakan aplikasi untuk satu perusahaan internal; isolasi antartenant pada seluruh endpoint lama belum diaudit. Perlindungan CSRF untuk seluruh aksi lama dan konsistensi definisi laba antarhalaman masih memerlukan audit terpisah. Formula alokasi prive yang dipertahankan mengikuti implementasi lama, bukan verifikasi akuntansi independen.

## Dokumentasi acuan

- [Coolify: Dockerfile](https://coolify.io/docs/applications/builds/dockerfile)
- [Coolify: Persistent Storage](https://coolify.io/docs/applications/configuration/persistent-storage)
- [PHP: Supported Versions](https://www.php.net/supported-versions.php)
