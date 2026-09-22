#!/usr/bin/env pwsh
param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Args)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$php = 'C:\wamp64\bin\php\php8.3.6\php.exe'
$ini = Join-Path $root 'php.ini'
$composer = "$env:LOCALAPPDATA\ComposerSetup\bin\composer.phar"

if (-not (Test-Path $php)) { throw "No se encuentra PHP 8.3.6 en $php" }

if ($Args.Count -eq 0) {
    Write-Host "Uso: ./dev.ps1 <comando> [args]"
    Write-Host ""
    Write-Host "  artisan <...>    php artisan con PHP 8.3 + php.ini del proyecto"
    Write-Host "  composer <...>   composer con PHP 8.3"
    Write-Host "  test             suite de Pest"
    Write-Host "  php <...>        php directo"
    Write-Host "  up / down        arranca o para PostgreSQL"
    Write-Host "  psql             consola de PostgreSQL"
    exit 0
}

$cmd = $Args[0]
$rest = if ($Args.Count -gt 1) { $Args[1..($Args.Count - 1)] } else { @() }

switch ($cmd) {
    'artisan'  { & $php -c $ini (Join-Path $root 'artisan') @rest }
    'composer' { & $php -c $ini $composer @rest }
    'test'     { & $php -c $ini (Join-Path $root 'vendor\bin\pest') @rest }
    'php'      { & $php -c $ini @rest }
    'up'       { docker compose -f (Join-Path $root 'docker-compose.yml') up -d }
    'down'     { docker compose -f (Join-Path $root 'docker-compose.yml') down }
    'psql'     { docker exec -it turnzero-db psql -U turnzero -d turnzero @rest }
    default    { & $php -c $ini (Join-Path $root 'artisan') @Args }
}

exit $LASTEXITCODE
