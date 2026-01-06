
# AEframework PHP

> **AEframework** adalah PHP framework ringan dan modular buatan [Aefw](https://aefw.net) dan didukung oleh  [Indonetsoft](https://indonetsoft.com)
> Dirancang untuk kecepatan, kemudahan, dan fleksibilitas tanpa beban ketergantungan besar.

---

## 🚀 Fitur Utama

- Zero-dependency, tidak membutuhkan Composer atau framework pihak ketiga
- Struktur rapi dan mudah dipahami (tidak perlu belajar ulang "framework")
- Modular dan multi-instance: bisa menampung banyak aplikasi dalam satu kernel
- Routing fleksibel berbasis struktur URL & folder
- Struktur konfigurasi terpusat dan sistem boot per-aplikasi
- File `initialise.php` sebagai core bootstrap ringan
- Langsung bisa deploy di VPS, maupun LAMP stack biasa

---

## 🛠️ Instalasi Cepat

Aplikasi yang sudah dibuat berlisensi oleh Indonetsoft, silakan hubungi kami melalui [Indonetsoft](https://indonetsoft.com/)

---

## ⚙️ Konfigurasi Aplikasi

Semua konfigurasi berada dalam:
- `app/config/` → konfigurasi global

Framework ini mendukung multi-aplikasi dalam satu instance.

---

## 🖥️ Server Requirements

AEframework membutuhkan PHP **versi 8.1 atau lebih tinggi**, dengan ekstensi berikut:

### Wajib Diinstal:
- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)
- [libcurl](http://php.net/manual/en/curl.requirements.php) _(jika menggunakan library HTTP\CURLRequest)_

### Harus Aktif (default PHP):
- `json` (aktif secara default — **jangan dimatikan**)
- `xml` (aktif secara default — **jangan dimatikan**)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) _(untuk dukungan MySQL native)_

## ⛁ Database Requirements

AEframework membutuhkan MariaDB **versi 10.5 atau lebih tinggi**, karena sudah mendukung CI/CD maupun SQL idempotent

## 📦 Upload & Produksi

- Folder `upload/` dan `backup/` harus writable (`chmod 755` atau `775`)
- `.htaccess` sudah mengatur keamanan dasar dan rewrite
- `.own.rewrite.htaccess` mengatur keamanan dasar dan rewrite custom yg akan merger dengan file htaccess utama
- `.own.module.htaccess` mengatur keamanan dasar dan module custom yg akan merger dengan file htaccess utama
- Tidak perlu `vendor/`, tidak perlu `artisan`, cukup `initialise.php`

---

## 🤝 Kontribusi

Framework ini bersifat private dan dikembangkan oleh tim internal Indonetsoft, namun terbuka untuk kolaborasi terbatas.  
Silakan hubungi kami melalui [Indonetsoft](https://indonetsoft.com/)

---

## 🔐 Lisensi

AEframework

---

💙 Dibuat oleh Aldo Expert dan didukung oleh Indonetsoft — _Make IT Easy, Make IT Simple_
