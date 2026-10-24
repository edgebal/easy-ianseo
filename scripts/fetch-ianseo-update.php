<?php
// Replicates IANSEO's "Modules => Update Ianseo" (Update/UpdateIanseo.php) outside of IANSEO:
// builds the same file manifest, POSTs it to www.ianseo.net/Update.php, and applies the returned delta.
// Usage: php fetch-ianseo-update.php <ianseo-dir>

$Base = rtrim($argv[1] ?? '', '/');
if (!is_dir($Base)) { fwrite(STDERR, "usage: php {$argv[0]} <ianseo-dir>\n"); exit(1); }

// version info is read from the installation itself, as IANSEO's own defines
preg_match('/define\s*\("ProgramVersion","([^"]+)"/', file_get_contents("$Base/Common/Globals.inc.php"), $m); $ProgVersion = $m[1];
preg_match('/define\("IanseoLicenseCode", "([^"]+)"/', file_get_contents("$Base/Common/Globals.inc.php"), $m); $License = $m[1];
preg_match("/define\('ProgramRelease', '([^']+)'/", file_get_contents("$Base/Common/distro.inc.php"), $m); $ProgRelease = $m[1];
preg_match("/define\('ProgramBuild', '([^']+)'/", file_get_contents("$Base/Common/distro.inc.php"), $m); $ProgBuild = $m[1];
preg_match("/newversion='([^']+)'/", file_get_contents("$Base/Common/UpdateDb-check.php"), $m); $DbVersion = $m[1];
echo "Local: $ProgVersion ($ProgRelease $ProgBuild), DB $DbVersion\n";

// same exclusions as Update/FileList.php
$Files = [];
$scan = function ($Folder) use (&$scan, &$Files, $Base) {
	$Dist = substr($Folder, strlen($Base));
	foreach (scandir($Folder) as $file) {
		if ($file == '.' || $file == '..' || preg_match('/^(\.)/i', $file)
			|| $Dist == '/Images'
			|| ($Dist == '/Common' && in_array($file, ['config.inc.php', 'DebugOverrides.php']))
			|| ($Dist == '/TV' && $file == 'Photos')
			|| ($Dist == '/Install' && $file == 'dbdumps')
			|| ($Dist == '/Modules' && $file == 'Custom')
			|| ($Dist == '/Common/Languages' && is_dir("$Folder/$file"))) continue;
		$path = "$Folder/$file";
		if (is_dir($path)) $scan($path);
		elseif (is_file($path)) $Files[substr("$Dist/$file", 1)] = ['s' => filesize($path), 'm' => md5_file($path)];
	}
};
$scan($Base);
ksort($Files);
echo count($Files) . " files in manifest\n";

$Req = new stdClass();
$Req->ProgVersion = $ProgVersion;
$Req->ProgRelease = $ProgRelease;
$Req->ProgBuild = $ProgBuild;
$Req->UUID = '';
$Req->DbVersion = $DbVersion;
$Req->AcceptGPL = date('Y-m-d H:i:s');
$Req->License = $License;
$Req->Referer = 'localhost';
$Req->Files = $Files;

$ctx = stream_context_create(['http' => [
	'method' => 'POST',
	'header' => 'Content-type: application/x-www-form-urlencoded',
	'content' => http_build_query(['Json' => gzcompress(serialize($Req))], '', '&'),
	'timeout' => 600,
]]);
$raw = file_get_contents('https://www.ianseo.net/Update.php', false, $ctx);
if ($raw === false) { fwrite(STDERR, "request failed\n"); exit(1); }

$New = @unserialize(@gzuncompress($raw), ['allowed_classes' => ['stdClass']]);
if (!$New) { echo "Server said: " . substr($raw, 0, 500) . "\n"; exit($raw == 'NothingToDo' ? 0 : 1); }
$New = (array)$New;

foreach ($New['Files'] ?? [] as $file => $data) {
	if (str_contains($file, '..')) { fwrite(STDERR, "skipping suspicious path $file\n"); continue; }
	@mkdir(dirname("$Base/$file"), 0777, true);
	file_put_contents("$Base/$file", $data['f']);
}
foreach ($New['ToDelete'] ?? [] as $file) if (!str_contains($file, '..')) @unlink("$Base/$file");
$Dirs = $New['DirToDelete'] ?? []; rsort($Dirs);
foreach ($Dirs as $dir) if (!str_contains($dir, '..')) @rmdir("$Base/$dir");

echo count($New['Files'] ?? []) . " files updated, " . count($New['ToDelete'] ?? []) . " deleted, " . count($Dirs) . " dirs removed\n";
preg_match('/define\s*\("ProgramVersion","([^"]+)"/', file_get_contents("$Base/Common/Globals.inc.php"), $m);
echo "Now: {$m[1]}\n";
