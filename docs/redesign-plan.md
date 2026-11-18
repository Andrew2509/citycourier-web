# City Courier Admin Panel — Complete UI/UX Redesign Plan

Target: professional SaaS redesign seluruh admin panel, fungsi 100% dipertahankan.

## Keputusan desain

- Palet baru: primary `#0647FF`, cyan `#73C8D2`, bg cream `#F5F1DC`, accent orange `#FF910F`,
  surface putih, text `#172033`, muted `#64748B`, border `#E5E7EB`,
  success `#16A34A`, warning `#F59E0B`, danger `#DC2626`.
- Font: Inter (satu keluarga). Skala sesuai spesifikasi.
- Icon: Lucide (outline, stroke ~2), runtime swap via `data-lucide`,
  mapping dari 140 nama Material Symbols lama, auto-convert untuk konten dinamis (MutationObserver).
- Radius 8–12px, shadow halus, tanpa gradient/glassmorphism.

## Fase

1. **Audit** — SELESAI. Laravel Blade, semua view admin extend `layouts/admin`, design system di `resources/css/admin.css`.
2. **Design system** — token warna baru di `@theme`, komponen (btn/card/badge/table/modal/nav) restyle, `.cc-icon` menggantikan `.material-symbols-outlined`.
3. **Global layout** — sidebar putih dengan active blue + collapsible + tooltip, topbar dengan breadcrumb, global search (command palette dari link sidebar), notification center, help, profile.
4. **Icon swap** — `npm i lucide`, mapping material→lucide, replace class di semua blade (termasuk string JS), hapus font Material Symbols.
5. **Page redesign** — per halaman: hapus warna hardcoded lama, seragamkan ke design system (dashboard, couriers, orders, order-detail, shipments x3, drop-points x3, ci-work x5, finance x13, settings x9, users/roles/permissions, research, app-download).
6. **Responsive** — sidebar drawer mobile sudah ada; audit tabel overflow, card stacking, form grid.
7. **Testing** — `npm run build`, review blade render (php artisan view:cache/clear), QA visual & fungsional per halaman.
8. **Cleanup** — hapus CSS/font lama yang tak terpakai.

## Aturan pelindung

- Tidak mengubah route, controller, business logic, validasi, form action, nama field.
- Tidak menghapus kolom tabel / informasi.
- Perubahan hanya di: blade markup (class/style), CSS, JS UI layer, layout.

## Progress

- [x] Phase 1 Audit
- [x] Phase 2 Design system
- [x] Phase 3 Global layout
- [x] Phase 4 Icon swap
- [x] Phase 5 Page redesign
- [x] Phase 6 Responsive
- [x] Phase 7 Testing
- [x] Phase 8 Cleanup

## Catatan testing & cleanup (2026-11-18)

- `npm run build` OK. Bundle admin-ui 932 KB → 165 KB dengan tree-shake lucide
  (named import hanya 130 ikon terpakai, objek `ICONS` di `resources/js/cc-icons.js`).
- Crawl semua route GET admin (49 halaman, login sebagai admin): semua 200,
  tanpa `material-symbols`, tanpa error render.
- Perbaikan bug pre-existing yang ditemukan saat testing:
    - `admin/ci-work/dana-transactions.blade.php`: route `finance.dana-transactions`
      tidak ada → `admin.ci-work.finance.dana-transactions`; `$providerName` di modal
      guide saat daftar transaksi kosong → fallback `?? 'DANA'`.
- Migrasi lokal dijalankan (16 migrasi finance/wallet belum jalan di DB lokal) supaya
  halaman finance bisa dites.
- `/admin/drop-points/create` & `edit` 302 memang disengaja controller (redirect ke
  index dengan info flash).
- Font lama (Plus Jakarta/JetBrains) hanya tersisa di halaman publik
  (tracking, research) — di luar scope redesign admin.
