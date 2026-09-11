# Testable Website Features

Fitur yang bisa di-test langsung lewat browser (web UI). Diurutkan per menu
sidebar. Setiap item ada URL, permission, feature flag, dan langkah test.

---

## A. Auth & Security (bisa sebelum login)

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| A1 | Login page | `/login` | - | - | Buka `/login`, pastikan field identifier (email/username) + password + checkbox remember |
| A2 | Login valid | POST `/login` | - | - | Login dengan super-admin, redirect ke `/dashboard` |
| A3 | Login gagal | POST `/login` | - | - | Login salah password, dapat error + throttle (5x → lockout) |
| A4 | Lockout | POST `/login` (5x salah) | - | - | Setelah 5 gagal, akun terkunci 15m, tombol login disable |
| A5 | Forgot password | `/forgot-password` | - | - | Masukkan email, dapat reset link |
| A6 | Reset password | `/reset-password/{token}` | - | - | Klik link di email, isi password baru, success redirect login |
| A7 | Email verification | `/email/verify/{id}/{hash}` | - | - | Setelah login belum verif, redirect ke halaman verify |
| A8 | Resend verification | POST `/email/verify/resend` | - | - | Klik resend, dapat notifikasi email |
| A9 | Logout | POST `/logout` | auth | - | Klik logout, redirect ke `/login` |

## B. Dashboard

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| B1 | Dashboard home | `/dashboard` | auth | - | Setelah login, lihat stat card + license badge |
| B2 | License status badge | di `/dashboard` | auth | - | Badge tununjukkan: "Active, 30 days left" / "Expired" / "Lifetime" |
| B3 | Health check | `/up` | - | - | Buka di browser/tab baru, dapat JSON `{"status":"ok"}` + DB/cache/queue check |

## C. Users Management

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| C1 | List users | `/users` | `user.view` | `users` | Buka `/users`, lihat tabel users + soft-deleted toggle |
| C2 | Create user | GET/POST `/users/create` | `user.create` | `users` | Klik tombol add, isi form, simpan |
| C3 | Edit user | GET/PUT `/users/{user}/edit` | `user.edit` | `users` | Klik icon edit, ubah, save |
| C4 | Soft-delete user | DELETE `/users/{user}` | `user.delete` | `users` | Klik tombol hapus, konfirmasi modal |
| C5 | Restore user | POST `/users/{user}/restore` | `user.restore` | `users` | Aktifkan "trash" filter, klik restore |
| C6 | Force-delete user | POST `/users/{user}/force-delete` | `user.force-delete` | `users` | Klik force-delete di row yang sudah di-trash |
| C7 | Lock user | POST `/users/{user}/lock` | `user.lock` | `users` | Klik lock, user tidak bisa login |
| C8 | Unlock user | POST `/users/{user}/unlock` | `user.lock` | `users` | Klik unlock, user bisa login lagi |
| C9 | Reset password user | POST `/users/{user}/reset-password` | `user.edit` | `users` | Klik reset password, dapat email reset link |
| C10 | Bulk delete | POST `/users/bulk` | `user.delete` | `users` | Pilih checkbox, pilih bulk action delete |
| C11 | Audit trail | tercantum di `/audit` | `audit.view` | `audit` | Lihat log `user_created`, `user_updated`, `user_deleted` |

## D. Roles & Permissions (RBAC)

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| D1 | List roles | `/roles` | `role.view` | `roles` | Buka `/roles`, lihat daftar roles |
| D2 | Create role | GET/POST `/roles/create` | `role.create` | `roles` | Klik add, beri nama + pilih permissions, simpan |
| D3 | Edit role | GET/PUT `/roles/{role}/edit` | `role.edit` | `roles` | Klik edit, tambah/hapus permission, save |
| D4 | Delete role | DELETE `/roles/{role}` | `role.delete` | `roles` | Klik hapus, konfirmasi |
| D5 | Restore role | POST `/roles/{role}/restore` | `role.restore` | `roles` | Di trash filter, klik restore |
| D6 | Force-delete role | POST `/roles/{role}/force-delete` | `role.force-delete` | `roles` | Klik force-delete |
| D7 | List permissions | `/permissions` | `permission.view` | `permissions` | Buka `/permissions`, lihat daftar |
| D8 | Bulk delete roles | POST `/roles/bulk` | `role.delete` | `roles` | Pilih checkbox, bulk action |
| D9 | Assign role ke user | di edit user | `user.edit` | `roles` | Buka edit user, lihat role assignment checkbox |

## E. Audit Trail

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| E1 | Audit log page | `/audit` | `audit.view` | `audit` | Buka `/audit`, lihat tabel aktivitas |
| E2 | Filter by user | GET `/audit?causer=1` | `audit.view` | `audit` | Pilih user di dropdown filter |
| E3 | Filter by action | GET `/audit?action=user_created` | `audit.view` | `audit` | Pilih action di dropdown |
| E4 | Filter by date | GET `/audit?from=2026-01-01&to=2026-09-01` | `audit.view` | `audit` | Isi date range filter |
| E5 | Detail modal | klik icon mata (eye) | `audit.view` | `audit` | Buka modal, lihat field diff Old/New + IP/User-Agent |
| E6 | CSV export | GET `/audit/export` | `audit.view` | `audit` | Klik export, download CSV dengan data yang terfilter |

## F. Notifications

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| F1 | Header bell | di semua halaman (auth) | auth | `audit` | Klik lonceng, lihat dropdown unread count + recent items |
| F2 | Mark as read | klik item notifikasi | auth | `audit` | Klik notifikasi, status jadi read |
| F3 | Notifications page | `/notifications` | `audit.view` | `audit` | Buka halaman, lihat semua notifikasi terpaginasi |
| F4 | Backfill | `php artisan notifications:backfill` | admin | `audit` | Jalankan command, cek notifikasi baru masuk |

## G. Sessions

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| G1 | List sessions | `/sessions` | `session.view` | `sessions` | Buka `/sessions`, lihat device yang sedang login |
| G2 | Logout other devices | POST `/sessions/logout-others` | `session.revoke` | `sessions` | Klik tombol, session lain ter-logout |

## H. Feature Flags

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| H1 | Features UI | `/features` | `feature.manage` | - | Buka `/features`, lihat toggle semua flags |
| H2 | Toggle flag | POST `/features/{slug}/toggle` | `feature.manage` | - | Klik toggle, flag langsung berubah |
| H3 | Kill-switch | toggle flag OFF | `feature.manage` | - | Toggle `users` OFF, `/users` langsung 404 |
| H4 | Sidebar hide | sidebar auto-hide | `feature.manage` | - | Toggle flag OFF, menu sidebar menghilang |

## I. Settings (Translations & Locale)

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| I1 | Locale switch (web) | POST `/locale` | auth | - | Pilih Indonesia, halaman reload ke Bahasa Indonesia |
| I2 | Translations UI | `/settings/translations` | `translation.view` | `translations` | Buka, lihat daftar language lines |
| I3 | Edit translation | GET/PUT `/settings/translations/{line}/edit` | `translation.edit` | `translations` | Klik edit, ubah teks, save — langsung berubah di UI |

## J. API Tokens

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| J1 | List API tokens | `/api-tokens` | `api-token.view` | `api-tokens` | Buka, lihat daftar token |
| J2 | Create token | POST `/api-tokens` | `api-token.create` | `api-tokens` | Klik add, beri nama, simpan — token ditunjukkan SEKALI |
| J3 | Revoke token | DELETE `/api-tokens/{id}` | `api-token.delete` | `api-tokens` | Klik revoke, token hilang dari list |

## K. Billing & Plans

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| K1 | Billing portal | `/billing` | auth | `billing` | Buka, lihat current plan + license + payment history |
| K2 | Checkout | POST `/billing/checkout` | auth | `billing` | Klik plan berbayar, proses dummy langsung selesai |
| K3 | Cancel billing | POST `/billing/cancel` | `billing.cancel` | `billing` | Klik cancel, license di-revoke |
| K4 | Invoice PDF | GET `/billing/invoice/{payment}` | auth (owner) | `billing` | Klik tombol invoice, download PDF |
| K5 | Admin billing analytics | `/admin/billing` | `billing.view` | `billing` | Buka, lihat KPI + tabel payments + licenses |

## L. Plans Management

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| L1 | Plans CRUD | `/plans` | `feature.manage` | `plans` | Buka, lihat list plans |
| L2 | Create plan | GET/POST `/plans/create` | `feature.manage` | `plans` | Klik add, isi slug/price/limits/features |
| L3 | Edit plan | GET/PUT `/plans/{plan}/edit` | `feature.manage` | `plans` | Klik edit, ubah limits/features |

## M. License Management (CLI)

| # | Fitur | Command | Permission | Catatan | Langkah Test |
|---|-------|---------|------------|---------|--------------|
| M1 | Issue license | `php artisan license:issue {slug} [--type=] [--days=]` | admin | signed key | Issue untuk plan "pro", dapat key `LIC-PRO-XXXX` |
| M2 | Activate license | `php artisan license:activate {key}` | admin | atomic | Activate key, cek status via `LicenseService::status()` |
| M3 | Webhook PG | POST `/billing/webhook` | - | CSRF-exempt | Kirim POST ke endpoint, cek payment ter-update |

## N. Logs & Monitoring

| # | Fitur | URL | Permission | Flag | Langkah Test |
|---|-------|-----|------------|------|--------------|
| N1 | Log viewer | `/logs` | `logs.view` | `logs` | Buka, filter level + date |
| N2 | Telescope | `/telescope` | `telescope.view` | `telescope` | Buka Telescope dashboard |
| N6 | Periscope | di Telescope | `telescope.view` | `periscope` | Gunakan filter advanced (date range, sort, type) |

---

## Cara Pakai

1. Pastikan server jalan: `php artisan serve`
2. Login sebagai super-admin (seed)
3. Buka tiap link di kolom URL di atas, verify permission + flag bekerja
4. Untuk fitur kill-switch (H3): toggle flag OFF, cek route 404 + sidebar hide
5. Untuk audit (E6): export CSV, buka di spreadsheet — pastikan isi sesuai filter
6. Untuk webhook (M3): pakai curl/Postman, POST ke `/billing/webhook` dengan payload dummy

## Seed Credentials

- super-admin: lihat di `database/seeders` (biasanya `admin@admin.com` / password kuat)
- Run seed: `php artisan migrate:fresh --seed`
