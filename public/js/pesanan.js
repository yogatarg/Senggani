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

    // ── Modal konfirmasi (pengganti window.confirm) ─────
    // konfirmasi({ judul, isi, tombol, bahaya }) → Promise<boolean>
    var elKonfirmasi = $('#modalKonfirmasi');
    function konfirmasi(opsi) {
        if (!elKonfirmasi || !window.bootstrap) {
            return Promise.resolve(window.confirm([opsi.judul, opsi.isi].filter(Boolean).join('\n')));
        }
        return new Promise(function (selesai) {
            var modal = bootstrap.Modal.getOrCreateInstance(elKonfirmasi);
            var ya = $('[data-konfirmasi-ya]', elKonfirmasi);
            var isi = $('[data-konfirmasi-isi]', elKonfirmasi);
            var hasil = false;
            $('[data-konfirmasi-judul]', elKonfirmasi).textContent = opsi.judul || 'Lanjutkan?';
            isi.textContent = opsi.isi || '';
            isi.hidden = !opsi.isi;
            ya.textContent = opsi.tombol || 'Ya, lanjutkan';
            ya.className = 'sg-btn ' + (opsi.bahaya ? 'sg-btn-bahaya-isi' : 'sg-btn-utama');
            var saatYa = function () { hasil = true; modal.hide(); };
            // Tindakan berisiko: fokus awal di "Batal"
            var saatTampil = function () { $('[data-konfirmasi-batal]', elKonfirmasi).focus(); };
            var saatTutup = function () {
                ya.removeEventListener('click', saatYa);
                elKonfirmasi.removeEventListener('shown.bs.modal', saatTampil);
                elKonfirmasi.removeEventListener('hidden.bs.modal', saatTutup);
                selesai(hasil);
            };
            ya.addEventListener('click', saatYa);
            elKonfirmasi.addEventListener('shown.bs.modal', saatTampil);
            elKonfirmasi.addEventListener('hidden.bs.modal', saatTutup);
            modal.show();
        });
    }

    // ── Kirim form: konfirmasi + cegah klik ganda ───────
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        // Dibatalkan oleh pemeriksaan lain (mis. konfirmasi nomor SPP): jangan kunci tombol
        if (e.defaultPrevented) return;

        var pesan = form.getAttribute('data-konfirmasi');
        if (pesan && form.dataset.terkonfirmasi !== '1') {
            e.preventDefault();
            var pengirim = e.submitter || null;
            konfirmasi({
                judul: pesan,
                isi: form.getAttribute('data-konfirmasi-isi'),
                tombol: form.getAttribute('data-konfirmasi-tombol'),
                bahaya: form.hasAttribute('data-konfirmasi-bahaya')
            }).then(function (ok) {
                if (!ok) return;
                form.dataset.terkonfirmasi = '1';
                if (form.requestSubmit) form.requestSubmit(pengirim); else form.submit();
            });
            return;
        }

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

    // Salin teks SPP (Clipboard API, cadangan: textarea sementara + execCommand)
    function salinTeks(teks) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(teks).catch(function () { return salinCadangan(teks); });
        }
        return salinCadangan(teks);
    }
    function salinCadangan(teks) {
        return new Promise(function (berhasil, gagal) {
            var aktif = document.activeElement;
            var ta = document.createElement('textarea');
            ta.value = teks;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.top = '0';
            ta.style.left = '-9999px';
            ta.style.fontSize = '16px'; // cegah zoom iOS
            document.body.appendChild(ta);
            ta.select();
            ta.setSelectionRange(0, teks.length);
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
            ta.remove();
            if (aktif && aktif.focus) aktif.focus({ preventScroll: true });
            ok ? berhasil() : gagal(new Error('salin gagal'));
        });
    }
    function pilihTeks(el) {
        var r = document.createRange();
        r.selectNodeContents(el);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(r);
    }
    $$('[data-salin-teks]').forEach(function (b) {
        var sumber = $(b.getAttribute('data-salin-teks'));
        if (!sumber) return;
        var label = $('[data-salin-label]', b);
        var labelAsli = label ? label.textContent : '';
        var ikon = $('i', b);
        var timer = null;
        b.addEventListener('click', function () {
            salinTeks(sumber.textContent)
                .then(function () {
                    toast('Tersalin ✓ Tinggal tempel di WhatsApp.', { durasi: 3000 });
                    if (label) label.textContent = 'Tersalin ✓';
                    if (ikon) ikon.className = 'fa-solid fa-check';
                    clearTimeout(timer);
                    timer = setTimeout(function () {
                        if (label) label.textContent = labelAsli;
                        if (ikon) ikon.className = 'fa-regular fa-copy';
                    }, 2000);
                })
                .catch(function () {
                    // Tidak bisa menyalin otomatis: pilihkan teksnya supaya bisa disalin manual
                    pilihTeks(sumber);
                    sumber.scrollIntoView({ block: 'nearest' });
                    toast('Gagal menyalin otomatis. Teks sudah dipilih, salin manual (tahan lalu Salin).', { galat: true, durasi: 6000 });
                });
        });
    });

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
        // Hapus gambar: tutup lightbox → modal konfirmasi → kirim form, atau kembali ke gambar bila batal
        var tombolHapusLb = $('[data-lb-hapus-tombol]', lightbox);
        var keKonfirmasi = false;
        if (tombolHapusLb) {
            tombolHapusLb.addEventListener('click', function () { keKonfirmasi = true; modalLb.hide(); });
        }
        lightbox.addEventListener('hidden.bs.modal', function () {
            if (!keKonfirmasi) { if (pemicu) pemicu.focus(); return; }
            keKonfirmasi = false;
            konfirmasi({
                judul: 'Hapus gambar ini?',
                isi: tombolGaleri[indeks].getAttribute('data-lb-nama') + ' akan dihapus dari SPP. Tindakan ini tidak bisa dibatalkan.',
                tombol: 'Ya, hapus', bahaya: true
            }).then(function (ok) {
                var formHapus = $('[data-lb-form-hapus]', lightbox);
                if (ok && formHapus) {
                    formHapus.action = tombolGaleri[indeks].getAttribute('data-lb-hapus');
                    tombolHapusLb.disabled = true;
                    formHapus.submit();
                    return;
                }
                tampilkan(indeks);
                modalLb.show();
            });
        });
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
                $('span', peringatan).textContent = 'DP melebihi total SPP sebesar ' + rupiah(dp - total) + '.';
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
                    tolak.push([f.name, ' tidak ditambahkan: sudah ' + maksFile + ' gambar (batas per SPP).']);
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
            konfirmasi({
                judul: 'Hapus gambar ini?',
                isi: 'Gambar langsung dihapus dari SPP, tanpa perlu menekan Simpan. Tindakan ini tidak bisa dibatalkan.',
                tombol: 'Ya, hapus', bahaya: true
            }).then(function (ok) { if (ok) hapusFileLama(b); else b.focus(); });
        });
        function hapusFileLama(b) {
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
                    var fokusLain = $('[data-hapus-file-lama]', formOrder) || $('[data-unggah="mockup"]', formOrder);
                    if (fokusLain) fokusLain.focus();
                    toast(h.j.pesan || 'Gambar dihapus.');
                })
                .catch(function (err) { b.disabled = false; toast(err.message || 'Gambar gagal dihapus.', { galat: true }); });
        }

        // ── Nomor SPP: peringatan loncat (tambah) / ganti nomor (edit) ──
        // Server tetap penentu; cek ini hanya supaya admin tidak bolak-balik.
        var inputNomor = $('[data-nomor-spp]', formOrder);
        var kotakNomor = $('[data-konfirmasi-nomor]', formOrder);
        var cekNomor = function () {
            if (!inputNomor || !kotakNomor) return false;
            var teks = inputNomor.value.replace(/^\s*(spp\s*)?#?\s*/i, '').trim();
            if (!/^\d{1,9}$/.test(teks)) { return false; }
            var n = parseInt(teks, 10);
            var saran = parseInt(inputNomor.getAttribute('data-saran-nomor'), 10) || 0;
            var lama = inputNomor.getAttribute('data-nomor-lama');
            var pesan = '';
            if (lama !== '') {
                if (n !== parseInt(lama, 10)) {
                    pesan = 'Nomor SPP akan diganti dari #' + lama + ' menjadi #' + n + '. Produksi mungkin sudah menerima nomor lama lewat WhatsApp, jadi kabari mereka setelah menyimpan.';
                }
            } else if (saran > 1 && Math.abs(n - saran) > 10) {
                pesan = 'Nomor SPP #' + n + ' jauh dari nomor berikutnya (#' + saran + '). Periksa lagi; bila memang benar, centang konfirmasi lalu simpan.';
            }
            var centang = $('input[type="checkbox"]', kotakNomor);
            var teksPesan = $('[data-pesan-konfirmasi]', kotakNomor);
            if (pesan) {
                // Pertahankan pesan server bila nomornya sama dengan yang ditolak server
                if (kotakNomor.hidden || kotakNomor.getAttribute('data-nomor-pesan') !== String(n)) {
                    teksPesan.textContent = pesan;
                    kotakNomor.setAttribute('data-nomor-pesan', String(n));
                }
                kotakNomor.hidden = false;
                return !centang.checked;
            }
            kotakNomor.hidden = true;
            centang.checked = false;
            return false;
        };
        if (inputNomor && kotakNomor) {
            if (!kotakNomor.hidden) kotakNomor.setAttribute('data-nomor-pesan', String(keAngka(inputNomor.value)));
            inputNomor.addEventListener('change', cekNomor);
            form.addEventListener('submit', function (e) {
                if (!cekNomor()) return;
                e.preventDefault();
                var centang = $('input[type="checkbox"]', kotakNomor);
                kotakNomor.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                centang.focus({ preventScroll: true });
            });
        }

        // ── Pilih customer lama (combobox ARIA + autocomplete) ──
        var wadahPilih = $('[data-pilih-customer]', formOrder);
        if (wadahPilih) {
            var urlCari = wadahPilih.getAttribute('data-url');
            var idCustomer = $('[data-customer-id]', form);
            var chip = $('[data-chip-customer]', wadahPilih);
            var wadahCari = $('[data-cari-customer-wadah]', wadahPilih);
            var inputCustomer = $('[data-input-cari-customer]', wadahPilih);
            var daftarCustomer = $('[data-daftar-customer]', wadahPilih);
            var statusCustomer = $('[data-status-customer]', wadahPilih);
            var wadahPerbarui = $('[data-wadah-perbarui-customer]', formOrder);
            var isian = {};
            $$('[data-isi-customer]', formOrder).forEach(function (el) { isian[el.getAttribute('data-isi-customer')] = el; });
            var hasil = [];
            var aktif = -1;
            var timerCari = null;
            var pengendali = null;
            var cariTerakhir = '';

            var umumkan = function (t) { statusCustomer.textContent = ''; setTimeout(function () { statusCustomer.textContent = t; }, 50); };
            var waLokal = function (wa) { wa = String(wa || ''); return wa.indexOf('62') === 0 ? '0' + wa.slice(2) : wa; };

            var tutupDaftar = function () {
                daftarCustomer.hidden = true;
                inputCustomer.setAttribute('aria-expanded', 'false');
                inputCustomer.removeAttribute('aria-activedescendant');
                aktif = -1;
            };
            var tandaiAktif = function (i) {
                var opsi = $$('[role="option"]', daftarCustomer);
                if (!opsi.length) return;
                aktif = (i + opsi.length) % opsi.length;
                opsi.forEach(function (o, j) { o.setAttribute('aria-selected', j === aktif ? 'true' : 'false'); });
                inputCustomer.setAttribute('aria-activedescendant', opsi[aktif].id);
                opsi[aktif].scrollIntoView({ block: 'nearest' });
            };
            var barisInfo = function (teks, ikon) {
                daftarCustomer.innerHTML = '';
                var li = document.createElement('li');
                li.className = 'sg-ac-info';
                li.setAttribute('role', 'presentation');
                li.innerHTML = ikon ? '<i class="fa-solid ' + ikon + '" aria-hidden="true"></i> ' : '';
                li.appendChild(document.createTextNode(teks));
                daftarCustomer.appendChild(li);
                daftarCustomer.hidden = false;
                inputCustomer.setAttribute('aria-expanded', 'false');
            };
            var tampilkanHasil = function (data, q) {
                hasil = data;
                aktif = -1;
                if (!data.length) {
                    barisInfo('Tidak ada customer untuk “' + q + '”. Lanjutkan isi data di bawah untuk customer baru.', 'fa-user-plus');
                    umumkan('Tidak ada customer yang cocok.');
                    return;
                }
                daftarCustomer.innerHTML = '';
                data.forEach(function (c, i) {
                    var li = document.createElement('li');
                    li.id = 'opsi-customer-' + i;
                    li.setAttribute('role', 'option');
                    li.setAttribute('aria-selected', 'false');
                    li.className = 'sg-ac-opsi';
                    var nama = document.createElement('strong');
                    nama.textContent = c.nama;
                    var info = document.createElement('span');
                    info.className = 'sg-angka';
                    info.textContent = [c.kode, c.whatsapp_tampil, c.kota, c.jumlah_spp + ' SPP'].filter(Boolean).join(' · ');
                    li.appendChild(nama);
                    li.appendChild(info);
                    li.addEventListener('mousedown', function (e) { e.preventDefault(); });
                    li.addEventListener('click', function () { pilih(i); });
                    daftarCustomer.appendChild(li);
                });
                daftarCustomer.hidden = false;
                inputCustomer.setAttribute('aria-expanded', 'true');
                umumkan(data.length + ' customer ditemukan. Gunakan panah atas/bawah lalu Enter untuk memilih.');
            };
            var cari = function () {
                var q = inputCustomer.value.trim();
                if (q.length < 2) { tutupDaftar(); daftarCustomer.innerHTML = ''; return; }
                if (q === cariTerakhir && !daftarCustomer.hidden) return;
                cariTerakhir = q;
                if (pengendali && pengendali.abort) pengendali.abort();
                pengendali = typeof AbortController !== 'undefined' ? new AbortController() : null;
                barisInfo('Mencari…', 'fa-spinner fa-spin');
                fetch(urlCari + (urlCari.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: pengendali ? pengendali.signal : undefined
                })
                    .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
                    .then(function (j) { if (inputCustomer.value.trim() === q) tampilkanHasil(j.data || [], q); })
                    .catch(function (err) {
                        if (err && err.name === 'AbortError') return;
                        cariTerakhir = '';
                        barisInfo('Gagal memuat daftar customer. Ketik lagi untuk mencoba.', 'fa-triangle-exclamation');
                    });
            };

            var aturChip = function (c) {
                $('[data-chip-judul]', chip).textContent = 'Customer lama ' + c.kode + ' · ' + c.jumlah_spp + ' SPP';
                $('[data-chip-sub]', chip).textContent = c.nama + ' · ' + c.whatsapp_tampil;
            };
            var pilih = function (i) {
                var c = hasil[i];
                if (!c) return;
                idCustomer.value = c.id;
                isian.nama.value = c.nama || '';
                isian.wa.value = waLokal(c.nomor_whatsapp);
                if (c.alamat) isian.alamat.value = c.alamat;
                if (c.kota) isian.kota.value = c.kota;
                aturChip(c);
                tutupDaftar();
                inputCustomer.value = '';
                cariTerakhir = '';
                chip.hidden = false;
                wadahCari.hidden = true;
                wadahPerbarui.hidden = false;
                $('input', wadahPerbarui).checked = false;
                tandaiKotor();
                umumkan('Customer ' + c.kode + ' dipilih. Nama, WhatsApp, alamat, dan kota sudah diisi; alamat dan kota masih bisa diubah untuk SPP ini.');
                $('[data-ganti-customer]', chip).focus();
            };
            var lepas = function (fokusKe) {
                idCustomer.value = '';
                chip.hidden = true;
                wadahCari.hidden = false;
                wadahPerbarui.hidden = true;
                $('input', wadahPerbarui).checked = false;
                tandaiKotor();
                fokusKe.focus();
            };

            inputCustomer.addEventListener('input', function () {
                clearTimeout(timerCari);
                timerCari = setTimeout(cari, 250);
            });
            inputCustomer.addEventListener('keydown', function (e) {
                var terbuka = inputCustomer.getAttribute('aria-expanded') === 'true';
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!terbuka) { cariTerakhir = ''; cari(); return; }
                    tandaiAktif(aktif + 1);
                } else if (e.key === 'ArrowUp') {
                    if (!terbuka) return;
                    e.preventDefault();
                    tandaiAktif(aktif - 1);
                } else if (e.key === 'Enter') {
                    // Enter di kolom cari tidak boleh mengirim form SPP
                    e.preventDefault();
                    if (terbuka && aktif >= 0) pilih(aktif);
                    else if (terbuka && hasil.length === 1) pilih(0);
                } else if (e.key === 'Escape') {
                    if (!daftarCustomer.hidden) { e.preventDefault(); tutupDaftar(); }
                    else if (inputCustomer.value) { e.preventDefault(); inputCustomer.value = ''; }
                } else if (e.key === 'Tab') {
                    tutupDaftar();
                }
            });
            inputCustomer.addEventListener('blur', function () { setTimeout(tutupDaftar, 120); });
            inputCustomer.addEventListener('focus', function () {
                if (hasil.length && inputCustomer.value.trim().length >= 2) { cariTerakhir = ''; cari(); }
            });

            $('[data-ganti-customer]', chip).addEventListener('click', function () {
                lepas(inputCustomer);
                umumkan('Pilih customer lain. Isian di bawah tetap.');
            });
            $('[data-lepas-customer]', chip).addEventListener('click', function () {
                lepas(isian.nama);
                umumkan('Customer dilepas. Saat disimpan, customer baru dibuat dari isian, kecuali nomor WA sudah terdaftar.');
                toast('Customer dilepas. SPP akan memakai customer baru dari isian (atau customer dengan nomor WA yang sama).', { durasi: 6000 });
            });
        }

        nomoriItem();
        hitungTotal();
        perbaruiHitung();
        if (linkDrive.value) cekLink();

        // Sebelum kirim: hapus format ribuan tidak perlu (server menerima "2.500"), cukup tandai bersih
        form.addEventListener('submit', function (e) { if (!e.defaultPrevented) form.dataset.bersih = '1'; });
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
                if (!kotor || formJaga.dataset.bersih === '1' || e.metaKey || e.ctrlKey || e.shiftKey) return;
                e.preventDefault();
                konfirmasi({
                    judul: 'Keluar tanpa menyimpan?',
                    isi: 'Perubahan yang belum disimpan akan hilang.',
                    tombol: 'Ya, keluar', bahaya: true
                }).then(function (ok) { if (ok) { formJaga.dataset.bersih = '1'; window.location.href = a.href; } else { a.focus(); } });
            });
        });
    }
})();
