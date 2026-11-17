/**
 * Panel admin: foto HEIC/HEIF (iPhone) diubah ke JPG di browser sebelum diunggah,
 * karena server (GD) tidak bisa membaca HEIC. Berlaku untuk semua upload foto di admin:
 * galeri, sampul berita (FilePond), dan gambar di isi berita (RichEditor).
 *
 * Caranya: tangkap event `change` (pilih file) dan `drop` (seret file) lebih dulu
 * (fase capture), ubah file HEIC ke JPG, lalu teruskan file hasilnya ke komponen aslinya.
 * Pustaka konversi (heic-to) baru dimuat saat benar-benar ada file HEIC.
 */

const KUALITAS_JPG = 0.9;
const TANDA = Symbol('heic-sudah-dikonversi');

const adalahHeic = (file) =>
    /^image\/hei[cf](-sequence)?$/i.test(file.type) || /\.(heic|heif)$/i.test(file.name);

async function keJpg(file) {
    const { heicTo } = await import('heic-to');
    const blob = await heicTo({ blob: file, type: 'image/jpeg', quality: KUALITAS_JPG });
    const nama = file.name.replace(/\.(heic|heif)$/i, '') + '.jpg';

    return new File([blob], nama, { type: 'image/jpeg', lastModified: file.lastModified });
}

/** Ubah semua file HEIC di daftar; file lain dibiarkan. Gagal konversi → file asli (server akan menolak dengan pesan jelas). */
async function konversi(files) {
    return Promise.all(
        Array.from(files).map(async (file) => {
            if (!adalahHeic(file)) return file;
            try {
                return await keJpg(file);
            } catch (e) {
                console.error('Gagal mengubah HEIC ke JPG:', file.name, e);
                return file;
            }
        }),
    );
}

function keFileList(files) {
    const dt = new DataTransfer();
    files.forEach((f) => dt.items.add(f));
    return dt;
}

function tandaiSibuk(el, sibuk) {
    const wadah = el.closest('.fi-fo-field, .fi-fo-file-upload, .fi-fo-rich-editor') ?? el;
    wadah.style.opacity = sibuk ? '0.6' : '';
    wadah.style.pointerEvents = sibuk ? 'none' : '';
    wadah.toggleAttribute('aria-busy', sibuk);
}

// Pilih file lewat tombol / dialog file.
document.addEventListener(
    'change',
    async (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'file' || event[TANDA]) return;
        if (!input.files || !Array.from(input.files).some(adalahHeic)) return;

        event.stopImmediatePropagation();
        tandaiSibuk(input, true);
        try {
            input.files = keFileList(await konversi(input.files)).files;
        } finally {
            tandaiSibuk(input, false);
        }

        const ulang = new Event('change', { bubbles: true });
        ulang[TANDA] = true;
        input.dispatchEvent(ulang);
    },
    true,
);

// Seret-lepas file ke area upload atau ke editor isi berita.
document.addEventListener(
    'drop',
    async (event) => {
        if (event[TANDA]) return;
        const files = event.dataTransfer?.files;
        if (!files || !Array.from(files).some(adalahHeic)) return;

        const target = event.target instanceof Element ? event.target : null;
        const pondRoot = target?.closest('.filepond--root');

        event.preventDefault();
        event.stopImmediatePropagation();
        if (target) tandaiSibuk(target, true);

        let hasil;
        try {
            hasil = await konversi(files);
        } finally {
            if (target) tandaiSibuk(target, false);
        }

        // Area upload FilePond (galeri, sampul): tambahkan langsung lewat API FilePond.
        const pond = pondRoot && window.FilePond?.find(pondRoot);
        if (pond) {
            pond.addFiles(hasil);
            return;
        }

        // Editor isi berita: kirim ulang event drop dengan file JPG.
        if (target) {
            const ulang = new DragEvent('drop', {
                bubbles: true,
                cancelable: true,
                clientX: event.clientX,
                clientY: event.clientY,
                dataTransfer: keFileList(hasil),
            });
            ulang[TANDA] = true;
            target.dispatchEvent(ulang);
        }
    },
    true,
);
