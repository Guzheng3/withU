param(
    [string]$TaskName = 'WithU OpenList Incremental Sync',
    [string]$PhpPath = 'C:\WithU\tools\php82\php.exe',
    [string]$PhpIni = 'C:\WithU\dev\php.ini',
    [string]$ProjectRoot = 'C:\WithU\withU',
    [int]$IntervalMinutes = 15
)
$scriptPath = Join-Path $ProjectRoot 'scripts\sync_openlist_media.php'
$action = New-ScheduledTaskAction -Execute $PhpPath -Argument "-c `"$PhpIni`" `"$scriptPath`"" -WorkingDirectory $ProjectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes $IntervalMinutes)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Force | Out-Null
Write-Output "已注册定时任务：$TaskName，每 $IntervalMinutes 分钟执行一次。"
