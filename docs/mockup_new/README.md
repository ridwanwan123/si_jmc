# Mockup desain B (referensi tampilan)

Desain B sudah disetujui. Pakai folder ini sebagai acuan saat membuat Blade view publik.

## Isi folder

| Bagian | Isi | Untuk apa |
|---|---|---|
| `*.html` (di folder ini) | HTML statis biasa, data contoh sudah terisi | **Acuan utama markup & style.** Bisa dibuka langsung di browser |
| `screenshots/*.png` | Gambar tiap halaman (desktop 1440px, dan `-hp` untuk lebar HP 390px) | Acuan visual: lihat gambar ini untuk memastikan hasil Blade mirip |
| `source/*.dc.html` | Sumber asli dari editor mockup, berisi logika interaksi | Lihat hanya untuk perilaku interaktif (filter, buka baris, lightbox) |
| `assets/` | `logo-ikon.png` (navbar, favicon), `logo-tulisan.png` (footer) | Salin ke `public/` |

## Halaman

| HTML statis | Screenshot | Route Laravel |
|---|---|---|
| `BerandaB.html` | `BerandaB.png`, `BerandaB-hp.png` (tampilan tahap **registrasi**: tombol Daftar sekarang + garis tahapan; podium hanya tampil di tahap klasemen) | `/` |
| `DaftarB.html` | `DaftarB-langkah1.png` s.d. `DaftarB-langkah5.png` (madrasah, cabor, mata lomba, data peserta, preview), `DaftarB-langkah4-perorangan.png` (contoh lomba perorangan), `DaftarB-selesai.png`, `DaftarB-hp.png`. HTML statis menampilkan langkah 4 | `/daftar` |
| `KlasemenB.html` | `KlasemenB.png`, `KlasemenB-hp.png`, `KlasemenB-peraih.png` (tab Peraih Medali) | `/klasemen` |
| `CaborB.html` | `CaborB.png`, `CaborB-hp.png`, `CaborB-olahraga.png` (cabor Olahraga, juara futsal dibuka) | `/cabor`, `/cabor/{slug}` |
| `MadrasahB.html` | `MadrasahB.png`, `MadrasahB-hp.png` | `/madrasah/{nsm}` |
| `GaleriB.html` | `GaleriB.png`, `GaleriB-hp.png`, `GaleriB-album.png` (isi album), `GaleriB-lightbox.png` (foto dibuka) | `/galeri`, `/galeri/{slug}` |

## Catatan penting

- HTML statis memakai **style inline**. Saat membuat Blade, terjemahkan ke kelas Tailwind dengan token warna dan font dari CLAUDE.md, jangan disalin mentah-mentah sebagai inline style.
- Semua nama madrasah, angka medali, dan daftar lomba di mockup adalah **data contoh**. Data asli diambil dari database lewat `KlasemenService` dan model.
- Teks dalam kurung siku (`[N]`, `[TGL]`, `[FOTO]`, `[Nama peserta]`) adalah placeholder.
- HTML statis hanya menampilkan **satu keadaan** (tab pertama, cabor pertama, satu baris klasemen terbuka). Keadaan lain terlihat di screenshot tambahan, dan logikanya ada di `source/`.
- **Juara ganda dan nama siswa** sudah tampil di `CaborB.html`: baris lomba bisa dibuka ("Lihat juara") untuk menampilkan semua juara per medali (default 1 emas, 2 perak, 3 perunggu), lengkap dengan nama siswa. Lomba beregu memakai label `TIM · n ANGGOTA`, lalu nama anggota satu per baris. Contoh tim besar (futsal 10 orang) ada di `screenshots/CaborB-olahraga.png`.
- `MadrasahB.html` menampilkan nama siswa di bawah nama lomba pada daftar juara.

### Cara membaca `source/*.dc.html` (jika perlu)

- Markup ada di dalam `<x-dc> … </x-dc>`; `{{nama}}` = nilai dinamis (→ Blade `{{ $nama }}`).
- `<sc-for list="{{items}}" as="item">` = perulangan (→ `@foreach`); `<sc-if value="{{kondisi}}">` = kondisi (→ `@if`).
- `onClick="{{fungsi}}"` = interaksi (→ Alpine.js `x-on:click`, `x-data`, `x-show`).
- Blok `<script type="text/x-dc">` di bawah berisi data contoh dan logika tampilan.
