# Hasil pemeriksaan LegaVoice

Tanggal: 1 November 2026. Sumber: `legavoice.zip` yang dilampirkan. Pemeriksaan mencakup kode aktif, bootstrap database, autentikasi JWT, beberapa alur simpan, dan konfigurasi deployment. Pemeriksaan ini bukan audit keamanan atau akuntansi menyeluruh.

## Temuan dan perbaikan

| Masalah sumber | Dampak | Perbaikan |
|---|---|---|
| Spasi sesudah tag penutup PHP pada include | Redirect/status HTTP gagal karena output sudah terkirim | Menghapus tag penutup pada akhir berkas PHP beserta whitespace akhir |
| `Project::getAll` mencampur parameter bernama dan posisi | Daftar proyek/filter dapat gagal atau mengikat parameter keliru | Menggunakan parameter posisi secara konsisten |
| `Transaction::find` menggunakan `fetchColumn` | Pemanggil menerima nilai scalar, bukan record transaksi | Mengembalikan `fetch`; membatasi update/delete model dengan company ID |
| `original_price` tidak ada dalam skema baru | INSERT/UPDATE proyek gagal pada instalasi bersih | Menambahkan migrasi kolom dan menyalin initial_price ketika kolom baru ditambahkan |
| Migrasi hanya berjalan ketika tabel projects belum ada | Database lama tidak menerima pembaruan skema | Menjalankan skema idempoten saat koneksi dibuka |
| Helper skema mengambil driver melalui global yang tidak konsisten dengan scope connect | Pemeriksaan tabel/kolom dapat gagal | Membaca driver langsung dari PDO |
| Settings baru belum memiliki `reminder_min_interval_days` pada permintaan pertama | Penyimpanan Settings pertama dapat gagal | Menyertakan kolom pada CREATE TABLE |
| Upload dokumen memakai `NOW()` | SQLite menolak INSERT | Menggunakan CURRENT_TIMESTAMP |
| Handler prive/pengembalian meng-include file yang tidak tersedia | Kedua alur simpan berhenti dengan fatal error | Menambahkan handler dan model OwnerFunds, validasi tanggal/jumlah, transaksi atomik, serta batas penarikan/pengembalian |
| Form menggunakan `completed`, sebagian laporan memakai `done` | Proyek selesai dapat tetap dihitung aktif | Menetapkan done dan migrasi completed; mempertahankan cancelled |
| `htmlspecialchars` menerima NULL dari database | Deprecation pada PHP modern | Memberikan fallback string kosong pada nilai kolom nullable |
| Loader environment tidak mempertahankan nilai `0`, tidak menangani kutipan/line invalid dengan baik | Konfigurasi dapat dibaca salah | Loader bersama dengan prioritas environment proses |
| JWT memakai secret fallback dan decoding base64 biasa | Secret mudah diprediksi dan token base64url tertentu tidak terbaca benar | Secret environment wajib, base64url, hash_equals, validasi algoritma/expiry/payload |
| Invoice tidak memeriksa sesi | Data invoice dapat terbuka tanpa login | Menambahkan pemeriksaan autentikasi |
| Dokumen diakses sebagai file publik | Kontrol sesi tidak diterapkan pada unduhan | Endpoint download dengan autentikasi, pemeriksaan perusahaan/proyek, dan pembatasan path; Apache menolak akses langsung ke direktori dokumen |
| Logo Settings ditulis di dalam kode public | Logo hilang setelah redeploy | Menyimpan logo pada uploads persisten |
| Backup membaca file SQLite aktif langsung | Snapshot berisiko tidak konsisten saat penulisan berlangsung | VACUUM INTO pada berkas sementara, hanya admin |
| ZIP mencakup database, sesi, dan ZIP terdahulu | Data operasional berpotensi masuk repository/image | Mengeluarkan semuanya dari paket kode dan menambahkan ignore files |
| Belum ada konfigurasi container/persistensi | Deploy memerlukan konfigurasi manual; data dapat hilang | Dockerfile, Apache/PHP config, entrypoint, health endpoint, CI, panduan volume dan migrasi |

## Bukti pengujian

- Database asli: `PRAGMA integrity_check` menghasilkan `ok`; `PRAGMA foreign_key_check` menghasilkan nol pelanggaran. Pemeriksaan tidak mengubah database asli.
- Kompatibilitas data lama: salinan database asli berhasil menjalankan bootstrap dan query model proyek tanpa warning/error PHP.
- Sintaks: 90 berkas PHP berhasil diparse dengan PHP 8.4.25 WebAssembly.
- Regresi: `tests/regression.php`, 23 pemeriksaan lulus. Cakupan: skema baru, password admin dari environment, query proyek dengan/tanpa filter, filter perusahaan pada model proyek, record transaksi, timestamp dokumen SQLite, JWT valid/rusak/kedaluwarsa, prive/pengembalian, rollback ketika melampaui batas, migrasi kolom/status, serta nilai environment `0`.
- Permintaan PHP: halaman dashboard, proyek, detail proyek/klien, arus kas, piutang/hutang, Settings, pengguna, aktivitas, mitra, layanan, laporan, cetak invoice/BAST, formulir prive/pengembalian, dan health menghasilkan respons yang diharapkan tanpa warning/deprecation PHP pada fixture uji.
- POST proyek, transaksi, dan prive menghasilkan redirect setelah tersimpan. Pengembalian melebihi saldo hutang menghasilkan HTTP 422.
- Pemeriksaan tambahan: Settings baru tersimpan; unggah PDF dan unduhan terautentikasi berhasil; invoice/dokumen tanpa sesi mengarah ke login; backup menghasilkan berkas SQLite; login API menghasilkan JWT; API menerima token valid dan menolak token yang diubah dengan HTTP 401.
- Skrip shell entrypoint lolos `sh -n`.

## Batas verifikasi dan pekerjaan lanjutan

Docker tidak tersedia pada lingkungan pemeriksaan. Image belum dibangun; konfigurasi Apache, volume, reverse proxy HTTPS, dan startup pada VPS perlu diverifikasi melalui CI serta deploy Coolify. Pengujian permintaan PHP bukan pengujian visual pada browser. Versi PHP WebAssembly uji berbeda dari patch terbaru yang akan ditarik oleh image `php:8.4-apache`.

Kode sumber memiliki controller Dashboard lama yang belum terhubung ke entry point aktif dan merujuk view yang belum disertakan. Dashboard aktif menggunakan `public/index.php`; jalur controller tersebut tidak digunakan atau diaktifkan dalam paket ini.

Beberapa endpoint lama belum menerapkan company_id secara menyeluruh; kolom company_id tidak membuktikan aplikasi siap multitenant. Aksi mutasi lama yang memakai GET dan perlindungan CSRF menyeluruh belum direfaktor. Definisi laba, pembayaran aktual, hutang vendor, dan alokasi 40/60 tidak seragam pada seluruh halaman; perbaikan ini tidak mengesahkan rumus tersebut sebagai laporan akuntansi. Handler prive yang baru mengikuti formula pada form lama dan menerapkan batas pada server.

Data operasional tidak disertakan dalam paket GitHub. Untuk mempertahankan data lama, ikuti bagian migrasi README dengan database dan uploads dari ZIP asli. Kredensial akun lama tidak diubah oleh paket kode ini.
