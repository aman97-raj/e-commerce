<div align="center">

# 🛒 Hey Buddy
### *Let's Be Together — India's Favourite Online Store*

![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![SQLite](https://img.shields.io/badge/Database-SQLite-003B57?logo=sqlite&logoColor=white)
![HTML](https://img.shields.io/badge/Frontend-HTML%2FCSS%2FJS-E34F26?logo=html5&logoColor=white)

> A complete, production-ready e-commerce platform built with a pure HTML/CSS/JS frontend and a PHP/SQLite backend — no frameworks, no dependencies, just clean code.

**Developed by [Aman Raj](https://aman97.netlify.app)**

---

</div>

## 📌 Overview

**Hey Buddy** is a full-featured e-commerce web application inspired by platforms like Flipkart. It includes everything a real online store needs — product browsing, cart, wishlist, checkout with multiple payment options, order tracking, reviews, a blog section, and a complete admin panel — all running on a zero-config SQLite database.

---

## 📁 Project Structure

```
e-commerce/
│
├── index.html                  ← Homepage (hero, categories, deals, featured)
├── login.html                  ← User Login & Register
├── blogs.html                  ← Blog listing page
│
├── auth.php                    ← Auth API (login, register, logout, profile)
├── products.php                ← Products API (list, detail, search, categories)
├── cart.php                    ← Cart API (add, remove, update, coupon)
├── wishlist.php                ← Wishlist API (toggle, get, check)
├── orders.php                  ← Orders API (place, list, detail)
├── addresses.php               ← Addresses API (add, list, delete, set-default)
├── reviews.php                 ← Reviews API (submit, get)
├── blogs.php                   ← Blogs API (list, detail)
│
├── pages/
│   ├── shop.html               ← Product listing with filters & sort
│   ├── product.html            ← Product detail page
│   ├── cart.html               ← Shopping cart
│   ├── checkout.html           ← Multi-step checkout (address + payment)
│   ├── wishlist.html           ← Wishlist page
│   ├── profile.html            ← User profile, orders, addresses
│   ├── orders.html             ← Order history
│   └── order-detail.html       ← Order tracking with step indicator
│
├── admin/
│   ├── login.html / login.php  ← Admin login
│   ├── dashboard.html / .php   ← Stats, revenue, charts, recent orders
│   ├── products.html / .php    ← Products CRUD
│   ├── add-product.html / .php ← Add new product with live preview
│   ├── orders.html / .php      ← Order management & status update
│   ├── users.html / .php       ← Registered users list
│   ├── coupons.html / .php     ← Coupon management
│   ├── blogs.html / .php       ← Blog post management
│   ├── notifications-ui.js     ← Admin notification system
│   └── logout.php              ← Admin logout
│
├── includes/
│   ├── db.php                  ← SQLite DB connection, schema, seeding
│   └── helpers.php             ← Utility & helper functions
│
├── assets/
│   ├── css/style.css           ← Main stylesheet (64KB, fully custom)
│   ├── js/app.js               ← Main JavaScript (30KB+)
│   └── images/logo/            ← Brand logos & mascot
│
└── database/
    ├── allthings.sql           ← SQL dump for reference/import
    └── allthings.db            ← SQLite database (auto-created)
```

---

## ✨ Features

### 🧑‍💻 Customer Side
| Feature | Description |
|---|---|
| 🏠 Homepage | Hero slider, deal banners, category grid, featured products |
| 🔍 Search | Live search suggestions with full-text results |
| 🏪 Shop | Browse by category, filter by price & rating, sort options |
| 📦 Product Detail | Image gallery, highlights, EMI info, reviews, related products |
| 🛒 Cart | Quantity control, remove items, save to wishlist, coupon codes |
| 💳 Checkout | Multi-step — address selection → payment → confirmation |
| 📱 Payment Options | UPI (GPay, PhonePe, Paytm), Credit/Debit Card, COD |
| ❤️ Wishlist | Save & remove items, move to cart |
| 👤 Profile | Edit profile, manage addresses, view order history |
| 📦 Order Tracking | Visual step-by-step order status tracker |
| ⭐ Reviews | Rate and review purchased products |
| 📝 Blog | Read blog posts with full article view |
| 📱 Responsive | Full mobile support with animated hamburger menu |

### 🛠️ Admin Panel
| Feature | Description |
|---|---|
| 📊 Dashboard | Revenue stats, order counts, user counts, order status chart |
| 📦 Products | View all, search/filter, edit inline, delete |
| ➕ Add Product | Add with live preview, image upload, featured toggle |
| 🛒 Orders | View all, update status (placed → processing → shipped → delivered) |
| 👥 Users | View all registered users with order counts |
| 🏷️ Coupons | Create, activate/deactivate, delete discount coupons |
| 📝 Blogs | Create and manage blog posts from admin |
| 🔔 Notifications | Admin notification system |

### ⚙️ Technical Highlights
- 🔒 **CSRF Protection** — All state-changing API requests are protected
- 🗄️ **SQLite + PDO** — Zero-config database, WAL mode, foreign keys enabled
- 🎯 **Toast Notifications** — Success / error / info user feedback
- ⚡ **Skeleton Screens** — Loading states across all pages
- 🔄 **Real-time Cart Badge** — Updates without page reload
- 📱 **CSS Hamburger Menu** — Smooth animated mobile navigation
- 🧹 **Strict PHP** — `declare(strict_types=1)` across all backend files
- 🗂️ **Modular Structure** — Clean separation of frontend and backend files

---

## 🚀 Setup Instructions

### ✅ Requirements
- PHP 8.0+ with SQLite3 extension enabled
- Any web server — Apache, Nginx, or PHP's built-in server

### ⚡ Quick Start

**Option 1 — PHP Built-in Server (fastest):**
```bash
cd e-commerce
php -S localhost:8000
```
Visit: [http://localhost:8000](http://localhost:8000)

**Option 2 — XAMPP / WAMP:**
1. Copy the project folder to `htdocs/` (XAMPP) or `www/` (WAMP)
2. Start Apache
3. Visit: `http://localhost/e-commerce`

**Option 3 — Linux/Mac with Apache:**
```bash
sudo cp -r e-commerce/ /var/www/html/
sudo chmod -R 755 /var/www/html/e-commerce/
sudo chown -R www-data:www-data /var/www/html/e-commerce/
```

> 💡 The SQLite database auto-creates itself at `database/allthings.db` on the very first request — no setup needed.

---

## 🔐 Default Credentials

### Admin Panel
| Field | Value |
|---|---|
| URL | `/admin/login.html` |
| Email | `admin@admin.com` |
| Password | `password` |

### Demo User
Register at `/login.html` — accounts are created instantly with no email verification required in demo mode.

---

## 🏷️ Demo Coupon Codes

| Code | Type | Discount | Min Order |
|---|---|---|---|
| `WELCOME10` | Percentage | 10% off | ₹500 |
| `FLAT200` | Flat | ₹200 off | ₹1,000 |
| `SAVE15` | Percentage | 15% off | ₹2,000 |
| `BUDDY10` | Percentage | 10% off | Any |

---

## 🎨 Customization

### Add Products
Use **Admin Panel → Add Product**, or seed directly in `includes/db.php` inside the `seed_demo_data()` function.

### Integrate a Real Payment Gateway
In `pages/checkout.html`, replace the Place Order logic with your gateway SDK (Razorpay, PayU, Cashfree), then confirm the order after payment success callback.

---

## 📝 Important Notes

- Database auto-creates at `database/allthings.db` on first request
- Demo product images use external URLs (internet required to display)
- `assets/uploads/` must be writable for image uploads to work
- For production: enforce HTTPS, add rate limiting, and use strong password hashing

---

## 👨‍💻 Developer

**Pritam Saha**
GitHub: [@IAmAmanRaj](https://github.com/IAmAmanRaj)

---

## 📄 License

This project is licensed under the **Apache License 2.0** — see the [LICENSE](LICENSE) file for details.

> ⚠️ As required by the license, the developer credit **"Pritam Saha"** must be retained in all copies and derivative works.

---

<div align="center">
Made with ❤️ by <strong>Aman Raj</strong> — 2026
</div>
