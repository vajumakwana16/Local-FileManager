<?php
$baseDir = __DIR__ . "/uploads";
if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);

/* ================= UPLOAD ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $today = date('Y-m-d');
    $dir = "$baseDir/$today";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
        if ($_FILES['files']['error'][$i] === 0) {
            move_uploaded_file($tmp, "$dir/" . basename($_FILES['files']['name'][$i]));
        }
    }
    echo json_encode(["status" => "ok"]);
    exit;
}

/* ================= DELETE ================= */
if (isset($_POST['delete'])) {
    $file = realpath($baseDir . "/" . $_POST['delete']);
    if ($file && strpos($file, realpath($baseDir)) === 0 && file_exists($file)) {
        unlink($file);
    }
    exit;
}

/* ================= RENAME ================= */
if (isset($_POST['rename_old'], $_POST['rename_new'])) {
    $old = realpath($baseDir . "/" . $_POST['rename_old']);
    if ($old && strpos($old, realpath($baseDir)) === 0) {
        $new = dirname($old) . "/" . basename($_POST['rename_new']);
        rename($old, $new);
    }
    exit;
}

/* ================= DATA ================= */
$folders = array_filter(glob("$baseDir/*"), 'is_dir');
rsort($folders); // newest first

$active = $_GET['folder'] ?? null;
$files = [];

if ($active && is_dir("$baseDir/$active")) {
    $files = array_diff(scandir("$baseDir/$active"), ['.', '..']);
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>File Manager</title>

    <style>
        body {
            background: #0b1220;
            color: #e5e7eb;
            font-family: Inter, system-ui;
            padding: 20px;
        }

        .upload {
            border: 2px dashed #3b82f6;
            padding: 22px;
            text-align: center;
            border-radius: 14px;
            cursor: pointer;
        }

        .folder-grid,
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
            margin-top: 22px;
        }

        .folder,
        .file {
            background: #111827;
            padding: 14px;
            border-radius: 12px;
            transition: .2s;
        }

        .folder:hover,
        .file:hover {
            background: #1f2937;
        }

        .file img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }

        .file-name {
            font-size: 13px;
            margin-top: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .actions {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }

        button {
            font-size: 12px;
            padding: 5px 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-blue {
            background: #2563eb;
            color: white;
        }

        .btn-red {
            background: #dc2626;
            color: white;
        }

        .search {
            margin-top: 18px;
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: none;
        }
    </style>
</head>

<body>

    <h2>📁 File Manager</h2>

    <div class="upload" onclick="document.getElementById('file').click()">
        Click or Drop Files to Upload
        <input type="file" id="file" hidden multiple>
    </div>

    <?php if ($active): ?>
        <input type="text" id="search" class="search" placeholder="Search files...">
    <?php endif; ?>

    <?php if (!$active): ?>
        <div class="folder-grid">
            <?php foreach ($folders as $f): ?>
                <div class="folder" onclick="location.href='?folder=<?= basename($f) ?>'">
                    📁 <?= basename($f) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <button onclick="location.href='index.php'">⬅ Back</button>

        <div class="file-grid" id="fileGrid">
            <?php foreach ($files as $f): ?>
                <div class="file" data-name="<?= strtolower($f) ?>">
                    <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)): ?>
                        <img src="uploads/<?= $active ?>/<?= $f ?>">
                    <?php else: ?>
                        📄
                    <?php endif; ?>
                    <div class="file-name"><?= htmlspecialchars($f) ?></div>
                    <div class="actions">
                        <button class="btn-blue" onclick="renameFile('<?= $active ?>/<?= $f ?>','<?= $f ?>')">Rename</button>
                        <button class="btn-red" onclick="deleteFile('<?= $active ?>/<?= $f ?>')">Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Rename Modal -->
    <div id="renameModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);align-items:center;justify-content:center;">
        <div style="background:#0f172a;padding:20px;border-radius:10px;width:300px;">
            <h3>Rename File</h3>
            <input id="newName" style="width:100%;padding:8px">
            <br><br>
            <button class="btn-blue" onclick="confirmRename()">Save</button>
            <button onclick="closeRename()">Cancel</button>
        </div>
    </div>

    <script>
        const input = document.getElementById("file");
        let renamePath = "";

        input.onchange = () => {
            const fd = new FormData();
            [...input.files].forEach(f => fd.append("files[]", f));
            fetch("", {
                method: "POST",
                body: fd
            }).then(() => location.reload());
        };

        function deleteFile(path) {
            if (!confirm("Delete this file?")) return;
            fetch("", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: "delete=" + encodeURIComponent(path)
            }).then(() => location.reload());
        }

        function renameFile(path, name) {
            renamePath = path;
            document.getElementById("newName").value = name;
            document.getElementById("renameModal").style.display = "flex";
        }

        function confirmRename() {
            fetch("", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: "rename_old=" + encodeURIComponent(renamePath) + "&rename_new=" + encodeURIComponent(document.getElementById("newName").value)
            }).then(() => location.reload());
        }

        function closeRename() {
            document.getElementById("renameModal").style.display = "none";
        }

        /* ===== FIXED SEARCH ===== */
        const search = document.getElementById("search");
        if (search) {
            search.addEventListener("input", () => {
                const q = search.value.toLowerCase();
                document.querySelectorAll(".file").forEach(f => {
                    f.style.display = f.dataset.name.includes(q) ? "" : "none";
                });
            });
        }
    </script>

</body>

</html>