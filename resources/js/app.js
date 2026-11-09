import Alpine from 'alpinejs';

/**
 * Lightbox galeri: buka/tutup, sebelumnya/berikutnya (tombol, panah keyboard, geser di HP),
 * kunci scroll halaman, dan kembalikan fokus ke foto yang dibuka.
 */
Alpine.data('lightbox', (fotos = []) => ({
    fotos,
    aktif: null,
    pemicu: null,
    sentuhX: null,

    buka(i, el = null) {
        this.aktif = i;
        this.pemicu = el;
        document.body.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.tutup?.focus());
    },

    tutup() {
        this.aktif = null;
        document.body.style.overflow = '';
        this.pemicu?.focus();
    },

    maju() {
        if (this.aktif === null) return;
        this.aktif = (this.aktif + 1) % this.fotos.length;
    },

    mundur() {
        if (this.aktif === null) return;
        this.aktif = (this.aktif - 1 + this.fotos.length) % this.fotos.length;
    },

    tombol(e) {
        if (this.aktif === null) return;
        if (e.key === 'Escape') this.tutup();
        else if (e.key === 'ArrowRight') this.maju();
        else if (e.key === 'ArrowLeft') this.mundur();
    },

    sentuhMulai(e) {
        this.sentuhX = e.changedTouches[0].clientX;
    },

    sentuhSelesai(e) {
        if (this.sentuhX === null) return;
        const dx = e.changedTouches[0].clientX - this.sentuhX;
        this.sentuhX = null;
        if (Math.abs(dx) < 50) return;
        dx < 0 ? this.maju() : this.mundur();
    },
}));

/**
 * Formulir pendaftaran 5 langkah (/daftar): madrasah → cabor → mata lomba → data peserta → preview.
 * "Kirim" mengirim FormData ke POST /daftar; kesalahan validasi server ditampilkan di langkah terkait.
 */
Alpine.data('formDaftar', (data) => ({
    data,
    langkah: 1, // 1–5 formulir, 6 = selesai
    cari: '',
    pilihan: { madrasah: null, cabor: null, lomba: null },
    peserta: [{ nama: '', nisn: '' }],
    guru: { nama: '', wa: '', surat: null },
    setuju: false,
    cobaLanjut: false,
    galat: '',
    mengirim: false,
    berkas: null, // File surat tugas
    terkirim: null, // respons server: nomor, lomba, cabor, madrasah, bukti

    batasCari: 8,

    madrasahTerpilih() {
        return this.data.madrasah.find((m) => m.id === this.pilihan.madrasah) ?? null;
    },
    caborTerpilih() {
        return this.data.cabor.find((c) => c.id === this.pilihan.cabor) ?? null;
    },
    lombaTerpilih() {
        return this.data.lomba.find((l) => l.id === this.pilihan.lomba) ?? null;
    },

    cocokCari() {
        const kata = this.cari.trim().toLowerCase();
        if (!kata) return this.data.madrasah;
        return this.data.madrasah.filter((m) => m.nama.toLowerCase().includes(kata) || m.nsm.startsWith(kata));
    },
    hasilCari() {
        const hasil = this.cocokCari().slice(0, this.batasCari);
        // Madrasah terpilih tetap terlihat walau di luar hasil pencarian.
        const terpilih = this.madrasahTerpilih();
        if (terpilih && !hasil.includes(terpilih)) hasil.unshift(terpilih);
        return hasil;
    },
    sisaCari() {
        return Math.max(0, this.cocokCari().length - this.batasCari);
    },

    lombaJenjang() {
        const jenjang = this.madrasahTerpilih()?.jenjang;
        return this.data.lomba.filter((l) => l.jenjang === jenjang);
    },
    jumlahLomba(caborId) {
        return this.lombaJenjang().filter((l) => l.cabor_id === caborId && !this.sudahTerdaftar(l.id)).length;
    },
    sudahTerdaftar(lombaId) {
        return this.data.terdaftar.includes(this.pilihan.madrasah + '-' + lombaId);
    },
    daftarLomba() {
        return this.lombaJenjang().filter((l) => l.cabor_id === this.pilihan.cabor);
    },
    gtk() {
        return this.lombaTerpilih()?.gtk ?? false;
    },
    labelNomor() {
        return this.gtk() ? 'NUPTK' : 'NISN';
    },
    nomorValid(n) {
        return (this.gtk() ? /^\d{16}$/ : /^\d{10}$/).test(n);
    },
    jumlahAnggota() {
        return this.lombaTerpilih()?.anggota ?? 1;
    },

    pilihMadrasah(id) {
        if (this.pilihan.madrasah === id) return;
        this.pilihan = { madrasah: id, cabor: null, lomba: null };
        this.galat = '';
    },
    pilihCabor(id) {
        if (this.pilihan.cabor === id) return;
        this.pilihan.cabor = id;
        this.pilihan.lomba = null;
        this.galat = '';
    },
    pilihLomba(id) {
        this.pilihan.lomba = id;
        this.galat = '';
        // Sesuaikan jumlah baris peserta, pertahankan isian yang sudah ada.
        const n = this.jumlahAnggota();
        this.peserta = Array.from({ length: n }, (_, i) => this.peserta[i] ?? { nama: '', nisn: '' });
    },
    pilihSurat(e) {
        const file = e.target.files[0] ?? null;
        this.guru.surat = null;
        this.berkas = null;
        if (!file) return;
        if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
            this.galat = 'Surat tugas harus berupa file PDF.';
            e.target.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            this.galat = 'Ukuran surat tugas maksimal 2 MB.';
            e.target.value = '';
            return;
        }
        this.galat = '';
        this.guru.surat = file.name;
        this.berkas = file;
    },

    waValid() {
        return /^(\+62|62|0)8\d{7,12}$/.test(this.guru.wa.replace(/[\s-]/g, ''));
    },

    /** Pesan kesalahan untuk langkah saat ini, atau '' jika sudah lengkap. */
    periksa() {
        switch (this.langkah) {
            case 1:
                return this.pilihan.madrasah ? '' : 'Pilih madrasah terlebih dahulu.';
            case 2:
                return this.pilihan.cabor ? '' : 'Pilih cabang lomba terlebih dahulu.';
            case 3:
                return this.pilihan.lomba ? '' : 'Pilih mata lomba terlebih dahulu.';
            case 4:
                if (this.peserta.some((p) => !p.nama)) return 'Isi nama lengkap semua peserta.';
                if (this.peserta.some((p) => !this.nomorValid(p.nisn))) return this.gtk() ? 'NUPTK harus 16 digit angka.' : 'NISN harus 10 digit angka.';
                if (!this.guru.nama) return 'Isi nama guru pendamping.';
                if (!this.waValid()) return 'Nomor WhatsApp tidak valid (contoh 081234567890).';
                if (!this.guru.surat) return 'Unggah surat tugas dari madrasah (PDF).';
                return '';
            case 5:
                return this.setuju ? '' : 'Centang pernyataan terlebih dahulu.';
        }
        return '';
    },

    lanjut() {
        this.cobaLanjut = true;
        this.galat = this.periksa();
        if (this.galat) return;
        this.cobaLanjut = false;
        if (this.langkah === 5) {
            this.kirim();
            return;
        }
        this.langkah++;
    },
    kembali() {
        this.galat = '';
        this.cobaLanjut = false;
        if (this.langkah > 1) this.langkah--;
    },

    async kirim() {
        if (this.mengirim) return;
        this.mengirim = true;
        this.galat = '';

        const fd = new FormData();
        fd.append('madrasah_id', this.pilihan.madrasah);
        fd.append('lomba_id', this.pilihan.lomba);
        this.peserta.forEach((p, i) => {
            fd.append(`peserta[${i}][nama]`, p.nama);
            fd.append(`peserta[${i}][nisn]`, p.nisn);
        });
        fd.append('guru_nama', this.guru.nama);
        fd.append('guru_wa', this.guru.wa);
        if (this.berkas) fd.append('surat_tugas', this.berkas);
        fd.append('setuju', this.setuju ? '1' : '0');

        try {
            const res = await fetch(this.data.url, {
                method: 'POST',
                body: fd,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
            });
            const json = await res.json().catch(() => ({}));

            if (res.status === 201) {
                this.terkirim = json;
                this.data.terdaftar.push(this.pilihan.madrasah + '-' + this.pilihan.lomba);
                this.langkah = 6;
                return;
            }
            if (res.status === 422 && json.errors) {
                const kunci = Object.keys(json.errors)[0];
                this.galat = json.errors[kunci][0];
                // Arahkan ke langkah yang perlu diperbaiki.
                if (kunci === 'madrasah_id') this.langkah = 1;
                else if (kunci === 'lomba_id') this.langkah = 3;
                else if (kunci !== 'setuju') this.langkah = 4;
                return;
            }
            this.galat = res.status === 429
                ? 'Terlalu banyak percobaan. Tunggu satu menit lalu coba lagi.'
                : 'Pendaftaran gagal dikirim. Muat ulang halaman lalu coba lagi.';
        } catch {
            this.galat = 'Koneksi terputus. Periksa internet Anda lalu coba lagi.';
        } finally {
            this.mengirim = false;
        }
    },

    lombaLain() {
        // Madrasah dan guru pendamping dipertahankan; lomba, peserta, dan pernyataan dikosongkan.
        this.pilihan.cabor = null;
        this.pilihan.lomba = null;
        this.peserta = [{ nama: '', nisn: '' }];
        this.setuju = false;
        this.terkirim = null;
        this.galat = '';
        this.langkah = 2;
    },

    chips() {
        const l = this.lombaTerpilih();
        return [
            { label: 'MADRASAH', isi: this.madrasahTerpilih()?.nama, ke: 1, ada: this.langkah > 1 },
            { label: 'CABOR', isi: this.caborTerpilih()?.nama, ke: 2, ada: this.langkah > 2 },
            { label: 'LOMBA', isi: l ? `${l.nama} ${l.kategori}` : '', ke: 3, ada: this.langkah > 3 },
        ].filter((c) => c.ada && c.isi);
    },

    ringkasan() {
        const m = this.madrasahTerpilih();
        const l = this.lombaTerpilih();
        const tim = this.jumlahAnggota() > 1;
        return [
            { label: 'MADRASAH', isi: [`${m?.nama} (${m?.jenjang})`], ke: 1 },
            { label: 'CABOR', isi: [this.caborTerpilih()?.nama], ke: 2 },
            { label: 'MATA LOMBA', isi: [`${l?.nama} ${l?.kategori} · ${tim ? 'Tim ' + this.jumlahAnggota() + ' orang' : 'Perorangan'}`], ke: 3 },
            { label: tim ? 'ANGGOTA TIM' : 'PESERTA', isi: this.peserta.map((p) => `${p.nama} · ${this.labelNomor()} ${p.nisn}`), ke: 4 },
            { label: 'PENDAMPING', isi: [`${this.guru.nama} · ${this.guru.wa}`, `Surat tugas: ${this.guru.surat}`], ke: 4 },
        ];
    },
}));

window.Alpine = Alpine;
Alpine.start();
