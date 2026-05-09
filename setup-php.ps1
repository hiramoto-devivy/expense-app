$phpDir = "C:\Users\hira_\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe"
$iniFile = Join-Path $phpDir "php.ini"
$devFile = Join-Path $phpDir "php.ini-development"

Copy-Item $devFile $iniFile

$content = Get-Content $iniFile
$content = $content.Replace(";extension_dir = `"ext`"", "extension_dir = `"ext`"")
$content = $content.Replace(";extension=pdo_sqlite", "extension=pdo_sqlite")
$content = $content.Replace(";extension=sqlite3", "extension=sqlite3")
Set-Content $iniFile $content
