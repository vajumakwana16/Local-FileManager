<?php
// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['files'])) {
    $uploadDate = date('Y-m-d');
    $uploadDir = __DIR__ . "/uploads/" . $uploadDate;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
        if ($_FILES['files']['error'][$index] === UPLOAD_ERR_OK) {
            $fileName = basename($_FILES['files']['name'][$index]);
            move_uploaded_file($tmpName, $uploadDir . "/" . $fileName);
        }
    }
    echo json_encode(["status" => "success"]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modern File Uploader</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f4f6f8;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
    }
    .upload-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        padding: 20px;
        max-width: 500px;
        width: 100%;
        text-align: center;
    }
    h2 {
        margin-bottom: 10px;
    }
    .drop-zone {
        border: 2px dashed #4a90e2;
        padding: 30px;
        border-radius: 10px;
        background: #f9fbfd;
        cursor: pointer;
        transition: 0.3s;
    }
    .drop-zone.dragover {
        background: #e0f0ff;
        border-color: #007bff;
    }
    input[type="file"] {
        display: none;
    }
    .file-list {
        margin-top: 15px;
        text-align: left;
    }
    .file-list div {
        font-size: 14px;
        margin: 5px 0;
        background: #f1f1f1;
        padding: 6px;
        border-radius: 5px;
    }
    button {
        margin-top: 15px;
        padding: 10px 15px;
        background: #4a90e2;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    button:hover {
        background: #357abd;
    }
    @media (max-width: 600px) {
        .upload-container {
            padding: 15px;
        }
        .drop-zone {
            padding: 20px;
        }
    }
</style>
</head>
<body>

<div class="upload-container">
    <h2>Upload Files</h2>
    <div class="drop-zone" id="dropZone">Drag & Drop files here or click to select</div>
    <input type="file" id="fileInput" name="files[]" multiple>
    <div class="file-list" id="fileList"></div>
    <button id="uploadBtn">Upload</button>
</div>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileList = document.getElementById('fileList');
const uploadBtn = document.getElementById('uploadBtn');
let filesToUpload = [];

dropZone.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', (e) => {
    filesToUpload = [...filesToUpload, ...e.target.files];
    displayFiles();
});

dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('dragover');
});
dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('dragover');
});
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    filesToUpload = [...filesToUpload, ...e.dataTransfer.files];
    displayFiles();
});

function displayFiles() {
    fileList.innerHTML = '';
    filesToUpload.forEach(file => {
        const div = document.createElement('div');
        div.textContent = file.name;
        fileList.appendChild(div);
    });
}

uploadBtn.addEventListener('click', () => {
    if (filesToUpload.length === 0) {
        alert("Please select files first.");
        return;
    }
    let formData = new FormData();
    filesToUpload.forEach(file => formData.append('files[]', file));

    fetch('', {
        method: 'POST',
        body: formData
    }).then(res => res.json()).then(data => {
        if (data.status === 'success') {
            alert('Files uploaded successfully!');
            filesToUpload = [];
            fileList.innerHTML = '';
        }
    }).catch(err => console.error(err));
});
</script>

</body>
</html>
