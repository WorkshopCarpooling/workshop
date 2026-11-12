$ErrorActionPreference = 'Stop'
$appointmentProject = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$appointmentPhpConsole = (Get-Command php.exe -ErrorAction Stop).Source
$appointmentPhp = Join-Path (Split-Path -Parent $appointmentPhpConsole) 'php-win.exe'
if (!(Test-Path -LiteralPath $appointmentPhp)) {throw 'The PHP windowless runtime (php-win.exe) is required for this background task.'}
$appointmentTaskName = 'RideUTeM Workshop Appointment Dispatcher'
$appointmentUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$appointmentArguments = '"' + (Join-Path $appointmentProject 'artisan') + '" rides:dispatch-appointments'
$existingAppointmentTask = Get-ScheduledTask -TaskName $appointmentTaskName -ErrorAction SilentlyContinue
if ($existingAppointmentTask) {
    if ($existingAppointmentTask.Actions.Arguments -notcontains $appointmentArguments) {
        throw 'A task with this name already exists for a different project or PHP configuration.'
    }
    $appointmentBackup = Join-Path $appointmentProject 'storage\app\appointment-task-before.xml'
    Export-ScheduledTask -TaskName $appointmentTaskName | Set-Content -LiteralPath $appointmentBackup -Encoding utf8
}
$appointmentAction = New-ScheduledTaskAction -Execute $appointmentPhp -Argument $appointmentArguments -WorkingDirectory $appointmentProject
$appointmentTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$appointmentSettings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 1)
$appointmentPrincipal = New-ScheduledTaskPrincipal -UserId $appointmentUser -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $appointmentTaskName -Action $appointmentAction -Trigger $appointmentTrigger -Settings $appointmentSettings -Principal $appointmentPrincipal -Description 'Release saved RideUTeM ride appointments at their booked time, once per minute while this local Windows account is signed in.' -Force | Out-Null
Start-ScheduledTask -TaskName $appointmentTaskName
Write-Output "Installed $appointmentTaskName for $appointmentProject."
