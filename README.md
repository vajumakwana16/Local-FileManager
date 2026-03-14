# 📂 Local FileManager — Pro Edition

![Header Image](https://raw.githubusercontent.com/vajumakwana16/Local-FileManager/main/project-banner.png)

> A premium, high-performance file management system with a stunning glassmorphism UI. Built for speed, security, and elegance.

---

## ✨ Features

- **🚀 Drag & Drop Mastery**: Instant uploads with real-time progress tracking.
- **🖼️ Smart Previews**: View images, videos, audio, and PDFs directly in the browser.
- **📂 Dated Organization**: Automagically organizes uploads into date-wise folders.
- **🔍 Instant Search**: Blade-fast filename filtering as you type.
- **🛠️ Full Control**: Download, Rename, and Delete files with zero page reloads (AJAX-powered).
- **💅 Premium Design**: A state-of-the-art dark mode UI with glassmorphism, micro-animations, and vibrant accents.
- **📱 Fully Responsive**: Seamless experience across Desktop, Tablet, and Mobile.

---

## 🛠️ Technology Stack

- **Core**: PHP (Modular, efficient backend)
- **Styling**: Vanilla CSS (Custom Glassmorphism framework)
- **Engine**: Vanilla JavaScript (ES6+, XHR2 for progressive uploads)
- **Fonts**: *Space Grotesk* for headings & *Inter* for maximum readability.

---

## 🚀 Installation

### 1. Prerequisites
Ensure you have **XAMPP**, **WAMP**, or any PHP-ready server environment installed.

### 2. Setup
Clone the repository into your web root (e.g., `htdocs` for XAMPP):
```bash
git clone https://github.com/vajumakwana16/Local-FileManager.git
```

### 3. Permissions
Make sure the `uploads` directory is writable:
```bash
chmod -R 777 uploads
```

### 4. Browser Access
Open your favorite browser and navigate to:
`http://localhost/Local-FileManager`

---

## ⚙️ Configuration (Large File Support)

We have included a custom `.htaccess` to enable large file uploads (up to 500MB). To modify limits, edit `.htaccess`:

```apache
php_value upload_max_filesize 500M
php_value post_max_size       512M
php_value max_execution_time  300
```

---

## 📸 Screenshots

| Home Grid View | File Preview Modal |
| :---: | :---: |
| ![Home](https://raw.githubusercontent.com/vajumakwana16/Local-FileManager/main/screenshots/home.png) | ![Preview](https://raw.githubusercontent.com/vajumakwana16/Local-FileManager/main/screenshots/preview.png) |

---

## 🤝 Contributing

Contributions are welcome! Feel free to open an issue or submit a pull request to make **Local FileManager** even better.

---

## 📄 License

Distributed under the MIT License. See `LICENSE` for more information.

---

Developed with ❤️ for the Developer Community.
