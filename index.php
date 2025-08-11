<?php
// Base uploads folder
$baseDir = __DIR__ . "/uploads/";

// Create base uploads folder if it doesn't exist
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}

// Create folder with today's date (YYYY-MM-DD)
$dateFolder = date("Y-m-d");
$targetDir = $baseDir . $dateFolder . "/";

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$messages = [];
$alertClass = "";

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $totalFiles = count($_FILES['files']['name']);

    for ($i = 0; $i < $totalFiles; $i++) {
        $fileName = basename($_FILES['files']['name'][$i]);
        $targetFile = $targetDir . $fileName;

        // If file exists, add unique suffix
        if (file_exists($targetFile)) {
            $fileInfo = pathinfo($fileName);
            $fileName = $fileInfo['filename'] . "_" . time() . "." . $fileInfo['extension'];
            $targetFile = $targetDir . $fileName;
        }

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $targetFile)) {
            $messages[] = "✅ Uploaded: " . htmlspecialchars($dateFolder . "/" . $fileName);
        } else {
            $messages[] = "❌ Failed: " . htmlspecialchars($fileName);
        }
    }

    $alertClass = "alert-info";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Multi File Upload</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #6e8efb, #a777e3);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .upload-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
        }
        .upload-btn {
            background: linear-gradient(45deg, #6e8efb, #a777e3);
            border: none;
            color: white;
            transition: 0.3s;
        }
        .upload-btn:hover {
            opacity: 0.85;
        }
        .file-input {
            padding: 10px;
        }
    </style>
</head>
<body>

<div class="upload-card">
    <h3 class="text-center mb-4">📂 Upload Your Files</h3>

    <?php if (!empty($messages)): ?>
        <div class="alert <?= $alertClass ?>">
            <?php foreach ($messages as $msg): ?>
                <div><?= $msg ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <input type="file" class="form-control file-input" name="files[]" multiple required>
        </div>
        <button type="submit" class="btn upload-btn w-100">Upload Files</button>
    </form>
</div>

</body>
</html>
