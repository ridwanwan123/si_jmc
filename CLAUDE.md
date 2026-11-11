# Web JMC — Jakarta Madrasah Competition

Website publik untuk menampilkan hasil **Jakarta Madrasah Competition (JMC)**, lomba tingkat provinsi untuk madrasah di DKI Jakarta yang diselenggarakan Bidang Pendidikan Madrasah, Kanwil Kemenag Provinsi DKI Jakarta.

Konsepnya seperti web Asian Games: **klasemen medali**, daftar cabor dan lomba, juara tiap lomba, profil madrasah, dan galeri dokumentasi. Data hasil sudah direkap manual oleh panitia; web ini **hanya menampilkan** apa yang diinput admin.

Mockup desain (versi B, terpilih) ada di **`docs/mockup/`**: Beranda, Klasemen Medali, Cabor & Lomba, Profil Madrasah, Galeri. **Sebelum membuat view publik, baca `docs/mockup/README.md`, file HTML statis halaman terkait, dan lihat screenshot-nya di `docs/mockup/screenshots/`**, lalu buat tampilannya semirip mungkin dengan mockup. Logo ada di `docs/mockup/assets/`.

---

## Stack & lingkungan

- Laravel 12+ (PHP 8.3), MySQL
- Lokal: **Laragon di Windows**. Perintah ditulis agar jalan di terminal Windows (PowerShell / Laragon terminal).
- Halaman publik: **Blade + Tailwind CSS (Vite) + Alpine.js**. Tidak pakai SPA.
- Panel admin: **Filament** (versi terbaru yang stabil), di path `/admin`.
- Gambar: `intervention/image` untuk kompres + thumbnail (WebP).
- **Lingkup dokumen ini: development lokal saja** (Laragon). Deploy ke server/production **tidak termasuk**; jangan buat konfigurasi Nginx, script deploy, atau pengaturan server kecuali diminta.

## Aturan umum kode

- Bahasa UI: **Indonesia**. Nama tabel dan kolom: bahasa Indonesia, `snake_case`.
- Validasi pakai **Form Request** (publik) atau validasi bawaan Filament (admin).
- **Migration**: selama tahap development (belum ada data production), perubahan skema langsung diubah di file `create_*` lalu `migrate:fresh --seed`; tidak ada migration "ubah/hapus/add to". Setelah production, **jangan ubah migration yang sudah dijalankan**: buat migration baru.
- Selalu eager load relasi di halaman list (hindari N+1).
- Logika klasemen ada di **satu tempat**: `app/Services/KlasemenService.php`. Controller dan Filament memanggil service ini, jangan menghitung ulang sendiri-sendiri.
- Setiap fitur baru disertai **feature test** (Pest atau PHPUnit), terutama untuk perhitungan klasemen.
- Kerjakan bertahap: rencana dulu, tunggu persetujuan, baru tulis kode.

---

## Konsep domain

```
Master (dikelola admin):  Cabor ──< Mata Lomba (nama + jenjang + kategori + kuota default)

Edisi (JMC 2026, 2027, ...)
 └─ Lomba  ──> Mata Lomba (master) ──> Cabor
     │         jenjang: RA / MI / MTs / MA
     │         kategori: Putra / Putri / Umum / Beregu
     │         kuota juara: default dari master (1 emas, 2 perak, 3 perunggu), bisa diubah per edisi
     └─ Hasil: satu baris per juara ──> Madrasah (+ nama peserta)
```

- **Cabor** adalah master data (di-seed 7: Keagamaan, Sains, Olahraga, Seni, Riset, Robotik, GTK). Admin boleh mengubah nama/ikon/urutan dan menambah cabor; cabor yang sudah punya mata lomba atau lomba tidak boleh dihapus.
- **Mata Lomba** (`mata_lomba`) adalah master/katalog lomba tetap: cabor + nama + jenjang + kategori (unik) + kuota default + aktif/nonaktif. Mata lomba yang sudah dipakai tidak boleh dihapus, cukup dinonaktifkan.
- **Lomba** adalah satuan yang memperebutkan medali di satu **edisi**, contoh: "Tenis Meja" jenjang MTs kategori Putri di JMC 2026. Lomba **selalu dibuat dari master mata lomba** (tidak ada nama lomba bebas) lewat `app/Services/LombaService.php`; satu mata lomba maksimal sekali per edisi. Nama/jenjang/kategori/cabor disalin ke `lomba` sebagai snapshot agar riwayat tidak berubah jika master diganti.
- **Jenjang**: RA (Raudhatul Athfal), MI, MTs, MA.
- **Jenis medali hanya 3**: Emas (juara 1), Perak (juara 2), Perunggu (juara 3). Tidak ada juara harapan.
- **Juara bisa lebih dari satu per peringkat.** Setiap lomba punya **kuota juara** sendiri. Defaultnya **1 juara 1, 2 juara 2, 3 juara 3** (berlaku untuk hampir semua lomba), tapi admin bisa mengubahnya per lomba sesuai aturan lomba tersebut.
- Setiap juara = 1 medali untuk madrasahnya. Contoh: lomba dengan 2 juara 2 menghasilkan 2 medali perak (bisa untuk 2 madrasah berbeda atau madrasah yang sama).
- **Cabor GTK**: medali guru/tenaga kependidikan dihitung ke **madrasah tempat ia bertugas** dan masuk klasemen yang sama.
- **RA** ikut klasemen umum bersama MI/MTs/MA (saat ini RA hanya punya 1 lomba, yaitu Adzan) dan tetap bisa difilter lewat jenjang RA.
- **Lomba beregu** tetap dihitung **1 medali** untuk madrasah; nama anggota tim ditulis di `nama_peserta`.
- **Multi-edisi**: semua halaman publik menampilkan edisi aktif, dengan pilihan arsip edisi sebelumnya.

## Struktur database

| Tabel          | Kolom penting                                                                                                                                                                                                                                                            |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `edisi`        | `id`, `tahun` (unik), `nama`, `tanggal_mulai`, `tanggal_selesai`, `lokasi`, `pendaftaran_buka`, `pendaftaran_tutup`, `is_aktif` (bool, hanya satu yang true)                                                                                                       |
| `cabor`        | `id`, `nama`, `slug`, `ikon` (nama ikon), `urutan`                                                                                                                                                                                                                       |
| `madrasah`     | `id`, `npsn` (unik, wajib), `nama`, `jenjang` (enum RA/MI/MTs/MA), `status` (Negeri/Swasta), `logo` (nullable), `provinsi`, `kota` (enum Jakarta Pusat/Utara/Barat/Selatan/Timur/Kepulauan Seribu), `kecamatan`, `kelurahan`, `alamat_sekolah` (alamat nullable). Data awal: `MadrasahSeeder` dari `database/seeders/data/madrasah.sql` (sumber si_presma) |
| `mata_lomba`   | `id`, `cabor_id`, `pengelola_cabor_id` (cabor yang admin-nya mengurus; = cabor_id kecuali GTK), `nama`, `jenjang` (enum), `kategori` (enum), `kuota_juara_1/2/3` (default 1/2/3), `urutan`, `is_aktif`. Unik per (`cabor_id`, `nama`, `jenjang`, `kategori`)                                                                                         |
| `lomba`        | `id`, `edisi_id`, `cabor_id`, `pengelola_cabor_id` (snapshot dari master), `mata_lomba_id`, `nama`, `jenjang` (enum), `kategori` (enum Putra/Putri/Umum/Beregu), `kuota_juara_1` (default 1), `kuota_juara_2` (default 2), `kuota_juara_3` (default 3), `urutan`. Unik per (`edisi_id`, `mata_lomba_id`); nama/jenjang/kategori/cabor = snapshot dari master |
| `hasil`        | `id`, `lomba_id`, `peringkat` (1/2/3), `madrasah_id`, `nama_peserta` (nullable), `urutan` (urutan tampil dalam peringkat yang sama), timestamps. **Tidak unique per peringkat**: satu lomba bisa punya beberapa baris dengan peringkat sama, maksimal sesuai kuota lomba |
| `galeri_album` | `id`, `edisi_id`, `cabor_id` (nullable = umum, mis. pembukaan), `lomba_id` (nullable), `judul`, `slug`, `tanggal`, `sampul_foto_id` (nullable)                                                                                                                           |
| `galeri_foto`  | `id`, `album_id`, `path`, `thumb_path`, `keterangan` (nullable), `urutan`                                                                                                                                                                                                |
| `berita`       | `id`, `edisi_id`, `cabor_id` (nullable, untuk ikon placeholder), `kategori` (pengumuman/liputan/hasil), `judul`, `slug` (unik), `ringkasan`, `isi` (HTML; ditampilkan lewat `Berita::isiAman()`), `sampul`, `keterangan_sampul`, `penulis` (kosong = "Panitia {edisi}"), `tag` (json), `unggulan`, `sematkan_di_pita`, `terbit_pada` (NULL = draf; masa depan = terjadwal). Query publik di `BeritaService`. Contoh: `BeritaContohSeeder` |
| `users`        | `role` (superadmin / admin_cabor / admin_berita), `cabor_id` (wajib untuk admin_cabor). Aturan akses di `User::boleh...()` |

Catatan:

- Medali **tidak disimpan** sebagai kolom; diturunkan dari `hasil.peringkat` (1 = emas, 2 = perak, 3 = perunggu).
- Klasemen **tidak disimpan**; selalu dihitung dari `hasil`.
- Satu madrasah boleh muncul lebih dari sekali dalam lomba yang sama (peserta berbeda), termasuk di peringkat yang sama.
- **Validasi**: jumlah baris `hasil` per (`lomba_id`, `peringkat`) tidak boleh melebihi `kuota_juara_N` lomba itu. Validasi ini ada di satu tempat (mis. `HasilService` atau rule khusus) dan dipakai oleh form admin maupun import Excel.

---

## Aturan klasemen

Dihitung per edisi oleh `KlasemenService`:

```sql
SELECT m.id, m.nama,
  SUM(h.peringkat = 1) AS emas,
  SUM(h.peringkat = 2) AS perak,
  SUM(h.peringkat = 3) AS perunggu,
  COUNT(*)            AS total
FROM hasil h
JOIN lomba l    ON l.id = h.lomba_id
JOIN madrasah m ON m.id = h.madrasah_id
WHERE l.edisi_id = ?
  -- filter opsional: AND l.cabor_id = ?  /  AND m.jenjang = ?
GROUP BY m.id, m.nama
ORDER BY emas DESC, perak DESC, perunggu DESC, m.nama ASC;
```

- **Urutan peringkat**: emas terbanyak → perak → perunggu.
- **Peringkat sama**: jika emas, perak, dan perunggu sama persis, peringkatnya sama dan ditampilkan dengan tanda `=` (contoh `=04`). Peringkat berikutnya melompat (1, 2, =3, =3, 5).
- Madrasah tanpa medali tidak masuk klasemen.
- **Filter**: cabor (berdasarkan cabor lomba) dan jenjang (berdasarkan jenjang madrasah). Peringkat dihitung ulang sesuai filter.
- **Rincian per cabor** untuk tiap madrasah tersedia dari service yang sama (dipakai di baris klasemen yang dibuka dan di profil madrasah).
- **Pemuncak cabor** = peringkat 1 klasemen dengan filter cabor tersebut.
- **Jumlah madrasah peserta** (angka ringkas beranda, arsip, dashboard) tidak diinput: dihitung `KlasemenService::jumlahMadrasahPeserta()` = madrasah berbeda yang punya pendaftaran **diverifikasi** di edisi itu atau punya juara di edisi itu.
- **Terakhir diperbarui** = `MAX(hasil.updated_at)` untuk lomba di edisi itu, format `05.11.2026 · 11:30 WIB` (timezone `Asia/Jakarta`).

Query klasemen di atas tetap benar untuk juara ganda, karena setiap baris `hasil` dihitung sebagai 1 medali.

Wajib ada test untuk: urutan peringkat, peringkat sama (`=`), filter cabor, filter jenjang, lomba beregu, **juara ganda** (mis. 2 perak + 3 perunggu dalam satu lomba), dan **validasi kuota** (menolak juara melebihi kuota).

---

## Halaman publik

| Route                             | Halaman         | Isi                                                                                                                                                                                                                   |
| --------------------------------- | --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `/`                               | Beranda         | Hero, podium 1-2-3, ticker hasil terbaru, angka ringkas, klasemen top 5, daftar cabor + mata lomba di tiap cabor (nama lomba + jenjang; pemuncak tidak ditampilkan), galeri pilihan                                                                                                    |
| `/klasemen`                       | Klasemen Medali | Tab: Klasemen / Peraih Medali / Pencarian. Podium, filter jenjang + cabor, baris bisa dibuka untuk rincian per cabor                                                                                                  |
| `/cabor` dan `/cabor/{slug}`      | Cabor & Lomba   | Daftar cabor 01–07 di kiri; lomba dikelompokkan per jenjang (RA/MI/MTs/MA), tiap lomba menampilkan semua juaranya (dikelompokkan emas → perak → perunggu, bisa lebih dari satu nama per medali) atau "Menunggu hasil" |
| `/madrasah` dan `/madrasah/{npsn}` | Profil Madrasah | Peringkat, total medali, daftar juara (lomba + peserta + cabor), rincian per cabor, riwayat antar-edisi                                                                                                               |
| `/berita` dan `/berita/{slug}`    | Berita          | Pita pengumuman tersemat, filter kategori, berita unggulan, grid terbaru (muat +6), kolom pengumuman + ajakan daftar; detail: meta, menit baca, bagikan WA/salin tautan, isi, tag, berita lainnya. Beranda: unggulan + 3 terbaru |
| `/galeri` dan `/galeri/{slug}`    | Galeri          | Album per cabor (filter), grid foto, lightbox                                                                                                                                                                         |

- Pilihan edisi lewat query `?edisi=2025`; tanpa parameter = edisi aktif.
- Interaksi (tab, filter, buka baris, lightbox) pakai **Alpine.js** atau query string, tanpa reload yang tidak perlu.
- Pencarian: cari di `madrasah.nama` dan `hasil.nama_peserta`.

## Panel admin (Filament)

### Role

| Role | Akses |
| ---- | ----- |
| **Superadmin** | Semua: master (edisi, madrasah, cabor, mata lomba, lomba), semua cabor, berita, galeri, **Pengguna** |
| **Admin cabor** | Hanya halaman **Kelola Cabor** miliknya (`users.cabor_id`): lomba, input juara, verifikasi pendaftaran. Tidak bisa ubah pengaturan/hapus lomba |
| **Admin berita** | Berita dan galeri |

- **Lomba GTK** tidak punya admin khusus: tiap mata lomba GTK punya `pengelola_cabor_id` (mis. Badminton GTK → Olahraga, MTQ GTK → Keagamaan; ditebak dari nama lewat `MataLomba::tebakPengelolaGtk`, bisa diubah superadmin). Admin cabor pengelola melihatnya di halaman cabornya (`Lomba::untukCabor`), tapi **medalinya tetap dihitung ke cabor GTK**.
- Pembatasan dijaga di `canAccess()` resource/halaman, query (`Lomba::bolehDikelola`, `PendaftaranResource::getEloquentQuery`), dan aksi (`authorize`). Wajib ada test per role (`tests/Feature/Admin/RoleTest.php`).

### Menu

- **Edisi**: CRUD, tandai edisi aktif.
- **Madrasah**: CRUD + **import Excel** (kolom: NPSN, nama, jenjang, status, provinsi, kota, kecamatan, kelurahan, alamat; kota menerima juga format Kemenag "KOTA ADM. JAKARTA TIMUR").
- **Master → Cabor**: ubah nama, ikon, urutan; tambah cabor (hapus hanya jika belum dipakai).
- **Master → Mata Lomba**: CRUD katalog lomba + aktif/nonaktif + import Excel (kolom: cabor, nama, jenjang, kategori, kuota juara 1/2/3 — kosong = default 1/2/3).
- **Lomba**: per edisi, dipilih dari master mata lomba (form: pilih mata lomba; aksi "Tambah dari master" untuk banyak sekaligus; "Salin dari edisi lain"; import Excel yang setiap barisnya harus cocok dengan master — kuota kosong = kuota master).
- **Input hasil**: di halaman edit lomba, tiga bagian Juara 1 / Juara 2 / Juara 3. Tiap bagian berupa repeater (pilih madrasah via searchable select + nama peserta), jumlah baris maksimal = kuota peringkat itu. Juga import Excel dengan format **satu baris per juara** (kolom: cabor, lomba, jenjang, kategori, peringkat, NPSN madrasah, nama peserta).
- **Berita** (Publikasi): tulis (editor kaya, sampul dikompres WebP maks 1600px), status draf/terjadwal/terbit lewat `terbit_pada`, aksi Terbitkan sekarang / Jadikan draf / Jadikan unggulan / Sematkan di pita / Lihat di web. Unggulan dan pita masing-masing **satu per edisi** (otomatis melepas yang lama).
- **Pengguna** (superadmin): kelola akun dan role.
- **Galeri**: buat album (pilih cabor/lomba), upload foto banyak sekaligus; otomatis kompres (maks lebar 1920px) dan buat thumbnail (lebar 600px), format WebP.
- Validasi input hasil: madrasah wajib ada; jumlah juara per peringkat tidak melebihi kuota lomba.

---

## Desain (versi B: minimalis, podium, tipografi tegas)

Ikuti mockup versi B. Prinsip: terang, lega, sedikit warna, angka ala papan skor.

### Warna (definisikan sebagai token Tailwind)

| Token      | Hex       | Pakai untuk                                               |
| ---------- | --------- | --------------------------------------------------------- |
| `bg`       | `#F6F5F1` | Latar halaman                                             |
| `ink`      | `#0E0E10` | Teks utama, tombol utama, garis tebal                     |
| `muted`    | `#5E6066` | Teks sekunder                                             |
| `line`     | `#DEDCD5` | Garis pemisah baris                                       |
| `accent`   | `#1F5EFF` | Satu-satunya warna aksen (nomor cabor, link, label cabor) |
| `emas`     | `#E3AE2A` | Medali emas                                               |
| `perak`    | `#A8AFB9` | Medali perak                                              |
| `perunggu` | `#B8692E` | Medali perunggu                                           |
| `live`     | `#3DDC84` | Titik status "berlangsung" saja                           |

Podium: emas `#E3AE2A`, perak `#C9CED6`, perunggu `#C98A55`.

### Tipografi (Google Fonts)

- **Archivo** (`font-stretch: 110%`, weight 800, uppercase) untuk judul.
- **Inter** untuk teks biasa.
- **JetBrains Mono** (weight 700) untuk angka, peringkat, label kecil berhuruf kapital, tanggal.

Ukuran (desktop):

- Judul hero beranda: `clamp(36px, 5vw, 64px)`
- Judul halaman: `clamp(32px, 4vw, 48px)`
- Judul seksi: 26–30px
- Teks: 15–17px; label mono: 11–13px

### Komponen khas

- **Podium 1-2-3**: urutan tampil 2 – 1 – 3, tinggi berbeda, nomor besar di dalam balok. (Podium ini untuk peringkat **madrasah** di klasemen, bukan juara per lomba.)
- **Juara per lomba** (halaman Cabor & Lomba, tab Peraih Medali, ticker): tampilkan per peringkat dengan **medali bernomor** (komponen `x-medali`: rosette bergerigi warna emas/perak/perunggu, angka 1/2/3, pita kecil; flat tanpa gradien) dan label "JUARA N" di atasnya, menggantikan garis warna medali dari mockup (permintaan atasan); jika satu peringkat punya beberapa juara, nama-namanya ditumpuk di kolom yang sama. Medali yang sama dipakai di daftar juara profil madrasah, hasil pencarian, dan kartu ringkasan medali.
- **Batang medali**: batang bertumpuk emas/perak/perunggu, lebar sebanding total medali.
- **Ticker hasil terbaru**: pita hitam, bisa digeser horizontal.
- **Daftar bernomor** 01–07 untuk cabor.
- **Tabel klasemen**: baris dipisah garis tipis, header dipisah garis tebal 2px hitam, angka monospace.
- **Segmented control** untuk filter jenjang; `<select>` untuk filter cabor.

### Aturan UI

- Responsif: semua halaman harus nyaman di HP (lebar 360px). Tabel lebar dibungkus `overflow-x-auto`.
- Tombol/area klik minimal 44px.
- Kontras teks minimal 4.5:1.
- Ikon: SVG garis (stroke), bukan emoji.
- Hindari gradien, bayangan tebal, dan kartu berlapis-lapis.
- Logo: ikon JMC (bulan sabit) di navbar dan favicon; logo tulisan "JMC" di footer.

---

## Keputusan

1. Medali cabor **GTK** dihitung ke klasemen **madrasah tempat guru bertugas**.
2. Filter jenjang di klasemen berdasarkan **jenjang madrasah**.
3. **RA ikut klasemen umum** bersama MI/MTs/MA, dengan filter jenjang RA. Alasan: RA hanya punya 1 lomba (Adzan), jadi tidak perlu klasemen terpisah. Keputusan ini bisa ditinjau ulang; buat logikanya cukup fleksibel (mis. konstanta/config jenjang yang ikut klasemen umum) agar mudah diubah.

## Urutan pengerjaan

1. Migration, model, relasi, seeder 7 cabor + edisi contoh.
   Migration masih tahap development: perubahan skema langsung di file `create_*` lalu `php artisan migrate:fresh --seed` (`DatabaseSeeder` = cabor, superadmin, 1.815 madrasah, 92 mata lomba, edisi JMC 2026 + lombanya). Data demo (juara semua lomba dari pendaftaran terverifikasi + berita): `php artisan db:seed --class=DemoJmcSeeder`.
2. `KlasemenService` + test-nya.
3. Panel admin Filament (edisi, madrasah, lomba + input hasil, import Excel).
4. Layout publik (navbar, footer, token warna, font) + halaman Klasemen.
5. Beranda, Cabor & Lomba, Profil Madrasah.
6. Galeri (upload, kompres, thumbnail, lightbox).
7. Pilihan edisi/arsip.
8. Uji menyeluruh di lokal: semua test lulus, cek tampilan desktop & HP terhadap screenshot mockup, isi data contoh lewat seeder.
