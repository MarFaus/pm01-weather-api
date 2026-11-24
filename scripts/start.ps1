# Сводка (погода на Open-Meteo): запуск одним кликом, вызывается из start.bat.
# Сам проверяет и при необходимости докачивает PHP 8.3, Composer, зависимости,
# создаёт .env и базу SQLite и запускает сайт. Совместим с Windows PowerShell 5.1.

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'
[Console]::OutputEncoding = [Text.Encoding]::UTF8
$OutputEncoding = [Text.Encoding]::UTF8

$Root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $Root
$Host.UI.RawUI.WindowTitle = 'Сводка: погода'

function Test-Ascii($path) { return -not ($path -match '[^\x00-\x7F]') }

# php.exe из папки с кириллицей ломает `artisan serve`, поэтому инструменты кладём в папку на латинице.
# Папка общая с прошлыми ЛР (kassa6379-tools): уже скачанный PHP не качается заново.
$Tools = Join-Path $Root '.tools'
if (-not (Test-Ascii $Tools)) { $Tools = Join-Path $env:PUBLIC 'kassa6379-tools' }
$Check = Join-Path $Root 'scripts\check.php'
$PhpUrl = 'https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip'
$ComposerUrl = 'https://getcomposer.org/download/latest-stable/composer.phar'
$VcRedistUrl = 'https://aka.ms/vs/17/release/vc_redist.x64.exe'

function Step($n, $text) { Write-Host "[$n/5] $text" -ForegroundColor Cyan }
function Info($text) { Write-Host "      $text" }
function Fail($text) {
    Write-Host ''
    Write-Host "  [ошибка] $text" -ForegroundColor Red
    Write-Host '  Запуск остановлен.'
    Write-Host ''
    if ($env:WEATHER_NONINTERACTIVE -ne '1') { Read-Host '  Нажмите Enter, чтобы закрыть окно' | Out-Null }
    exit 1
}

function Download($url, $out) {
    & curl.exe -fL --progress-bar -o $out $url
    if ($LASTEXITCODE -ne 0) { Fail "Не удалось скачать $url. Проверьте интернет или прокси и запустите start.bat ещё раз." }
}

function Expand($zip, $dir) {
    if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Path $dir | Out-Null }
    & tar.exe -xf $zip -C $dir
    if ($LASTEXITCODE -ne 0) { Expand-Archive -LiteralPath $zip -DestinationPath $dir -Force }
    Remove-Item -LiteralPath $zip -Force
}

function Test-Port($port) {
    $client = New-Object Net.Sockets.TcpClient
    try {
        $async = $client.BeginConnect('127.0.0.1', $port, $null, $null)
        return ($async.AsyncWaitHandle.WaitOne(500) -and $client.Connected)
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Test-Php($exe) {
    & $exe $Check php *> $null
    return ($LASTEXITCODE -eq 0)
}

function Artisan { & $script:PHP artisan @args }

Write-Host ''
Write-Host '  Сводка: прогноз погоды на Open-Meteo' -ForegroundColor White
Write-Host '  ------------------------------------------------------------'
Write-Host ''

if (-not (Get-Command curl.exe -ErrorAction SilentlyContinue)) {
    Fail 'В системе нет curl.exe. Нужна Windows 10 версии 1803 или новее.'
}
if (-not (Test-Path -LiteralPath $Tools)) { New-Item -ItemType Directory -Path $Tools | Out-Null }
if ($Tools -ne (Join-Path $Root '.tools')) { Info "В пути проекта есть кириллица, поэтому инструменты лежат в $Tools" }

# ------------------------------------------------------------------ PHP
Step 1 'PHP'
$PHP = $null
$LocalPhp = Join-Path $Tools 'php\php.exe'
if (Test-Path -LiteralPath $LocalPhp) {
    $PHP = $LocalPhp
} else {
    $found = Get-Command php.exe -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($found) { $PHP = $found.Source }
}

if ($PHP -and -not (Test-Ascii $PHP)) {
    Info "PHP лежит в папке с не латинскими буквами ($PHP), с ним не работает artisan serve. Беру переносной."
    $PHP = $null
}

if ($PHP -eq $LocalPhp -and -not (Test-Php $PHP)) {
    & $PHP $Check ini (Join-Path $Tools 'php\php.ini')
}

if ($PHP -and -not (Test-Php $PHP)) {
    Info "Найден $PHP, но он не подходит:"
    & $PHP $Check php
    $PHP = $null
}

if (-not $PHP) {
    Info 'Скачиваю переносной PHP 8.3, около 35 МБ...'
    $phpDir = Join-Path $Tools 'php'
    if (Test-Path -LiteralPath $phpDir) { Remove-Item -LiteralPath $phpDir -Recurse -Force }
    $zip = Join-Path $Tools 'php.zip'
    Download $PhpUrl $zip
    Expand $zip $phpDir
    Copy-Item -LiteralPath (Join-Path $phpDir 'php.ini-development') -Destination (Join-Path $phpDir 'php.ini') -Force
    $PHP = $LocalPhp

    & $PHP -v *> $null
    if ($LASTEXITCODE -ne 0) {
        Info 'PHP не стартует: нет Microsoft Visual C++ Runtime. Скачиваю установщик...'
        $vc = Join-Path $Tools 'vc_redist.x64.exe'
        Download $VcRedistUrl $vc
        Info 'Устанавливаю. Подтвердите окно UAC...'
        Start-Process -FilePath $vc -ArgumentList '/install', '/passive', '/norestart' -Verb RunAs -Wait
    }

    & $PHP $Check ini (Join-Path $phpDir 'php.ini')
    if (-not (Test-Php $PHP)) {
        & $PHP $Check php
        Fail 'Переносной PHP не прошёл проверку.'
    }
}
$phpVersion = & $PHP $Check php
Info "PHP ${phpVersion}: $PHP"

# ------------------------------------------------------------- Composer
Step 2 'Composer и зависимости'
# Системный composer не берём: он может быть привязан к другому PHP (например, 8.2 из XAMPP).
$ComposerPhar = Join-Path $Tools 'composer.phar'
if (-not (Test-Path -LiteralPath $ComposerPhar)) {
    Info 'Скачиваю composer.phar...'
    Download $ComposerUrl $ComposerPhar
}
if (-not (Test-Path -LiteralPath 'vendor\autoload.php')) { Info 'Первая установка пакетов, это займёт минуту...' }
& $PHP $ComposerPhar install --no-interaction --prefer-dist --no-progress
if ($LASTEXITCODE -ne 0) { Fail 'composer install завершился с ошибкой. Проверьте интернет и запустите start.bat ещё раз.' }
Info 'vendor в порядке'

# ----------------------------------------------------------------- .env
Step 3 'Настройки .env'
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
    Artisan key:generate --force --no-interaction | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail 'Не удалось сгенерировать APP_KEY.' }
    Info '.env создан из .env.example'
} else {
    Info '.env на месте'
}

# ----------------------------------------------------------------- База
Step 4 'База данных SQLite (история поиска и кеш)'
$db = 'database\database.sqlite'
if (-not (Test-Path -LiteralPath $db)) { New-Item -ItemType File -Path $db | Out-Null }
Artisan migrate --force --no-interaction | Out-Null
if ($LASTEXITCODE -ne 0) { Fail 'Миграции не прошли. Подробности покажет команда: php artisan migrate' }
Info 'миграции применены'

# --------------------------------------------------------------- Сервер
Step 5 'Запуск сайта'
Artisan config:clear | Out-Null

$port = 8000
while ((Test-Port $port) -and $port -lt 8100) { $port++ }
$url = "http://127.0.0.1:$port"

Write-Host ''
Write-Host '  ------------------------------------------------------------'
Write-Host "  Готово. Сайт: $url" -ForegroundColor Green
Write-Host "  JSON API:     $url/api/weather?city=Алматы"
Write-Host '  Нужен интернет: прогноз приходит с api.open-meteo.com.'
Write-Host '  Остановить: закройте это окно или нажмите Ctrl+C.'
Write-Host '  ------------------------------------------------------------'
Write-Host ''

if ($env:WEATHER_NONINTERACTIVE -ne '1') {
    Start-Job -ScriptBlock { param($u) Start-Sleep -Seconds 2; Start-Process $u } -ArgumentList $url | Out-Null
}
Artisan serve --host=127.0.0.1 --port=$port
