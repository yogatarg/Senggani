/*
 * Modul Pesanan Senggani — interaksi halaman (JS vanilla, tanpa jQuery).
 * Setiap bagian hanya aktif bila elemennya ada di halaman.
 */
(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
    var csrf = ($('meta[name="csrf-token"]') || {}).content || '';

    var formatAngka = function (n) { return new Intl.NumberFormat('id-ID').format(n); };
    var rupiah = function (n) { return 'Rp ' + formatAngka(n); };
    var keAngka = function (teks) {
        var d = String(teks || '').replace(/\D/g, '');
        return d === '' ? 0 : parseInt(d, 10);
    };

    // ── Toast ──────────────────────────────────────────
    var wadahToast = $('#wadah-toast');

    function pasangToast(el, durasi) {
        var timer = null;
        var berhenti = false;
        var tutup = function () {
            el.classList.add('sg-keluar');
            setTimeout(function () { el.remove(); }, 160);
        };
        var mulai = function () {
            clearTimeout(timer);
            if (!berhenti) timer = setTimeout(tutup, durasi);
        };
        el.addEventListener('mouseenter', function () { berhenti = true; clearTimeout(timer); });
        el.addEventListener('mouseleave', function () { berhenti = false; mulai(); });
        el.addEventListener('focusin', function () { berhenti = true; clearTimeout(timer); });
        el.addEventListener('focusout', function () { berhenti = false; mulai(); });
        var tombolTutup = $('[data-tutup-toast]', el);
        if (tombolTutup) tombolTutup.addEventListener('click', tutup);
        mulai();
        return tutup;
    }

    function toast(teks, opsi) {
        if (!wadahToast) return;
        opsi = opsi || {};
        var el = document.createElement('div');
        el.className = 'sg-toast sg-masuk';
        el.setAttribute('role', opsi.galat ? 'alert' : 'status');
        var p = document.createElement('p');
        p.textContent = teks;
        el.appendChild(p);
        var tutup;
        if (opsi.aksi) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'sg-toast-aksi';
            b.textContent = opsi.aksi;
            b.addEventListener('click', function () { opsi.saatAksi(); tutup(); });
            el.appendChild(b);
        }
        var x = document.createElement('button');
        x.type = 'button';
        x.setAttribute('data-tutup-toast', '');
        x.setAttribute('aria-label', 'Tutup pemberitahuan');
        x.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
        el.appendChild(x);
        wadahToast.appendChild(el);
        tutup = pasangToast(el, opsi.durasi || 5000);
    }

    $$('[data-toast-otomatis]').forEach(function (el) { pasangToast(el, 5000); });

    // ── Kirim form: konfirmasi + cegah klik ganda ───────
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        var pesan = form.getAttribute('data-konfirmasi');
        if (pesan && !window.confirm(pesan)) { e.preventDefault(); return; }

        if (!form.hasAttribute('data-kirim')) return;
        if (form.dataset.terkirim === '1') { e.preventDefault(); return; }
        form.dataset.terkirim = '1';
        form.dataset.bersih = '1';

        var tombol = $$('button[type="submit"]', form);
        if (form.id) tombol = tombol.concat($$('button[type="submit"][form="' + form.id + '"]'));
        // Nonaktifkan setelah event selesai agar data submitter tetap terkirim
        setTimeout(function () {
            tombol.forEach(function (b) {
                b.disabled = true;
                b.setAttribute('aria-busy', 'true');
                var teks = b.getAttribute('data-teks-kirim');
                if (teks) b.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> ' + teks;
            });
        }, 0);
    });

    // Bila halaman dipulihkan dari cache tombol kembali, aktifkan lagi tombolnya
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        $$('form[data-terkirim]').forEach(function (f) { delete f.dataset.terkirim; });
        $$('button[aria-busy="true"]').forEach(function (b) { b.disabled = false; b.removeAttribute('aria-busy'); });
    });

    // ── Ringkasan error: arahkan ke daftar & field ─────
    var ringkasanGalat = $('#ringkasan-galat');
    if (ringkasanGalat) {
        ringkasanGalat.focus({ preventScroll: true });
        ringkasanGalat.scrollIntoView({ block: 'start' });
        $$('a[href^="#f-"]', ringkasanGalat).forEach(function (a) {
            a.addEventListener('click', function (e) {
                var target = document.getElementById(a.getAttribute('href').slice(1));
                if (!target) return;
                e.preventDefault();
                var field = target.matches('input,select,textarea') ? target : $('input:not([type=hidden]),select,textarea', target);
                target.scrollIntoView({ block: 'center' });
                if (field) field.focus({ preventScroll: true });
            });
        });
    }

    // ── Tampilkan/sembunyikan password ─────────────────
    $$('[data-tampil-sandi]').forEach(function (b) {
        var input = b.parentElement.querySelector('input');
        b.addEventListener('click', function () {
            var tampil = input.type === 'password';
            input.type = tampil ? 'text' : 'password';
            b.setAttribute('aria-pressed', tampil ? 'true' : 'false');
            b.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
            b.innerHTML = '<i class="fa-solid ' + (tampil ? 'fa-eye-slash' : 'fa-eye') + '" aria-hidden="true"></i>';
        });
    });

    // Fokus otomatis hanya di desktop (di HP keyboard tidak langsung muncul)
    var fokusDesktop = $('[data-fokus-desktop]');
    if (fokusDesktop && window.matchMedia('(min-width: 992px)').matches) fokusDesktop.focus();

    // ════════════════════════════════════════════════
    // Daftar pesanan
    // ════════════════════════════════════════════════
    var daftar = $('[data-daftar]');

    // Tab aktif digeser ke tengah (HP)
    var stripTab = $('[data-tab-gulir]');
    if (stripTab) {
        var tabAktif = $('a[aria-current="page"]', stripTab);
        if (tabAktif && stripTab.scrollWidth > stripTab.clientWidth) {
            stripTab.scrollLeft = tabAktif.parentElement.offsetLeft - (stripTab.clientWidth - tabAktif.offsetWidth) / 2;
        }
        $$('[data-tab-link]', stripTab).forEach(function (a) {
            a.addEventListener('click', function (e) {
                if (e.metaKey || e.ctrlKey || e.shiftKey) return;
                $$('[data-tab-link]', stripTab).forEach(function (x) { x.removeAttribute('aria-current'); });
                a.setAttribute('aria-current', 'page');
                if (daftar) daftar.setAttribute('aria-busy', 'true');
            });
        });
    }

    var formCari = $('[data-form-cari]');
    if (formCari) {
        var inputCari = $('input[type="search"]', formCari);
        var hapusCari = $('[data-hapus-cari]', formCari);
        var aturHapus = function () { hapusCari.hidden = inputCari.value === ''; };
        inputCari.addEventListener('input', aturHapus);
        hapusCari.addEventListener('click', function () {
            var tadinya = new URLSearchParams(location.search).get('q');
            inputCari.value = '';
            aturHapus();
            if (tadinya) { formCari.requestSubmit ? formCari.requestSubmit() : formCari.submit(); } else { inputCari.focus(); }
        });
        $$('[data-kirim-otomatis]', formCari).forEach(function (s) {
            s.addEventListener('change', function () {
                if (daftar) daftar.setAttribute('aria-busy', 'true');
                formCari.submit();
            });
        });
        formCari.addEventListener('submit', function () { if (daftar) daftar.setAttribute('aria-busy', 'true'); });
    }

    // Muat lebih banyak (tambahkan kartu halaman berikutnya)
    var tombolMuat = $('[data-muat-lagi]');
    if (tombolMuat && daftar && window.DOMParser) {
        tombolMuat.addEventListener('click', function (e) {
            e.preventDefault();
            var url = tombolMuat.href;
            tombolMuat.setAttribute('aria-disabled', 'true');
            tombolMuat.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Memuat…';
            fetch(url, { headers: { 'Accept': 'text/html' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var kartuBaru = $$('[data-daftar] > .sg-order', doc);
                    var pertama = null;
                    kartuBaru.forEach(function (k) {
                        var n = document.importNode(k, true);
                        daftar.appendChild(n);
                        if (!pertama) pertama = n;
                    });
                    var lanjut = $('[data-muat-lagi]', doc);
                    var akhir = $('[data-muat-akhir]', doc);
                    var akhirKini = $('[data-muat-akhir]');
                    if (akhir && akhirKini) akhirKini.textContent = akhir.textContent;
                    if (lanjut) {
                        tombolMuat.href = lanjut.href;
                        tombolMuat.removeAttribute('aria-disabled');
                        tombolMuat.textContent = 'Muat lebih banyak';
                    } else {
                        tombolMuat.remove();
                    }
                    if (pertama) { var link = $('a', pertama); if (link) link.focus({ preventScroll: true }); }
                })
                .catch(function () { window.location.href = url; });
        });
    }

    // Pembaruan otomatis jumlah tab tiap 60 detik
    var segar = $('[data-segar-otomatis]');
    if (segar) {
        var penanda = segar.getAttribute('data-penanda');
        var gagal = 0;
        var terakhirCek = Date.now();
        var teksSegar = $('[data-teks-segar]');
        var teksAsli = teksSegar ? teksSegar.innerHTML : '';
        var cek = function () {
            terakhirCek = Date.now();
            fetch(segar.getAttribute('data-url'), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
                .then(function (data) {
                    gagal = 0;
                    if (teksSegar) { teksSegar.innerHTML = teksAsli; teksSegar.classList.remove('sg-tak-tersambung'); }
                    Object.keys(data.jumlah || {}).forEach(function (k) {
                        var el = $('[data-jumlah-tab="' + k + '"]');
                        if (!el) return;
                        el.firstChild.nodeValue = data.jumlah[k];
                        el.classList.toggle('sg-nol', data.jumlah[k] === 0);
                    });
                    if (data.penanda && data.penanda !== penanda) $('#ada-perubahan').hidden = false;
                })
                .catch(function () {
                    gagal++;
                    if (gagal >= 3 && teksSegar) {
                        teksSegar.textContent = 'Tidak tersambung · coba lagi';
                        teksSegar.classList.add('sg-tak-tersambung');
                    }
                });
        };
        setInterval(function () { if (document.visibilityState === 'visible') cek(); }, 60000);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible' && Date.now() - terakhirCek > 60000) cek();
        });
    }

    // ════════════════════════════════════════════════
    // Detail order
    // ════════════════════════════════════════════════
    var formCatatan = $('[data-form-catatan]');
    if (formCatatan) {
        var area = $('textarea', formCatatan);
        var tombolSimpan = $('[data-butuh-isi]', formCatatan);
        var hitung = $('[data-hitung]', formCatatan);
        var perbarui = function () {
            tombolSimpan.disabled = area.value.trim() === '';
            var n = area.value.length;
            hitung.hidden = n <= 400;
            hitung.textContent = n + ' / 500 karakter';
        };
        area.addEventListener('input', perbarui);
        $$('[data-saran]', formCatatan).forEach(function (chip) {
            chip.addEventListener('click', function () {
                var saran = chip.getAttribute('data-saran');
                var isi = area.value.replace(/\s+$/, '');
                if (isi === '') {
                    area.value = saran;
                } else {
                    area.value = isi + (/[.!?]$/.test(isi) ? ' ' : '. ') + saran;
                }
                area.focus();
                area.setSelectionRange(area.value.length, area.value.length);
                perbarui();
            });
        });
        perbarui();
    }

    var tombolRiwayat = $('[data-tampil-riwayat]');
    if (tombolRiwayat) {
        tombolRiwayat.addEventListener('click', function () {
            var lain = $$('[data-riwayat-lain]');
            lain.forEach(function (li) { li.hidden = false; });
            tombolRiwayat.remove();
            if (lain[0]) { lain[0].setAttribute('tabindex', '-1'); lain[0].focus(); }
        });
    }

    // Lightbox galeri mockup
    var lightbox = $('#lightbox');
    if (lightbox && window.bootstrap) {
        var tombolGaleri = $$('[data-galeri] [data-lb-src]');
        var modalLb = bootstrap.Modal.getOrCreateInstance(lightbox);
        var gambarLb = $('[data-lb-gambar]', lightbox);
        var indeks = 0;
        var pemicu = null;
        var tampilkan = function (i) {
            indeks = (i + tombolGaleri.length) % tombolGaleri.length;
            var b = tombolGaleri[indeks];
            gambarLb.src = b.getAttribute('data-lb-src');
            gambarLb.alt = b.getAttribute('data-lb-nama');
            $('[data-lb-judul]', lightbox).textContent = b.getAttribute('data-lb-nama');
            $('[data-lb-hitung]', lightbox).textContent = (indeks + 1) + ' / ' + tombolGaleri.length;
            $('[data-lb-buka]', lightbox).href = b.getAttribute('data-lb-src');
            $('[data-lb-unduh-link]', lightbox).href = b.getAttribute('data-lb-unduh');
            var formHapus = $('[data-lb-form-hapus]', lightbox);
            if (formHapus) formHapus.action = b.getAttribute('data-lb-hapus') || '';
            $$('[data-lb-geser]', lightbox).forEach(function (n) { n.hidden = tombolGaleri.length < 2; });
        };
        tombolGaleri.forEach(function (b, i) {
            b.addEventListener('click', function () { pemicu = b; tampilkan(i); modalLb.show(); });
        });
        $$('[data-lb-geser]', lightbox).forEach(function (n) {
            n.addEventListener('click', function () { tampilkan(indeks + parseInt(n.getAttribute('data-lb-geser'), 10)); });
        });
        lightbox.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') tampilkan(indeks + 1);
            if (e.key === 'ArrowLeft') tampilkan(indeks - 1);
        });
        var awalX = null;
        var tubuh = $('[data-lb-tubuh]', lightbox);
        tubuh.addEventListener('touchstart', function (e) { awalX = e.touches[0].clientX; }, { passive: true });
        tubuh.addEventListener('touchend', function (e) {
            if (awalX === null) return;
            var dx = e.changedTouches[0].clientX - awalX;
            if (Math.abs(dx) > 50) tampilkan(indeks + (dx < 0 ? 1 : -1));
            awalX = null;
        });
        lightbox.addEventListener('hidden.bs.modal', function () { if (pemicu) pemicu.focus(); });
    }

    // ════════════════════════════════════════════════
    // Form order (tambah & edit)
    // ════════════════════════════════════════════════
    var formOrder = $('[data-form-order]');
    if (formOrder) {
        var form = formOrder.closest('form');
        var wadahItem = $('[data-daftar-item]', formOrder);
        var templateItem = $('#template-item');

        // Input rupiah: pemisah ribuan saat blur
        var formatRupiahInput = function (input) {
            if (input.value.trim() === '') return;
            input.value = formatAngka(keAngka(input.value));
        };
        formOrder.addEventListener('focusout', function (e) {
            if (e.target.matches('[data-rupiah]')) formatRupiahInput(e.target);
        });

        // Opsi "Lainnya…" pada select master
        var aturLainnya = function (select, fokus) {
            var wadah = select.parentElement.querySelector('[data-isian-lainnya]');
            if (!wadah) return;
            var pilih = select.value === '__lainnya__';
            wadah.hidden = !pilih;
            var input = $('input', wadah);
            input.required = pilih;
            if (pilih && fokus) input.focus();
        };
        formOrder.addEventListener('change', function (e) {
            if (e.target.matches('[data-pilih-lainnya]')) aturLainnya(e.target, true);
        });
        $$('[data-pilih-lainnya]', formOrder).forEach(function (s) { aturLainnya(s, false); });

        // Chip "+ Lainnya" finishing
        formOrder.addEventListener('click', function (e) {
            var b = e.target.closest('[data-toggle-fin-lain]');
            if (!b) return;
            var wadah = b.closest('fieldset').querySelector('[data-fin-lain]');
            var buka = wadah.hidden;
            wadah.hidden = !buka;
            b.setAttribute('aria-pressed', buka ? 'true' : 'false');
            var input = $('input', wadah);
            if (buka) input.focus(); else input.value = '';
            hitungTotal();
        });

        // Penomoran ulang item (indeks nama field selalu 0..n-1 agar error server cocok)
        var nomoriItem = function () {
            var semua = $$('[data-item]', wadahItem);
            semua.forEach(function (item, j) {
                $$('[name^="item["]', item).forEach(function (el) {
                    el.name = el.name.replace(/^item\[[^\]]+\]/, 'item[' + j + ']');
                });
                $$('*', item).concat([item]).forEach(function (el) {
                    ['id', 'for', 'aria-describedby', 'aria-controls', 'aria-labelledby'].forEach(function (attr) {
                        var v = el.getAttribute(attr);
                        if (!v) return;
                        var baru = v.replace(/\b(f|g)-item-[^-\s]+-/g, '$1-item-' + j + '-').replace(/\bjudul-item-[^\s]+/g, 'judul-item-' + j);
                        if (baru !== v) el.setAttribute(attr, baru);
                    });
                });
                $('[data-item-label]', item).textContent = 'Item ' + (j + 1);
                $('[data-hapus-item]', item).hidden = semua.length < 2;
            });
            $$('[data-jumlah-item]').forEach(function (el) { el.textContent = semua.length; });
        };

        $('[data-tambah-item]', formOrder).addEventListener('click', function () {
            var html = templateItem.innerHTML.replace(/__I__/g, String($$('[data-item]', wadahItem).length));
            wadahItem.insertAdjacentHTML('beforeend', html);
            nomoriItem();
            var baru = wadahItem.lastElementChild;
            baru.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            var pertama = $('select, input', baru);
            if (pertama) pertama.focus({ preventScroll: true });
            hitungTotal();
            tandaiKotor();
        });

        wadahItem.addEventListener('click', function (e) {
            var b = e.target.closest('[data-hapus-item]');
            if (!b) return;
            var semua = $$('[data-item]', wadahItem);
            if (semua.length < 2) return;
            var item = b.closest('[data-item]');
            var posisi = semua.indexOf(item);
            var label = $('[data-item-label]', item).textContent;
            item.remove();
            nomoriItem();
            hitungTotal();
            tandaiKotor();
            var sisaItem = $$('[data-item]', wadahItem);
            var fokusKe = sisaItem[Math.min(posisi, sisaItem.length - 1)];
            if (fokusKe) $('[data-hapus-item]', fokusKe).hidden ? $('select', fokusKe).focus() : $('[data-hapus-item]', fokusKe).focus();
            toast(label + ' dihapus.', {
                aksi: 'Urungkan', durasi: 6000,
                saatAksi: function () {
                    var kini = $$('[data-item]', wadahItem);
                    if (posisi >= kini.length) wadahItem.appendChild(item); else wadahItem.insertBefore(item, kini[posisi]);
                    nomoriItem();
                    hitungTotal();
                    $('select', item).focus();
                }
            });
        });

        // Hitung subtotal, total, sisa, status bayar
        var hitungTotal = function () {
            var total = 0;
            $$('[data-item]', wadahItem).forEach(function (item) {
                var sub = keAngka($('[data-jumlah]', item).value) * keAngka($('[data-harga]', item).value);
                total += sub;
                $('[data-subtotal]', item).textContent = rupiah(sub);
            });
            var dpInput = $('[data-dp]', formOrder);
            var dp = keAngka(dpInput.value);
            var lunas = $('[data-tanggal-lunas]', formOrder).value !== '';
            var sisa = lunas ? 0 : Math.max(total - dp, 0);
            $$('[data-total]').forEach(function (el) { el.textContent = rupiah(total); });
            $$('[data-sisa]').forEach(function (el) { el.textContent = rupiah(sisa); });
            $$('[data-dp-tampil]').forEach(function (el) { el.textContent = rupiah(dp); });

            var status = lunas ? 'lunas' : (dp > 0 ? 'dp' : 'belum_dp');
            var info = { belum_dp: ['Belum DP', 'fa-wallet'], dp: ['DP Masuk', 'fa-coins'], lunas: ['Lunas', 'fa-circle-check'] }[status];
            $$('[data-badge-bayar]').forEach(function (b) {
                b.className = 'sg-bayar sg-by-' + status;
                $('i', b).className = 'fa-solid ' + info[1];
                $('[data-teks-bayar]', b).textContent = info[0];
            });

            var peringatan = $('[data-peringatan-dp]', formOrder);
            if (dp > total) {
                peringatan.hidden = false;
                $('span', peringatan).textContent = 'DP melebihi total order sebesar ' + rupiah(dp - total) + '.';
            } else {
                peringatan.hidden = true;
            }
        };
        formOrder.addEventListener('input', function (e) {
            if (e.target.matches('[data-jumlah], [data-harga], [data-dp], [data-tanggal-lunas]')) hitungTotal();
        });
        formOrder.addEventListener('change', function (e) {
            if (e.target.matches('[data-tanggal-lunas]')) hitungTotal();
        });

        // Deadline tidak boleh sebelum tanggal order
        var tglOrder = $('[data-tanggal-order]', formOrder);
        var deadline = $('[data-deadline]', formOrder);
        tglOrder.addEventListener('change', function () { deadline.min = tglOrder.value; });

        // Peringatan lunak link Drive
        var linkDrive = $('[data-link-drive]', formOrder);
        var bantuLink = $('[data-bantu-link]', formOrder);
        var cekLink = function () {
            var v = linkDrive.value.trim();
            var salah = v !== '' && !/^https:\/\/(drive|docs)\.google\.com\//i.test(v);
            bantuLink.classList.toggle('sg-tak-tersambung', salah);
            bantuLink.textContent = salah
                ? 'Link ini bukan link Google Drive. Gunakan link drive.google.com atau docs.google.com.'
                : 'Harus link drive.google.com atau docs.google.com.';
        };
        linkDrive.addEventListener('blur', cekLink);
        linkDrive.addEventListener('input', function () { if (bantuLink.classList.contains('sg-tak-tersambung')) cekLink(); });

        // ── Unggah gambar: cek format, ukuran, jumlah sebelum kirim ──
        var maksFile = parseInt(formOrder.getAttribute('data-maks-file'), 10) || 10;
        var maksByte = (parseFloat(formOrder.getAttribute('data-maks-mb')) || 5) * 1024 * 1024;
        var tipeBoleh = ['image/jpeg', 'image/png', 'image/webp'];
        var pilihan = { mockup: [], referensi: [] };
        var bisaAturFile = typeof DataTransfer !== 'undefined';
        var teksHitung = $('[data-hitung-file]', formOrder);

        var jumlahLama = function () { return $$('[data-file-lama]', formOrder).length; };
        var jumlahSemua = function () { return jumlahLama() + pilihan.mockup.length + pilihan.referensi.length; };
        var perbaruiHitung = function () {
            var n = jumlahSemua();
            teksHitung.textContent = 'Gambar: ' + n + ' dari ' + maksFile + (n >= maksFile ? ' (sudah penuh)' : ' · masih bisa ' + (maksFile - n) + ' lagi') + '. JPG, PNG, atau WebP, maks 5 MB per gambar.';
        };
        var ukuranMb = function (b) { return (b / 1024 / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' MB'; };

        var sinkron = function (jenis) {
            var input = $('[data-unggah="' + jenis + '"]', formOrder);
            if (bisaAturFile) {
                var dt = new DataTransfer();
                pilihan[jenis].forEach(function (f) { dt.items.add(f); });
                input.files = dt.files;
            }
            var daftarP = $('[data-pratinjau="' + jenis + '"]', formOrder);
            $$('img', daftarP).forEach(function (img) { URL.revokeObjectURL(img.src); });
            daftarP.innerHTML = '';
            pilihan[jenis].forEach(function (f, i) {
                var li = document.createElement('li');
                var img = document.createElement('img');
                img.src = URL.createObjectURL(f);
                img.alt = f.name;
                li.appendChild(img);
                var b = document.createElement('button');
                b.type = 'button';
                b.setAttribute('aria-label', 'Hapus gambar ' + f.name);
                b.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
                b.addEventListener('click', function () {
                    pilihan[jenis].splice(i, 1);
                    sinkron(jenis);
                    tandaiKotor();
                    var lain = $('button', daftarP) || input;
                    lain.focus();
                });
                li.appendChild(b);
                daftarP.appendChild(li);
            });
            perbaruiHitung();
        };

        var terimaFile = function (jenis, files) {
            var tolak = [];
            Array.prototype.forEach.call(files, function (f) {
                var ext = (f.name.split('.').pop() || '').toLowerCase();
                var tipeOk = tipeBoleh.indexOf(f.type) !== -1 || ['jpg', 'jpeg', 'png', 'webp'].indexOf(ext) !== -1;
                if (!tipeOk) {
                    tolak.push([f.name, ' tidak bisa diunggah: format harus JPG, PNG, atau WebP.']);
                } else if (f.size > maksByte) {
                    tolak.push([f.name, ' lebih dari 5 MB (' + ukuranMb(f.size) + '). Kecilkan dulu ukurannya.']);
                } else if (jumlahSemua() >= maksFile) {
                    tolak.push([f.name, ' tidak ditambahkan: sudah ' + maksFile + ' gambar (batas per order).']);
                } else {
                    pilihan[jenis].push(f);
                }
            });
            var daftarTolak = $('[data-tolak="' + jenis + '"]', formOrder);
            daftarTolak.innerHTML = '';
            tolak.forEach(function (t) {
                var li = document.createElement('li');
                var b = document.createElement('strong');
                b.textContent = t[0];
                li.appendChild(b);
                li.appendChild(document.createTextNode(t[1]));
                daftarTolak.appendChild(li);
            });
            if (!bisaAturFile && tolak.length) {
                // Browser lama: tidak bisa membuang sebagian file, kosongkan pilihan
                $('[data-unggah="' + jenis + '"]', formOrder).value = '';
                pilihan[jenis] = [];
            }
            sinkron(jenis);
        };

        $$('[data-unggah]', formOrder).forEach(function (input) {
            var jenis = input.getAttribute('data-unggah');
            input.addEventListener('change', function () {
                var baru = Array.prototype.slice.call(input.files);
                if (!bisaAturFile) pilihan[jenis] = [];
                terimaFile(jenis, baru);
            });
            var zona = input.closest('[data-dropzone]');
            ['dragenter', 'dragover'].forEach(function (ev) {
                zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.add('sg-seret'); });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.remove('sg-seret'); });
            });
            zona.addEventListener('drop', function (e) {
                if (e.dataTransfer && e.dataTransfer.files.length && bisaAturFile) {
                    terimaFile(jenis, e.dataTransfer.files);
                    tandaiKotor();
                }
            });
        });

        // Hapus gambar tersimpan (edit) lewat JSON
        formOrder.addEventListener('click', function (e) {
            var b = e.target.closest('[data-hapus-file-lama]');
            if (!b) return;
            if (!window.confirm('Hapus gambar ini dari order? Tindakan ini tidak bisa dibatalkan.')) return;
            var data = new FormData();
            data.append('_method', 'DELETE');
            b.disabled = true;
            fetch(b.getAttribute('data-hapus-file-lama'), {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (h) {
                    if (!h.ok) throw new Error(h.j.message || h.j.pesan || 'Gambar gagal dihapus.');
                    var li = b.closest('[data-file-lama]');
                    var ul = li.parentElement;
                    li.remove();
                    if (!ul.children.length) ul.closest('.sg-field').remove();
                    perbaruiHitung();
                    toast(h.j.pesan || 'Gambar dihapus.');
                })
                .catch(function (err) { b.disabled = false; toast(err.message || 'Gambar gagal dihapus.', { galat: true }); });
        });

        nomoriItem();
        hitungTotal();
        perbaruiHitung();
        if (linkDrive.value) cekLink();

        // Sebelum kirim: hapus format ribuan tidak perlu (server menerima "2.500"), cukup tandai bersih
        form.addEventListener('submit', function () { form.dataset.bersih = '1'; });
    }

    // ── Jaga perubahan yang belum disimpan ─────────────
    var formJaga = $('form[data-jaga-perubahan]');
    var kotor = false;
    function tandaiKotor() { kotor = true; }
    if (formJaga) {
        formJaga.addEventListener('input', tandaiKotor);
        formJaga.addEventListener('change', tandaiKotor);
        $$('[data-batal-form], .sg-topbar-kembali').forEach(function (a) {
            a.addEventListener('click', function (e) {
                if (kotor && formJaga.dataset.bersih !== '1' && !window.confirm('Perubahan belum disimpan. Tetap keluar?')) e.preventDefault();
            });
        });
    }
})();
