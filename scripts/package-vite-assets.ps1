# Build and zip frontend assets for shared hosting (Hostinger etc.)
# Git ignores public/vite — upload the zip, then extract on the server under public/vite/

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

Write-Host "==> npm run build"
npm run build

if (-not (Test-Path "public/vite/manifest.json")) {
    Write-Error "Build failed: public/vite/manifest.json not found"
}

$outDir = Join-Path $root "storage/deploy"
$zip = Join-Path $outDir "vite-assets.zip"
New-Item -ItemType Directory -Force -Path $outDir | Out-Null
if (Test-Path $zip) { Remove-Item $zip -Force }

Compress-Archive -Path "public/vite/*" -DestinationPath $zip -Force

Write-Host "==> Created $zip"
Write-Host ""
Write-Host "Upload to Hostinger:"
Write-Host "  1. File Manager -> public_html/public/vite/"
Write-Host "  2. Upload vite-assets.zip and extract there"
Write-Host "  3. Confirm public_html/public/vite/manifest.json exists"
