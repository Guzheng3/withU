param(
    [string]$QtRoot = $env:QT_ROOT,
    [string]$Configuration = 'Release',
    [string]$Generator = $(if ($env:WITHU_CMAKE_GENERATOR) { $env:WITHU_CMAKE_GENERATOR } else { 'Ninja' })
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($QtRoot)) {
    throw '请设置 QT_ROOT，示例：$env:QT_ROOT = "C:\Qt\6.8.0\msvc2022_64"'
}

$qtRootPath = (Resolve-Path -LiteralPath $QtRoot).Path
$cmakePrefix = $qtRootPath
$buildDir = Join-Path $PSScriptRoot 'build'
$distDir = Join-Path $PSScriptRoot 'dist-wmf'
$cmakeCommand = Get-Command cmake -ErrorAction SilentlyContinue
$cmakeExe = if ($cmakeCommand) { $cmakeCommand.Source } else { Join-Path $PSScriptRoot '..\..\..\tools\portable\cmake-3.31.6-windows-x86_64\bin\cmake.exe' }
if (!(Test-Path -LiteralPath $cmakeExe)) {
    throw "找不到 CMake：$cmakeExe"
}

$cmakeArgs = @('--fresh', '-S', $PSScriptRoot, '-B', $buildDir, '-G', $Generator, "-DCMAKE_PREFIX_PATH=$cmakePrefix")
if ($Generator -eq 'Ninja') {
    $ninjaPath = $env:CMAKE_MAKE_PROGRAM
    if ([string]::IsNullOrWhiteSpace($ninjaPath)) {
        $ninjaPath = Join-Path $PSScriptRoot '..\..\..\tools\portable\ninja\ninja.exe'
    }
    $ninjaPath = (Resolve-Path -LiteralPath $ninjaPath).Path
    $mingwBin = Join-Path $qtRootPath '..\..\Tools\mingw1310_64\bin'
    if (Test-Path -LiteralPath $mingwBin) {
        $env:Path = "$mingwBin;$env:Path"
        $cmakeArgs += "-DCMAKE_CXX_COMPILER=$(Join-Path $mingwBin 'g++.exe')"
    }
    $cmakeArgs += "-DCMAKE_MAKE_PROGRAM=$ninjaPath"
} else {
    $cmakeArgs += @('-A', 'x64')
}

& $cmakeExe @cmakeArgs
& $cmakeExe --build $buildDir --config $Configuration
& $cmakeExe --install $buildDir --config $Configuration --prefix $distDir

$windeployqt = Join-Path $qtRootPath 'bin\windeployqt.exe'
$binary = Join-Path $distDir 'withU Desktop.exe'
if ((Test-Path -LiteralPath $windeployqt) -and (Test-Path -LiteralPath $binary)) {
    & $windeployqt --release --no-translations --no-system-d3d-compiler --exclude-plugins ffmpegmediaplugin $binary
}

# Playback uses the in-process libmpv backend only. Remove any stale external
# mpv package left by older builds so the release cannot silently use it.
$distRoot = [System.IO.Path]::GetFullPath($distDir + [System.IO.Path]::DirectorySeparatorChar)
$staleMpvDir = [System.IO.Path]::GetFullPath((Join-Path $distDir 'mpv'))
if ($staleMpvDir.StartsWith($distRoot, [System.StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $staleMpvDir)) {
    Remove-Item -LiteralPath $staleMpvDir -Recurse -Force
    Write-Host "已清理旧外部 mpv 目录：$staleMpvDir"
}

# Bundle the in-process libmpv runtime for both VMware software fallback and
# physical-machine hardware acceleration paths.
$libMpvSource = Join-Path $PSScriptRoot 'third_party\libmpv\libmpv-2.dll'
if (Test-Path -LiteralPath $libMpvSource) {
    Copy-Item -LiteralPath $libMpvSource -Destination (Join-Path $distDir 'libmpv-2.dll') -Force
    Write-Host "已内置进程内 libmpv：$(Join-Path $distDir 'libmpv-2.dll')"
}

# WebView2 is an optional shell layer. Keep the runtime beside the desktop
# executable so a physical machine can use the same browser engine without a
# machine-wide loader lookup; the existing Qt UI remains the fallback.
$webView2LoaderSource = Join-Path $PSScriptRoot '..\..\..\tools\webview2-sdk\package\runtimes\win-x64\native\WebView2Loader.dll'
if (Test-Path -LiteralPath $webView2LoaderSource) {
    Copy-Item -LiteralPath $webView2LoaderSource -Destination (Join-Path $distDir 'WebView2Loader.dll') -Force
    Write-Host "已内置 WebView2 Loader：$(Join-Path $distDir 'WebView2Loader.dll')"
}

# Reuse the same web artwork in the desktop shell so the first screen keeps
# the site's logo, hero background and avatar fallback instead of a second UI identity.
$webImagesSource = Join-Path $PSScriptRoot '..\..\assets\images'
$webImagesTarget = Join-Path $distDir 'assets\images'
if (Test-Path -LiteralPath $webImagesSource) {
    New-Item -ItemType Directory -Path $webImagesTarget -Force | Out-Null
    Get-ChildItem -LiteralPath $webImagesSource -Force | Copy-Item -Destination $webImagesTarget -Recurse -Force
    Write-Host "已复用网页视觉资源：$webImagesTarget"
}

$legacyVlcDir = [System.IO.Path]::GetFullPath((Join-Path $distDir 'vlc'))
if ($legacyVlcDir.StartsWith($distRoot, [System.StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $legacyVlcDir)) {
    Remove-Item -LiteralPath $legacyVlcDir -Recurse -Force
    Write-Host "已清理旧 VLC 目录：$legacyVlcDir"
}

Write-Host "构建完成：$distDir"
