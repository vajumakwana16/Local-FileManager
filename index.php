<?php
$baseDir = __DIR__ . "/uploads";
if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);

/* ================= UPLOAD ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    // Bump limits at runtime too (belt-and-suspenders with .htaccess)
    @ini_set('upload_max_filesize', '500M');
    @ini_set('post_max_size', '512M');

    $parent = isset($_POST['parent_folder']) ? $_POST['parent_folder'] : '';
    $parent = ltrim($parent, '/\\');
    if (strpos($parent, '..') !== false) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "msg" => "Invalid parent path"]);
        exit;
    }

    if ($parent !== '') {
        $dir = "$baseDir/$parent";
        $targetFolder = $parent;
    } else {
        $today = date('Y-m-d');
        $dir = "$baseDir/$today";
        $targetFolder = $today;
    }
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $uploaded = [];
    $errors = [];

    $errMap = [
        UPLOAD_ERR_INI_SIZE => 'File too large (exceeds server limit)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds form limit)',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by PHP extension',
    ];

    foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
        $errCode = $_FILES['files']['error'][$i];
        $name = basename($_FILES['files']['name'][$i]);

        if ($errCode === UPLOAD_ERR_OK) {
            if (move_uploaded_file($tmp, "$dir/$name")) {
                $uploaded[] = $name;
            } else {
                $errors[] = "$name: Could not move to upload folder";
            }
        } else {
            $msg = $errMap[$errCode] ?? "Unknown error (code $errCode)";
            $errors[] = "$name: $msg";
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        "status" => empty($errors) ? "ok" : (empty($uploaded) ? "error" : "partial"),
        "files" => $uploaded,
        "errors" => $errors,
        "folder" => $targetFolder,
    ]);
    exit;
}

/* ================= DELETE ================= */
if (isset($_POST['delete'])) {
    $file = realpath($baseDir . "/" . $_POST['delete']);
    if ($file && strpos($file, realpath($baseDir)) === 0 && file_exists($file)) {
        if (!function_exists('deleteRecursive')) {
            function deleteRecursive($dir) {
                if (!is_dir($dir)) {
                    return unlink($dir);
                }
                foreach (scandir($dir) as $item) {
                    if ($item == '.' || $item == '..') continue;
                    if (!deleteRecursive($dir . DIRECTORY_SEPARATOR . $item)) return false;
                }
                return rmdir($dir);
            }
        }
        if (deleteRecursive($file)) {
            header('Content-Type: application/json');
            echo json_encode(["status" => "ok"]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(["status" => "error", "msg" => "Failed to delete"]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "msg" => "File not found or invalid path"]);
    }
    exit;
}

/* ================= RENAME ================= */
if (isset($_POST['rename_old'], $_POST['rename_new'])) {
    $old = realpath($baseDir . "/" . $_POST['rename_old']);
    if ($old && strpos($old, realpath($baseDir)) === 0) {
        $new = dirname($old) . "/" . basename($_POST['rename_new']);
        rename($old, $new);
        header('Content-Type: application/json');
        echo json_encode(["status" => "ok"]);
    }
    exit;
}

/* ================= CREATE FOLDER ================= */
if (isset($_POST['create_folder'])) {
    $folderName = basename($_POST['create_folder']);
    $folderName = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $folderName);
    $folderName = trim($folderName);
    
    $parent = isset($_POST['parent_folder']) ? $_POST['parent_folder'] : '';
    $parent = ltrim($parent, '/\\');
    if (strpos($parent, '..') !== false) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "msg" => "Invalid parent path"]);
        exit;
    }

    if ($folderName !== '') {
        $dir = $baseDir;
        if ($parent !== '') {
            $dir .= '/' . $parent;
        }
        $dir .= '/' . $folderName;
        
        if (!is_dir($dir)) {
            if (mkdir($dir, 0777, true)) {
                header('Content-Type: application/json');
                echo json_encode(["status" => "ok"]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(["status" => "error", "msg" => "Failed to create directory"]);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(["status" => "error", "msg" => "Folder already exists"]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "msg" => "Invalid folder name"]);
    }
    exit;
}

/* ================= SAVE FILE (CREATE/EDIT) ================= */
if (isset($_POST['save_file'], $_POST['file_content'])) {
    $relPath = ltrim($_POST['save_file'], '/\\');
    if (strpos($relPath, '..') !== false) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "msg" => "Invalid path"]);
        exit;
    }
    $file = $baseDir . '/' . $relPath;
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    if (file_put_contents($file, $_POST['file_content']) !== false) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "ok"]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "msg" => "Failed to write file"]);
    }
    exit;
}

/* ================= READ FILE ================= */
if (isset($_GET['read_file'])) {
    $relPath = ltrim($_GET['read_file'], '/\\');
    if (strpos($relPath, '..') !== false) exit;
    $file = $baseDir . '/' . $relPath;
    if (file_exists($file) && is_file($file)) {
        header('Content-Type: text/plain');
        echo file_get_contents($file);
    }
    exit;
}

/* ================= FILE LIST (AJAX) ================= */
if (isset($_GET['ajax_files'])) {
    $folder = ltrim($_GET['ajax_files'], '/\\');
    if (strpos($folder, '..') !== false) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    $files = [];
    $targetDir = $folder !== '' ? "$baseDir/$folder" : $baseDir;
    if (is_dir($targetDir)) {
        foreach (array_diff(scandir($targetDir), ['.', '..']) as $f) {
            $path = "$targetDir/$f";
            $isDir = is_dir($path);
            if ($isDir || is_file($path)) {
                $files[] = [
                    'name' => $f,
                    'size' => $isDir ? 0 : filesize($path),
                    'modified' => filemtime($path),
                    'ext' => $isDir ? '' : strtolower(pathinfo($f, PATHINFO_EXTENSION)),
                    'is_dir' => $isDir
                ];
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($files);
    exit;
}

/* ================= FOLDER LIST (AJAX) ================= */
if (isset($_GET['ajax_folders'])) {
    $flist = array_filter(glob("$baseDir/*"), 'is_dir');
    rsort($flist);
    $result = [];
    foreach ($flist as $f) {
        $name = basename($f);
        $count = count(array_filter(scandir($f), function($x) use ($f) { return $x !== '.' && $x !== '..' && is_file("$f/$x"); }));
        $result[] = ['name' => $name, 'count' => $count];
    }
    $rootCount = count(array_filter(scandir($baseDir), function($x) use ($baseDir) { return $x !== '.' && $x !== '..' && is_file("$baseDir/$x"); }));
    header('Content-Type: application/json');
    echo json_encode([
        'folders' => $result,
        'root_count' => $rootCount
    ]);
    exit;
}

/* ================= DATA ================= */
$folders = array_filter(glob("$baseDir/*"), 'is_dir');
rsort($folders);
$folderData = [];
foreach ($folders as $f) {
    $name = basename($f);
    $count = count(array_filter(scandir($f), function($x) use ($f) { return $x !== '.' && $x !== '..' && is_file("$f/$x"); }));
    $folderData[] = ['name' => $name, 'count' => $count];
}

$rootCount = count(array_filter(scandir($baseDir), function($x) use ($baseDir) { return $x !== '.' && $x !== '..' && is_file("$baseDir/$x"); }));

/* ================= MOBILE URL DETECTION ================= */
$host = $_SERVER['HTTP_HOST'];
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    $localIP = gethostbyname(gethostname());
    if ($localIP !== '127.0.0.1' && filter_var($localIP, FILTER_VALIDATE_IP)) {
        $host = str_replace(['localhost', '127.0.0.1'], $localIP, $host);
    }
}
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$mobileScannerUrl = $protocol . "://" . $host . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local FileManager</title>
    <meta name="description" content="Local FileManager is a premium file manager with drag & drop upload, preview, rename, download and delete functionality.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── Reset & Base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #070b14;
            --surface:   #0d1525;
            --surface2:  #111e34;
            --surface3:  #162238;
            --border:    rgba(99,179,255,.12);
            --border2:   rgba(99,179,255,.22);
            --accent:    #3b82f6;
            --accent2:   #6366f1;
            --success:   #10b981;
            --danger:    #ef4444;
            --warn:      #f59e0b;
            --text:      #e2e8f0;
            --text2:     #94a3b8;
            --text3:     #64748b;
            --glow:      rgba(59,130,246,.35);
            --radius:    16px;
            --radius-sm: 10px;
        }

        html, body {
            height: 100%;
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--surface); }
        ::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 3px; }

        /* ── Animated background ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% -10%, rgba(59,130,246,.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 110%, rgba(99,102,241,.10) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Layout ── */
        .app {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ── Header ── */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 0 20px;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 42px; height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            box-shadow: 0 0 20px var(--glow);
        }

        .logo h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px; font-weight: 700;
            background: linear-gradient(90deg, #e2e8f0, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        .logo span {
            font-size: 11px; font-weight: 500;
            color: var(--text3);
            letter-spacing: .5px;
        }

        .header-stats {
            display: flex; gap: 24px; align-items: center;
        }

        .stat {
            text-align: right;
        }

        .stat-val {
            font-size: 18px; font-weight: 700;
            color: var(--accent);
        }

        .stat-lbl {
            font-size: 11px; color: var(--text3);
            text-transform: uppercase; letter-spacing: .5px;
        }

        .scanner-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 18px;
            background: rgba(59,130,246,.12);
            border: 1px solid var(--border2);
            color: var(--accent);
            font-size: 13px; font-weight: 600;
            border-radius: 50px; cursor: pointer;
            transition: all .2s;
            backdrop-filter: blur(10px);
        }

        .scanner-btn:hover {
            background: var(--accent);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--glow);
        }

        .qr-wrap {
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 20px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        /* ── Main ── */
        main { flex: 1; padding: 28px 0 40px; }

        /* ── Upload Zone ── */
        .upload-zone {
            position: relative;
            border: 2px dashed var(--border2);
            border-radius: var(--radius);
            padding: 48px 40px;
            text-align: center;
            cursor: pointer;
            background: linear-gradient(135deg, rgba(59,130,246,.04), rgba(99,102,241,.04));
            transition: all .3s ease;
            overflow: hidden;
            margin-bottom: 32px;
        }

        /* Children must not intercept drag events — they belong to the zone */
        .upload-zone > *:not(input):not(button) {
            pointer-events: none;
        }
        .upload-zone .upload-btn {
            pointer-events: auto;
        }

        .upload-zone::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(59,130,246,.08), rgba(99,102,241,.08));
            opacity: 0;
            transition: opacity .3s;
        }

        .upload-zone.drag-over {
            border-color: var(--accent);
            transform: scale(1.005);
            box-shadow: 0 0 40px rgba(59,130,246,.25), inset 0 0 60px rgba(59,130,246,.06);
        }

        .upload-zone.drag-over::before { opacity: 1; }

        .upload-icon {
            font-size: 52px;
            margin-bottom: 16px;
            display: block;
            transition: transform .3s;
        }

        .upload-zone:hover .upload-icon,
        .upload-zone.drag-over .upload-icon {
            transform: translateY(-6px);
        }

        .upload-title {
            font-size: 16px; font-weight: 600; color: var(--text);
            margin-bottom: 6px;
        }

        .upload-sub {
            font-size: 13px; color: var(--text3);
        }

        .upload-btn {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 20px;
            padding: 10px 24px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff; font-size: 14px; font-weight: 600;
            border: none; border-radius: 50px; cursor: pointer;
            box-shadow: 0 4px 20px var(--glow);
            transition: all .2s;
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px var(--glow);
        }

        #fileInput { display: none; }

        /* ── Progress Bar ── */
        .progress-container {
            margin-bottom: 20px;
            display: none;
        }

        .progress-bar-wrap {
            background: var(--surface2);
            border-radius: 50px;
            height: 6px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            border-radius: 50px;
            transition: width .3s ease;
            width: 0%;
        }

        .progress-label {
            font-size: 12px; color: var(--text2);
            margin-bottom: 8px;
            display: flex; justify-content: space-between;
        }

        /* ── Toolbar ── */
        .toolbar {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .search-wrap {
            flex: 1;
            position: relative;
            min-width: 200px;
        }

        .search-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            font-size: 16px; pointer-events: none; color: var(--text3);
        }

        .search-input {
            width: 100%;
            padding: 11px 14px 11px 42px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .search-input::placeholder { color: var(--text3); }

        .search-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }

        .view-toggle {
            display: flex; gap: 4px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 4px;
        }

        .view-btn {
            width: 36px; height: 36px;
            border: none; border-radius: 7px;
            background: transparent;
            color: var(--text3);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            transition: all .2s;
        }

        .view-btn.active {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff;
            box-shadow: 0 2px 10px var(--glow);
        }

        .sort-select {
            padding: 10px 14px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text2);
            font-size: 13px;
            font-family: inherit;
            outline: none;
            cursor: pointer;
            transition: border-color .2s;
        }

        .sort-select:focus { border-color: var(--accent); }

        /* ── Breadcrumb ── */
        .breadcrumb {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 22px;
            font-size: 13px; color: var(--text3);
        }

        .breadcrumb a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            display: flex; align-items: center; gap: 5px;
            transition: opacity .2s;
        }

        .breadcrumb a:hover { opacity: .8; }

        .breadcrumb-sep { color: var(--text3); }

        .breadcrumb-cur {
            color: var(--text);
            font-weight: 500;
        }

        /* ── Section Label ── */
        .section-label {
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--text3);
            margin-bottom: 14px;
        }

        /* ── Folder Grid ── */
        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 36px;
        }

        .folder-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 18px;
            cursor: pointer;
            transition: all .25s ease;
            position: relative;
            overflow: hidden;
        }

        .folder-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            opacity: 0;
            transition: opacity .25s;
        }

        .folder-card:hover {
            background: var(--surface3);
            border-color: var(--border2);
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0,0,0,.35);
        }

        .folder-card:hover::before { opacity: 1; }

        .folder-icon-wrap {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(59,130,246,.15), rgba(99,102,241,.15));
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
        }

        .folder-name {
            font-size: 14px; font-weight: 600; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .folder-meta {
            font-size: 12px; color: var(--text3);
        }

        /* ── File Grid ── */
        #fileGrid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
        }

        #fileGrid.list-view {
            grid-template-columns: 1fr;
            gap: 6px;
        }

        /* ── File Card ── */
        .file-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: all .25s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            animation: fadeUp .3s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .file-card:hover {
            border-color: var(--border2);
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0,0,0,.4);
        }

        /* Thumbnail */
        .file-thumb {
            width: 100%; height: 140px;
            object-fit: cover;
            display: block;
        }

        .file-icon-thumb {
            height: 140px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 8px;
            font-size: 44px;
            background: linear-gradient(135deg, var(--surface3), var(--surface2));
        }

        .file-ext-badge {
            font-size: 10px; font-weight: 700;
            letter-spacing: 1px;
            padding: 3px 8px;
            border-radius: 4px;
            background: rgba(99,130,255,.15);
            color: var(--accent);
            text-transform: uppercase;
        }

        .file-body {
            padding: 12px 14px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .file-name {
            font-size: 13px; font-weight: 500; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .file-meta {
            font-size: 11px; color: var(--text3);
            margin-bottom: 12px;
        }

        .file-actions {
            display: flex; gap: 6px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 10px;
            font-size: 12px; font-weight: 500;
            border: none; border-radius: 7px; cursor: pointer;
            font-family: inherit;
            transition: all .18s;
            flex: 1;
            justify-content: center;
        }

        .btn-view   { background: rgba(99,102,241,.15); color: #a5b4fc; }
        .btn-dl     { background: rgba(16,185,129,.12); color: #6ee7b7; }
        .btn-rename { background: rgba(245,158,11,.12); color: #fcd34d; }
        .btn-del    { background: rgba(239,68,68,.12);  color: #fca5a5; }
        .btn-edit   { background: rgba(168,85,247,.12); color: #c084fc; } /* Added edit button styles */

        .btn:hover { filter: brightness(1.25); transform: scale(1.04); }

        /* ── List view file card ── */
        #fileGrid.list-view .file-card {
            flex-direction: row;
            align-items: center;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
        }

        #fileGrid.list-view .file-thumb,
        #fileGrid.list-view .file-icon-thumb {
            width: 44px; height: 44px;
            border-radius: 8px;
            flex-shrink: 0;
            font-size: 22px;
        }

        #fileGrid.list-view .file-body {
            flex-direction: row;
            align-items: center;
            padding: 0 0 0 12px;
            gap: 12px;
        }

        #fileGrid.list-view .file-name { margin-bottom: 0; flex: 1; min-width: 0; }
        #fileGrid.list-view .file-meta { margin-bottom: 0; min-width: 90px; }
        #fileGrid.list-view .file-actions { flex-wrap: nowrap; margin-left: auto; }
        #fileGrid.list-view .btn { flex: unset; }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text3);
        }

        .empty-state .empty-icon { font-size: 64px; margin-bottom: 16px; display: block; }
        .empty-state h3 { font-size: 18px; color: var(--text2); margin-bottom: 8px; }
        .empty-state p { font-size: 14px; }

        /* ── Toast Notifications ── */
        .toast-container {
            position: fixed;
            bottom: 28px; right: 28px;
            display: flex; flex-direction: column; gap: 10px;
            z-index: 9999;
        }

        .toast {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 18px;
            background: var(--surface3);
            border: 1px solid var(--border2);
            border-radius: var(--radius-sm);
            box-shadow: 0 10px 40px rgba(0,0,0,.5);
            font-size: 14px; color: var(--text);
            min-width: 280px;
            animation: slideInToast .3s ease;
            backdrop-filter: blur(12px);
        }

        @keyframes slideInToast {
            from { opacity: 0; transform: translateX(60px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .toast.out { animation: slideOutToast .3s ease forwards; }

        @keyframes slideOutToast {
            to { opacity: 0; transform: translateX(60px); }
        }

        .toast-icon { font-size: 18px; }
        .toast-success { border-left: 3px solid var(--success); }
        .toast-error   { border-left: 3px solid var(--danger); }
        .toast-info    { border-left: 3px solid var(--accent); }

        /* ── Modals ── */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.7);
            backdrop-filter: blur(8px);
            display: flex; align-items: center; justify-content: center;
            z-index: 1000;
            opacity: 0; pointer-events: none;
            transition: opacity .25s;
        }

        .modal-overlay.open { opacity: 1; pointer-events: all; }

        .modal {
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 20px;
            padding: 32px;
            width: 420px; max-width: 90vw;
            box-shadow: 0 24px 80px rgba(0,0,0,.6);
            transform: scale(.94) translateY(10px);
            transition: transform .25s ease;
        }

        .modal-overlay.open .modal { transform: scale(1) translateY(0); }

        .modal-header {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 24px;
        }

        .modal-header-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }

        .modal-header-icon.rename { background: rgba(245,158,11,.15); }
        .modal-header-icon.delete { background: rgba(239,68,68,.15); }
        .modal-header-icon.preview { background: rgba(99,102,241,.15); }
        .modal-header-icon.edit { background: rgba(168,85,247,.15); }

        .modal-title {
            font-size: 18px; font-weight: 700;
            font-family: 'Space Grotesk', sans-serif;
        }

        .modal-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--surface3);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .modal-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }
        
        .modal-textarea {
            width: 100%;
            height: 250px;
            padding: 12px 16px;
            background: var(--surface3);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 14px;
            font-family: monospace;
            outline: none;
            resize: vertical;
            margin-top: 10px;
            transition: border-color .2s, box-shadow .2s;
        }
        
        .modal-textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }

        .modal-btns {
            display: flex; gap: 10px; margin-top: 22px;
        }

        .modal-btn {
            flex: 1; padding: 11px;
            border: none; border-radius: 10px;
            font-size: 14px; font-weight: 600;
            font-family: inherit; cursor: pointer;
            transition: all .2s;
        }

        .modal-btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff;
            box-shadow: 0 4px 16px var(--glow);
        }

        .modal-btn-primary:hover { transform: translateY(-1px); filter: brightness(1.1); }

        .modal-btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
            box-shadow: 0 4px 16px rgba(239,68,68,.3);
        }

        .modal-btn-danger:hover { transform: translateY(-1px); filter: brightness(1.1); }

        .modal-btn-cancel {
            background: var(--surface3);
            color: var(--text2);
            border: 1px solid var(--border);
        }

        .modal-btn-cancel:hover { background: #1e2e4a; }

        /* ── Preview Modal ── */
        #previewModal .modal {
            width: 780px; max-width: 94vw;
        }

        /* ── Create/Edit Modal ── */
        #editorModal .modal {
            width: 96vw;
            max-width: 1800px;
            height: 92vh;
            display: flex;
            flex-direction: column;
        }
        
        #editorModal .modal-textarea {
            flex: 1;
            height: auto;
            resize: none;
        }

        .preview-body {
            max-height: 60vh;
            display: flex; align-items: center; justify-content: center;
            overflow: auto;
            background: var(--surface3);
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .preview-body img, .preview-body video, .preview-body audio {
            max-width: 100%; max-height: 60vh;
            border-radius: 8px;
        }

        .preview-info {
            font-size: 13px; color: var(--text2);
            text-align: center;
        }

        .preview-nopreview {
            padding: 60px;
            text-align: center;
            color: var(--text3);
        }

        .preview-nopreview .big-icon { font-size: 64px; display: block; margin-bottom: 12px; }

        /* ── Confirm Delete modal ── */
        .delete-filename {
            font-size: 14px; color: var(--text2);
            margin: 12px 0;
            padding: 10px 14px;
            background: var(--surface3);
            border-radius: 8px;
            word-break: break-all;
        }

        /* ── Upload list ── */
        .upload-list {
            list-style: none;
            margin-bottom: 20px;
            display: flex; flex-direction: column; gap: 8px;
        }

        .upload-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px; color: var(--text2);
        }

        .upload-item-icon { font-size: 18px; }
        .upload-item-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .upload-item-size { color: var(--text3); white-space: nowrap; }

        /* ── Back button ── */
        .back-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 18px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 50px;
            color: var(--text2);
            font-size: 13px; font-weight: 500;
            cursor: pointer;
            font-family: inherit;
            transition: all .2s;
            text-decoration: none;
        }

        .back-btn:hover {
            background: var(--surface3);
            border-color: var(--border2);
            color: var(--text);
            transform: translateX(-2px);
        }

        /* ── Folder count badge ── */
        .folder-count-badge {
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(59,130,246,.15);
            color: var(--accent);
            border-radius: 50px;
            font-size: 11px; font-weight: 600;
            padding: 2px 8px;
            margin-left: auto;
        }

        /* ── Options Dropdown ── */
        .file-options-wrap {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }

        .options-btn {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(13, 21, 37, 0.85);
            border: 1px solid var(--border);
            color: var(--text2);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            outline: none;
        }

        .options-btn:hover {
            background: var(--surface3);
            color: var(--text);
            border-color: var(--border2);
        }

        .options-dropdown {
            position: absolute;
            top: 32px;
            right: 0;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: var(--radius-sm);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            width: 140px;
            display: none;
            flex-direction: column;
            z-index: 20;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .options-dropdown.show {
            display: flex;
        }

        .dropdown-item {
            padding: 8px 12px;
            font-size: 13px;
            color: var(--text2);
            background: transparent;
            border: none;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
            transition: all 0.15s;
            width: 100%;
        }

        .dropdown-item:hover {
            background: var(--surface3);
            color: var(--text);
        }

        .dropdown-item.danger-item {
            color: #fca5a5;
        }

        .dropdown-item.danger-item:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }
        
        .file-card {
            cursor: pointer;
        }

        #fileGrid.list-view .file-options-wrap {
            top: 50%;
            transform: translateY(-50%);
            right: 15px;
        }

        #fileGrid.list-view .file-card {
            padding-right: 50px;
        }

        /* ── Premium Styling Enhancements ── */
        .folder-card, .file-card {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.3s, box-shadow 0.3s !important;
        }
        
        .folder-card:hover {
            transform: translateY(-5px) !important;
            box-shadow: 0 16px 36px rgba(59, 130, 246, 0.25) !important;
            border-color: rgba(59, 130, 246, 0.4) !important;
        }

        .file-card:hover {
            transform: translateY(-5px) !important;
            box-shadow: 0 16px 36px rgba(99, 102, 241, 0.25) !important;
            border-color: rgba(99, 102, 241, 0.4) !important;
        }

        .folder-icon-wrap {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        .folder-card:hover .folder-icon-wrap {
            transform: scale(1.1) rotate(3deg);
        }

        .file-thumb {
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        .file-card:hover .file-thumb {
            transform: scale(1.06);
        }

        .modal {
            background: rgba(13, 21, 37, 0.85) !important;
            backdrop-filter: blur(20px);
            border-color: rgba(99, 179, 255, 0.25) !important;
        }

        /* ── Advanced Responsiveness ── */
        @media (max-width: 850px) {
            .toolbar {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .search-wrap {
                grid-column: span 2;
                width: 100%;
            }
            .sort-select {
                width: 100%;
            }
            .view-toggle {
                justify-content: center;
            }
            .toolbar .back-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 640px) {
            header {
                flex-direction: column;
                gap: 18px;
                text-align: center;
                align-items: center;
                padding-bottom: 24px;
            }
            .header-stats {
                width: 100%;
                justify-content: center;
                gap: 28px;
            }
            .scanner-btn {
                width: 100%;
                justify-content: center;
            }
            .folder-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 12px;
            }
            #fileGrid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 12px;
            }
            .upload-zone {
                padding: 32px 16px;
            }
            .upload-icon {
                font-size: 40px;
            }
            .modal {
                padding: 24px 20px;
            }
        }

        @media (max-width: 480px) {
            .header-stats {
                flex-wrap: wrap;
                justify-content: space-around;
                gap: 16px;
            }
            .toolbar {
                grid-template-columns: 1fr;
            }
            .search-wrap {
                grid-column: span 1;
            }
            .view-toggle {
                display: none;
            }
        }
    </style>
</head>

<body>
<div class="app">

    <!-- ── Header ── -->
    <header>
        <div class="logo">
            <div class="logo-icon">🗂</div>
            <div>
                <h1>Local FileManager</h1>
                <span>Professional Tool</span>
            </div>
        </div>
        <div class="header-stats">
            <button class="scanner-btn" onclick="startCreateFolder()">
                <span>📁 New Folder</span>
            </button>
            <button class="scanner-btn" onclick="startCreateFile()">
                <span>➕ New File</span>
            </button>
            <button class="scanner-btn" onclick="showScanner()">
                <span>📱 Connect Phone</span>
            </button>
            <div class="stat">
                <div class="stat-val" id="totalFolders"><?= count($folderData) + 1 ?></div>
                <div class="stat-lbl">Folders</div>
            </div>
            <div class="stat">
                <div class="stat-val" id="totalFiles">–</div>
                <div class="stat-lbl">Files</div>
            </div>
        </div>
    </header>

    <!-- ── Main ── -->
    <main>

        <!-- Drag & Drop Upload Zone -->
        <div class="upload-zone" id="uploadZone">
            <span class="upload-icon">☁️</span>
            <div class="upload-title">Drag & drop files here</div>
            <div class="upload-sub">or click to browse — any file type supported</div>
            <button class="upload-btn" onclick="event.stopPropagation();document.getElementById('fileInput').click()">
                📂 Browse Files
            </button>
            <input type="file" id="fileInput" multiple>
        </div>

        <!-- Upload Progress -->
        <div class="progress-container" id="progressContainer">
            <div class="progress-label">
                <span id="progressLabel">Uploading...</span>
                <span id="progressPct">0%</span>
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar" id="progressBar"></div>
            </div>
        </div>

        <!-- Folder View -->
        <div id="folderView">
            <div class="section-label">📁 Date Folders & Root</div>
            <div class="folder-grid" id="folderGrid">
                <div class="folder-card" onclick="openFolder('')">
                    <div class="folder-icon-wrap" style="background: linear-gradient(135deg, rgba(16,185,129,.15), rgba(52,211,153,.15));">🏠</div>
                    <div class="folder-name">Root Directory</div>
                    <div class="folder-meta" style="display:flex;align-items:center;">
                        <?= $rootCount ?> file<?= $rootCount !== 1 ? 's' : '' ?>
                        <span class="folder-count-badge" style="margin-left:8px; color: #10b981; background: rgba(16,185,129,.15);"><?= $rootCount ?></span>
                    </div>
                </div>
                <?php foreach ($folderData as $fd): ?>
                    <div class="folder-card" onclick="openFolder('<?= htmlspecialchars($fd['name']) ?>')">
                        <div class="folder-icon-wrap">📁</div>
                        <div class="folder-name"><?= htmlspecialchars($fd['name']) ?></div>
                        <div class="folder-meta" style="display:flex;align-items:center;">
                            <?= $fd['count'] ?> file<?= $fd['count'] !== 1 ? 's' : '' ?>
                            <span class="folder-count-badge" style="margin-left:8px"><?= $fd['count'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>
            
            <!-- File View (hidden initially) -->
            <div id="fileView" style="display:none">
                <div class="breadcrumb">
                    <a href="#" onclick="closeFolder();return false">🏠 Home</a>
                    <span class="breadcrumb-sep">›</span>
                    <span class="breadcrumb-cur" id="breadcrumbFolder"></span>
                </div>
            
                <div class="toolbar">
                    <a href="#" class="back-btn" onclick="goBack();return false">← Back</a>
                    <button class="back-btn" style="border-color: var(--accent); color: var(--accent);" onclick="startCreateFolder()">📁 New Folder</button>
                    <button class="back-btn" style="border-color: var(--accent); color: var(--accent);" onclick="startCreateFile()">➕ New File</button>
                    <div class="search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" class="search-input" id="searchInput" placeholder="Search files…">
                    </div>
                    <select class="sort-select" id="sortSelect" onchange="sortFiles()">
                        <option value="name-asc">Name A–Z</option>
                        <option value="name-desc">Name Z–A</option>
                        <option value="size-desc">Largest first</option>
                        <option value="size-asc">Smallest first</option>
                        <option value="date-desc">Newest first</option>
                        <option value="date-asc">Oldest first</option>
                    </select>
                    <div class="view-toggle">
                        <button class="view-btn active" id="gridViewBtn" onclick="setView('grid')" title="Grid view">⊞</button>
                        <button class="view-btn" id="listViewBtn" onclick="setView('list')" title="List view">☰</button>
                    </div>
                </div>
            
                <div id="fileGrid"></div>
            </div>
            
            </main>
            </div>
            
            <!-- ── Toast Container ── -->
            <div class="toast-container" id="toastContainer"></div>

<!-- ── Rename Modal ── -->
<div class="modal-overlay" id="renameModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon rename">✏️</div>
            <div class="modal-title">Rename File</div>
        </div>
        <input class="modal-input" id="renameInput" placeholder="New filename…">
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('renameModal')">Cancel</button>
            <button class="modal-btn modal-btn-primary" onclick="confirmRename()">Save Changes</button>
        </div>
    </div>
</div>

<!-- ── Delete Confirm Modal ── -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon delete">🗑️</div>
            <div class="modal-title">Delete File</div>
        </div>
        <p style="color:var(--text2);font-size:14px">Are you sure you want to permanently delete this file?</p>
        <div class="delete-filename" id="deleteFilename"></div>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
            <button class="modal-btn modal-btn-danger" onclick="confirmDelete()">Delete Forever</button>
        </div>
    </div>
</div>

<!-- ── Create/Edit Modal ── -->
<div class="modal-overlay" id="editorModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon edit">📝</div>
            <div class="modal-title" id="editorTitle">Edit File</div>
        </div>
        <input class="modal-input" id="editorFilename" placeholder="Filename (e.g. script.js)">
        <textarea class="modal-textarea" id="editorContent" placeholder="Enter file content here..."></textarea>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('editorModal')">Cancel</button>
            <button class="modal-btn modal-btn-primary" onclick="confirmSaveFile()">Save File</button>
        </div>
    </div>
</div>

<!-- ── Preview Modal ── -->
<div class="modal-overlay" id="previewModal" onclick="if(event.target===this)closeModal('previewModal')">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon preview">👁️</div>
            <div class="modal-title" id="previewTitle">Preview</div>
        </div>
        <div class="preview-body" id="previewBody"></div>
        <div class="preview-info" id="previewInfo"></div>
        <div class="modal-btns" style="margin-top:16px">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('previewModal')">Close</button>
            <button class="modal-btn modal-btn-primary" id="previewDlBtn">↓ Download</button>
        </div>
    </div>
</div>

<!-- ── Create Folder Modal ── -->
<div class="modal-overlay" id="createFolderModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon rename">📁</div>
            <div class="modal-title">Create New Folder</div>
        </div>
        <input class="modal-input" id="folderNameInput" placeholder="Folder name…">
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('createFolderModal')">Cancel</button>
            <button class="modal-btn modal-btn-primary" onclick="confirmCreateFolder()">Create Folder</button>
        </div>
    </div>
</div>

<!-- ── Scanner Modal ── -->
<div class="modal-overlay" id="scannerModal">
    <div class="modal" style="text-align:center">
        <div class="modal-header" style="justify-content:center">
            <div class="modal-header-icon preview">📱</div>
            <div class="modal-title">Connect Mobile</div>
        </div>
        <p style="color:var(--text2);font-size:14px;margin-bottom:10px">Scan this QR code with your phone to open Local FileManager instantly.</p>
        <div class="qr-wrap">
            <img id="qrImage" src="" alt="QR Code" style="width:200px;height:200px;display:block">
        </div>
        <p style="color:var(--text3);font-size:11px">Make sure both devices are on the same Wi-Fi network.</p>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('scannerModal')">Close</button>
        </div>
    </div>
</div>

<script>
/* ========================================================
   STATE
======================================================== */
let currentFolder = null;
let allFiles = [];
let renamePath = '';
let deletePath = '';

/* ========================================================
   FOLDER NAVIGATION
======================================================= */
function openFolder(name) {
    currentFolder = name;
    document.getElementById('folderView').style.display = 'none';
    document.getElementById('fileView').style.display = 'block';
    renderBreadcrumbs();
    loadFiles();
}

function closeFolder() {
    currentFolder = null;
    document.getElementById('fileView').style.display = 'none';
    document.getElementById('folderView').style.display = 'block';
    document.getElementById('searchInput').value = '';
    // Refresh folder grid so counts stay accurate
    refreshFolderGrid();
}

function goBack() {
    if (!currentFolder) {
        closeFolder();
        return;
    }
    const parts = currentFolder.split('/');
    parts.pop();
    if (parts.length === 0) {
        closeFolder();
    } else {
        openFolder(parts.join('/'));
    }
}

function renderBreadcrumbs() {
    const breadcrumb = document.querySelector('.breadcrumb');
    if (!currentFolder) {
        breadcrumb.innerHTML = `<a href="#" onclick="closeFolder();return false">🏠 Home</a> <span class="breadcrumb-sep">›</span> <span class="breadcrumb-cur">Root Directory</span>`;
        return;
    }
    
    let html = `<a href="#" onclick="closeFolder();return false">🏠 Home</a>`;
    const parts = currentFolder.split('/');
    let accumPath = '';
    
    parts.forEach((part, index) => {
        accumPath += (index > 0 ? '/' : '') + part;
        html += ` <span class="breadcrumb-sep">›</span> `;
        if (index === parts.length - 1) {
            html += `<span class="breadcrumb-cur">${part}</span>`;
        } else {
            html += `<a href="#" onclick="openFolder('${accumPath.replace(/'/g, "\\'")}');return false">${part}</a>`;
        }
    });
    
    breadcrumb.innerHTML = html;
}

async function loadFiles() {
    const res = await fetch(`?ajax_files=${encodeURIComponent(currentFolder)}`);
    allFiles = await res.json();
    document.getElementById('totalFiles').textContent = allFiles.filter(f => !f.is_dir).length;
    sortFiles();
}

/* ========================================================
   RENDER FILES
======================================================== */
const imageExts = ['jpg','jpeg','png','gif','webp','bmp','svg','avif'];
const videoExts = ['mp4','webm','ogg','mov','mkv'];
const audioExts = ['mp3','wav','ogg','flac','aac','m4a'];
const pdfExts   = ['pdf'];

function fileEmojiIcon(ext) {
    if (imageExts.includes(ext)) return '🖼️';
    if (videoExts.includes(ext)) return '🎬';
    if (audioExts.includes(ext)) return '🎵';
    if (pdfExts.includes(ext))   return '📕';
    if (['zip','rar','7z','tar','gz'].includes(ext)) return '📦';
    if (['doc','docx'].includes(ext)) return '📝';
    if (['xls','xlsx'].includes(ext)) return '📊';
    if (['ppt','pptx'].includes(ext)) return '📊';
    if (['txt','md','log'].includes(ext)) return '📄';
    if (['js','ts','html','css','php','py','json','xml','java','dart','c','cpp','h','hpp','cs','go','rs','rb','swift','kt','sh','bat','ps1','yaml','yml','ini','conf'].includes(ext)) return '💻';
    return '📎';
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    if (bytes < 1024*1024*1024) return (bytes/(1024*1024)).toFixed(1) + ' MB';
    return (bytes/(1024*1024*1024)).toFixed(2) + ' GB';
}

function formatDate(ts) {
    const d = new Date(ts * 1000);
    return d.toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'});
}

function handleFileClick(event, path, name, ext, size) {
    if (event.target.closest('.file-options-wrap')) {
        return;
    }
    
    const isImg = imageExts.includes(ext);
    const isVid = videoExts.includes(ext);
    const isAud = audioExts.includes(ext);
    const isPdf = pdfExts.includes(ext);
    const isEditable = ['js','ts','html','css','php','py','json','xml','txt','md','csv','java','dart','c','cpp','h','hpp','cs','go','rs','rb','swift','kt','sh','bat','ps1','yaml','yml','ini','conf'].includes(ext);

    if (isImg || isVid || isAud || isPdf) {
        viewFile(path, name, ext, size);
    } else if (isEditable) {
        startEditFile(path, name);
    } else {
        viewFile(path, name, ext, size);
    }
}

function toggleFileOptions(event, btn) {
    event.stopPropagation();
    const dropdown = btn.nextElementSibling;
    const isShowing = dropdown.classList.contains('show');
    
    document.querySelectorAll('.options-dropdown.show').forEach(d => {
        if (d !== dropdown) d.classList.remove('show');
    });
    
    dropdown.classList.toggle('show', !isShowing);
}

// Close options dropdowns on document click
document.addEventListener('click', e => {
    if (!e.target.closest('.file-options-wrap')) {
        document.querySelectorAll('.options-dropdown.show').forEach(d => d.classList.remove('show'));
    }
});

function renderFiles(files) {
    const grid = document.getElementById('fileGrid');
    if (!files.length) {
        grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
            <span class="empty-icon">📂</span>
            <h3>No files found</h3>
            <p>Drag &amp; drop files onto the upload zone above</p>
        </div>`;
        return;
    }
    grid.innerHTML = files.map((f, i) => {
        const path = currentFolder ? currentFolder + '/' + f.name : f.name;
        
        if (f.is_dir) {
            return `<div class="file-card folder-card file-card-folder" onclick="if(!event.target.closest('.file-options-wrap')){openFolder('${path.replace(/'/g, "\\'")}');}" data-name="${f.name.toLowerCase()}" data-size="0" data-date="${f.modified}" style="animation-delay:${i*0.04}s">
                <div class="file-icon-thumb" style="background: linear-gradient(135deg, rgba(59,130,246,.1), rgba(99,102,241,.1));">📁</div>
                <div class="file-body">
                    <div class="file-name" title="${f.name}">${f.name}</div>
                    <div class="file-meta">Folder · ${formatDate(f.modified)}</div>
                </div>
                <div class="file-options-wrap">
                    <button class="options-btn" onclick="toggleFileOptions(event, this)">⋮</button>
                    <div class="options-dropdown">
                        <button class="dropdown-item" onclick="event.stopPropagation(); startRename('${path}','${f.name}')">✏️ Rename</button>
                        <button class="dropdown-item danger-item" onclick="event.stopPropagation(); startDelete('${path}','${f.name}')">🗑️ Delete</button>
                    </div>
                </div>
            </div>`;
        }
        
        const icon = fileEmojiIcon(f.ext);
        const isImg = imageExts.includes(f.ext);
        const thumb = isImg
            ? `<img class="file-thumb" src="uploads/${path}" loading="lazy" alt="${f.name}">`
            : `<div class="file-icon-thumb">${icon}<span class="file-ext-badge">${f.ext||'file'}</span></div>`;

        const editable = ['js','ts','html','css','php','py','json','xml','txt','md','csv','java','dart','c','cpp','h','hpp','cs','go','rs','rb','swift','kt','sh','bat','ps1','yaml','yml','ini','conf'].includes(f.ext);

        return `<div class="file-card" onclick="handleFileClick(event, '${path}','${f.name}','${f.ext}',${f.size})" data-name="${f.name.toLowerCase()}" data-size="${f.size}" data-date="${f.modified}" style="animation-delay:${i*0.04}s">
            ${thumb}
            <div class="file-body">
                <div class="file-name" title="${f.name}">${f.name}</div>
                <div class="file-meta">${formatSize(f.size)} · ${formatDate(f.modified)}</div>
            </div>
            <div class="file-options-wrap">
                <button class="options-btn" onclick="toggleFileOptions(event, this)">⋮</button>
                <div class="options-dropdown">
                    <button class="dropdown-item" onclick="event.stopPropagation(); viewFile('${path}','${f.name}','${f.ext}',${f.size})">👁️ View</button>
                    ${editable ? `<button class="dropdown-item" onclick="event.stopPropagation(); startEditFile('${path}','${f.name}')">📝 Edit</button>` : ''}
                    <button class="dropdown-item" onclick="event.stopPropagation(); downloadFile('${path}','${f.name}')">📥 Download</button>
                    <button class="dropdown-item" onclick="event.stopPropagation(); startRename('${path}','${f.name}')">✏️ Rename</button>
                    <button class="dropdown-item danger-item" onclick="event.stopPropagation(); startDelete('${path}','${f.name}')">🗑️ Delete</button>
                </div>
            </div>
        </div>`;
    }).join('');
}

/* ========================================================
   SEARCH + SORT
======================================================== */
document.getElementById('searchInput').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.file-card').forEach(c => {
        c.style.display = c.dataset.name.includes(q) ? '' : 'none';
    });
});

function sortFiles() {
    const val = document.getElementById('sortSelect').value;
    const sorted = [...allFiles].sort((a, b) => {
        if (a.is_dir && !b.is_dir) return -1;
        if (!a.is_dir && b.is_dir) return 1;
        if (val === 'name-asc')  return a.name.localeCompare(b.name);
        if (val === 'name-desc') return b.name.localeCompare(a.name);
        if (val === 'size-asc')  return a.size - b.size;
        if (val === 'size-desc') return b.size - a.size;
        if (val === 'date-asc')  return a.modified - b.modified;
        if (val === 'date-desc') return b.modified - a.modified;
    });
    renderFiles(sorted);
}

/* ========================================================
   VIEW TOGGLE
======================================================== */
function setView(type) {
    const grid = document.getElementById('fileGrid');
    document.getElementById('gridViewBtn').classList.toggle('active', type === 'grid');
    document.getElementById('listViewBtn').classList.toggle('active', type === 'list');
    grid.classList.toggle('list-view', type === 'list');
}

/* ========================================================
   PREVIEW
======================================================== */
function viewFile(path, name, ext, size) {
    const modal = document.getElementById('previewModal');
    const body  = document.getElementById('previewBody');
    const title = document.getElementById('previewTitle');
    const info  = document.getElementById('previewInfo');
    const dlBtn = document.getElementById('previewDlBtn');

    title.textContent = name;
    info.textContent  = `${name}  ·  ${formatSize(size)}`;
    dlBtn.onclick = () => downloadFile(path, name);

    const src = `uploads/${path}`;
    if (imageExts.includes(ext)) {
        body.innerHTML = `<img src="${src}" alt="${name}">`;
    } else if (videoExts.includes(ext)) {
        body.innerHTML = `<video src="${src}" controls></video>`;
    } else if (audioExts.includes(ext)) {
        body.innerHTML = `<audio src="${src}" controls style="width:100%;margin:40px 0"></audio>`;
    } else if (pdfExts.includes(ext)) {
        body.innerHTML = `<iframe src="${src}" style="width:100%;height:58vh;border:none;border-radius:8px"></iframe>`;
    } else {
        body.innerHTML = `<div class="preview-nopreview">
            <span class="big-icon">${fileEmojiIcon(ext)}</span>
            <p style="color:var(--text2)">No preview available</p>
            <p style="font-size:12px;margin-top:6px">Download the file to open it</p>
        </div>`;
    }
    openModal('previewModal');
}

/* ========================================================
   DOWNLOAD
======================================================== */
function downloadFile(path, name) {
    const a = document.createElement('a');
    a.href = `uploads/${path}`;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    toast('📥 Downloading ' + name, 'info');
}

/* ========================================================
   RENAME
======================================================== */
function startRename(path, name) {
    renamePath = path;
    document.getElementById('renameInput').value = name;
    openModal('renameModal');
    setTimeout(() => document.getElementById('renameInput').select(), 250);
}

async function confirmRename() {
    const newName = document.getElementById('renameInput').value.trim();
    if (!newName) return;
    closeModal('renameModal');
    const body = new URLSearchParams({rename_old: renamePath, rename_new: newName});
    const res  = await fetch('', {method:'POST', body});
    const data = await res.json();
    if (data.status === 'ok') {
        toast('✏️ File renamed successfully', 'success');
        loadFiles();
    } else {
        toast('❌ Rename failed', 'error');
    }
}

/* ========================================================
   DELETE
======================================================== */
function startDelete(path, name) {
    deletePath = path;
    document.getElementById('deleteFilename').textContent = name;
    openModal('deleteModal');
}

async function confirmDelete() {
    closeModal('deleteModal');
    const body = new URLSearchParams({delete: deletePath});
    const res  = await fetch('', {method:'POST', body});
    const data = await res.json();
    if (data.status === 'ok') {
        toast('🗑️ File deleted', 'success');
        loadFiles();
    } else {
        toast('❌ Delete failed', 'error');
    }
}

/* ========================================================
   CREATE / EDIT
======================================================== */
let editorMode = 'create'; // 'create' or 'edit'
let editorOriginalPath = '';

function startCreateFile() {
    editorMode = 'create';
    editorOriginalPath = '';
    document.getElementById('editorTitle').textContent = 'Create New File';
    document.getElementById('editorFilename').value = '';
    document.getElementById('editorFilename').readOnly = false;
    document.getElementById('editorContent').value = '';
    openModal('editorModal');
    setTimeout(() => document.getElementById('editorFilename').focus(), 250);
}

async function startEditFile(path, name) {
    editorMode = 'edit';
    editorOriginalPath = path;
    document.getElementById('editorTitle').textContent = 'Edit File';
    document.getElementById('editorFilename').value = name;
    document.getElementById('editorFilename').readOnly = true;
    document.getElementById('editorContent').value = 'Loading...';
    openModal('editorModal');
    
    try {
        const res = await fetch(`?read_file=${encodeURIComponent(path)}`);
        if (!res.ok) throw new Error('Network response was not ok');
        const text = await res.text();
        document.getElementById('editorContent').value = text;
    } catch(err) {
        document.getElementById('editorContent').value = '';
        toast('❌ Failed to read file content', 'error');
    }
}

async function confirmSaveFile() {
    const name = document.getElementById('editorFilename').value.trim();
    if (!name) return toast('❌ Filename is required', 'error');
    
    let targetPath = '';
    if (editorMode === 'edit') {
        targetPath = editorOriginalPath;
    } else {
        targetPath = (currentFolder ? currentFolder + '/' : '') + name;
    }
    
    const content = document.getElementById('editorContent').value;
    
    const body = new URLSearchParams({
        save_file: targetPath,
        file_content: content
    });
    
    const res = await fetch('', {method: 'POST', body});
    const data = await res.json();
    
    if (data.status === 'ok') {
        closeModal('editorModal');
        toast('✅ File saved successfully', 'success');
        if (currentFolder !== null) {
            loadFiles();
        } else {
            refreshFolderGrid();
        }
    } else {
        toast('❌ Failed to save file: ' + (data.msg || ''), 'error');
    }
}

/* ========================================================
   UPLOAD
======================================================== */
const uploadZone = document.getElementById('uploadZone');
const fileInput  = document.getElementById('fileInput');

uploadZone.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', () => {
    if (fileInput.files.length) uploadFiles(fileInput.files);
});

/* ── Robust drag & drop using enter/leave counter to prevent child-element flicker ── */
let dragCounter = 0;

document.addEventListener('dragover', e => e.preventDefault());

uploadZone.addEventListener('dragenter', e => {
    e.preventDefault();
    dragCounter++;
    uploadZone.classList.add('drag-over');
});

uploadZone.addEventListener('dragleave', e => {
    dragCounter--;
    if (dragCounter <= 0) {
        dragCounter = 0;
        uploadZone.classList.remove('drag-over');
    }
});

uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    dragCounter = 0;
    uploadZone.classList.remove('drag-over');
    if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
});

function uploadFiles(fileList) {
    // Convert FileList → mutable array so we can filter it
    let files = [...fileList];
    // Client-side size guard (500 MB) — fast feedback before even sending
    const MAX_BYTES = 500 * 1024 * 1024;
    const tooBig = files.filter(f => f.size > MAX_BYTES);
    if (tooBig.length) {
        tooBig.forEach(f => toast(`❌ ${f.name}: file too large (max 500 MB)`, 'error'));
        const ok = [...files].filter(f => f.size <= MAX_BYTES);
        if (!ok.length) return;
        files = ok; // upload only the ones that fit
    }

    const container = document.getElementById('progressContainer');
    const bar       = document.getElementById('progressBar');
    const label     = document.getElementById('progressLabel');
    const pct       = document.getElementById('progressPct');

    container.style.display = 'block';
    bar.style.width = '0%';
    label.textContent = `Uploading ${files.length} file${files.length>1?'s':''}…`;
    pct.textContent = '0%';

    const fd = new FormData();
    [...files].forEach(f => fd.append('files[]', f));
    if (currentFolder) {
        fd.append('parent_folder', currentFolder);
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '');

    xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) {
            const p = Math.round(e.loaded / e.total * 100);
            bar.style.width = p + '%';
            pct.textContent = p + '%';
            label.textContent = `Uploading… ${p}%`;
        }
    });

    xhr.addEventListener('load', async () => {
        bar.style.width = '100%';
        pct.textContent = '100%';
        setTimeout(() => { container.style.display = 'none'; }, 800);

        let data;
        try { data = JSON.parse(xhr.responseText); }
        catch(e) {
            toast('❌ Server error — could not parse response', 'error');
            fileInput.value = '';
            return;
        }

        // Show success toast for uploaded files
        if (data.files && data.files.length) {
            toast(`☁️ ${data.files.length} file${data.files.length>1?'s':''} uploaded to ${data.folder}`, 'success');
        }

        // Show individual error toasts for each failed file
        if (data.errors && data.errors.length) {
            data.errors.forEach(err => toast(`❌ ${err}`, 'error'));
        }

        // Navigate to / refresh the target folder if anything succeeded
        if (data.files && data.files.length) {
            await refreshFolderGrid();
            openFolder(data.folder);
        }

        fileInput.value = '';
    });

    xhr.addEventListener('error', () => {
        container.style.display = 'none';
        toast('❌ Network error during upload', 'error');
        fileInput.value = '';
    });

    xhr.send(fd);
}

/* ========================================================
   FOLDER GRID — dynamic refresh
======================================================== */
async function refreshFolderGrid() {
    const res  = await fetch('?ajax_folders');
    const data = await res.json();
    renderFolderGrid(data.folders, data.root_count);

    // Update header counters
    const totalF = data.folders.length + 1;
    const totalFi = data.folders.reduce((s, f) => s + f.count, 0) + data.root_count;
    document.getElementById('totalFolders').textContent = totalF;
    document.getElementById('totalFiles').textContent   = totalFi;
}

function renderFolderGrid(folders, rootCount) {
    const grid = document.getElementById('folderGrid');
    
    const rootHtml = `
        <div class="folder-card" onclick="openFolder('')">
            <div class="folder-icon-wrap" style="background: linear-gradient(135deg, rgba(16,185,129,.15), rgba(52,211,153,.15));">🏠</div>
            <div class="folder-name">Root Directory</div>
            <div class="folder-meta" style="display:flex;align-items:center;">
                ${rootCount} file${rootCount !== 1 ? 's' : ''}
                <span class="folder-count-badge" style="margin-left:8px; color: #10b981; background: rgba(16,185,129,.15);">${rootCount}</span>
            </div>
        </div>
    `;
    
    const foldersHtml = folders.map(fd => `
        <div class="folder-card" onclick="openFolder('${escHtml(fd.name)}')">
            <div class="folder-icon-wrap">📁</div>
            <div class="folder-name">${escHtml(fd.name)}</div>
            <div class="folder-meta" style="display:flex;align-items:center;">
                ${fd.count} file${fd.count !== 1 ? 's' : ''}
                <span class="folder-count-badge" style="margin-left:8px">${fd.count}</span>
            </div>
        </div>`).join('');
        
    grid.innerHTML = rootHtml + foldersHtml;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ========================================================
   MODAL HELPERS
======================================================== */
function showScanner() {
    const url = "<?= $mobileScannerUrl ?>";
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(url)}`;
    document.getElementById('qrImage').src = qrUrl;
    openModal('scannerModal');
}

function startCreateFolder() {
    document.getElementById('folderNameInput').value = '';
    openModal('createFolderModal');
    setTimeout(() => document.getElementById('folderNameInput').focus(), 250);
}

async function confirmCreateFolder() {
    const folderName = document.getElementById('folderNameInput').value.trim();
    if (!folderName) return toast('❌ Folder name is required', 'error');
    closeModal('createFolderModal');
    
    const body = new URLSearchParams({
        create_folder: folderName,
        parent_folder: currentFolder || ''
    });
    const res  = await fetch('', {method:'POST', body});
    const data = await res.json();
    if (data.status === 'ok') {
        toast('📁 Folder created successfully', 'success');
        if (currentFolder !== null) {
            loadFiles();
        } else {
            refreshFolderGrid();
        }
    } else {
        toast('❌ Failed to create folder: ' + (data.msg || ''), 'error');
    }
}

function openModal(id) {
    document.getElementById(id).classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

// Rename on Enter
document.getElementById('renameInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') confirmRename();
    if (e.key === 'Escape') closeModal('renameModal');
});

// Create folder on Enter
document.getElementById('folderNameInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') confirmCreateFolder();
    if (e.key === 'Escape') closeModal('createFolderModal');
});

/* ========================================================
   TOAST
======================================================== */
function toast(msg, type = 'info') {
    const container = document.getElementById('toastContainer');
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    const icons = { success: '✅', error: '❌', info: 'ℹ️' };
    el.innerHTML = `<span class="toast-icon">${icons[type]||'ℹ️'}</span><span>${msg}</span>`;
    container.appendChild(el);
    setTimeout(() => {
        el.classList.add('out');
        setTimeout(() => el.remove(), 350);
    }, 3500);
}

/* ========================================================
   TOTAL FILES COUNTER on load
======================================================== */
const totalFiles = <?= array_sum(array_column($folderData, 'count')) ?>;
document.getElementById('totalFiles').textContent = totalFiles || '0';
</script>
</body>
</html>