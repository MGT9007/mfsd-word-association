<?php
/**
 * Word Association Installation Checker
 * 
 * Upload this file to your wp-content/plugins/mfsd-word-association/ folder
 * Then visit: your-site.com/wp-content/plugins/mfsd-word-association/check-install.php
 */

$plugin_dir = __DIR__;
$plugin_url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['REQUEST_URI']);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Word Association Plugin Checker</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        td, th { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #667eea; color: white; }
    </style>
</head>
<body>
    <h1>🔍 Word Association Plugin Installation Checker</h1>
    
    <div class="info">
        <strong>Plugin Directory:</strong> <?php echo $plugin_dir; ?><br>
        <strong>Plugin URL:</strong> <?php echo $plugin_url; ?>
    </div>

    <h2>File Check</h2>
    <table>
        <tr>
            <th>File</th>
            <th>Status</th>
            <th>Path</th>
        </tr>
        <?php
        $files = array(
            'Main PHP' => 'mfsd-word-association.php',
            'CSS File' => 'assets/mfsd-word-association.css',
            'JS File' => 'assets/mfsd-word-association.js',
            'Assets Folder' => 'assets/'
        );
        
        foreach ($files as $name => $file) {
            $path = $plugin_dir . '/' . $file;
            $exists = file_exists($path);
            $is_dir = is_dir($path);
            
            echo '<tr>';
            echo '<td>' . $name . '</td>';
            echo '<td class="' . ($exists ? 'success' : 'error') . '">';
            echo $exists ? '✓ Found' : '✗ Missing';
            if ($exists && $is_dir) echo ' (folder)';
            echo '</td>';
            echo '<td>' . $path . '</td>';
            echo '</tr>';
        }
        ?>
    </table>

    <?php if (is_dir($plugin_dir . '/assets')): ?>
        <h2>Assets Folder Contents</h2>
        <ul>
            <?php
            $assets = scandir($plugin_dir . '/assets');
            foreach ($assets as $file) {
                if ($file != '.' && $file != '..') {
                    echo '<li>' . $file;
                    $size = filesize($plugin_dir . '/assets/' . $file);
                    echo ' <em>(' . number_format($size) . ' bytes)</em>';
                    echo '</li>';
                }
            }
            ?>
        </ul>
    <?php endif; ?>

    <h2>Expected File Sizes</h2>
    <ul>
        <li>mfsd-word-association.php: ~18-20 KB</li>
        <li>mfsd-word-association.css: ~11 KB</li>
        <li>mfsd-word-association.js: ~14 KB</li>
    </ul>

    <h2>What to Do if Files Are Missing</h2>
    <div class="info">
        <p><strong>If CSS or JS files are missing:</strong></p>
        <ol>
            <li>Check that you uploaded the entire <code>mfsd-word-association</code> folder</li>
            <li>Verify the <code>assets</code> folder exists inside the plugin folder</li>
            <li>Re-upload the plugin via FTP or WordPress admin</li>
            <li>Make sure file permissions are correct (644 for files, 755 for folders)</li>
        </ol>
        
        <p><strong>Correct structure should be:</strong></p>
        <pre>
wp-content/
  plugins/
    mfsd-word-association/
      mfsd-word-association.php
      README.md
      assets/
        mfsd-word-association.css
        mfsd-word-association.js
        </pre>
    </div>

    <h2>🔧 Quick Fix</h2>
    <p>If files are missing, download the fresh ZIP file and:</p>
    <ol>
        <li>Deactivate the plugin in WordPress</li>
        <li>Delete the <code>mfsd-word-association</code> folder from plugins</li>
        <li>Re-upload the ZIP via WordPress Admin → Plugins → Add New → Upload</li>
        <li>Activate the plugin</li>
    </ol>

    <hr>
    <p><em>After fixing, visit your Word Association page with <code>?debug_wa=1</code> added to the URL to see detailed debug info.</em></p>
</body>
</html>