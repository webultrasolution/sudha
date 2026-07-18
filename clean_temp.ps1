Add-Type -Path 'C:\Users\Lenovo\AppData\Local\Temp\winscp\WinSCPnet.dll'
$sessionOptions = New-Object WinSCP.SessionOptions -Property @{
    Protocol = [WinSCP.Protocol]::Sftp
    HostName = 'sudhacreative.com'
    PortNumber = 22
    UserName = 'root'
    Password = 'M2Noida@847226'
    GiveUpSecurityAndAcceptAnySshHostKey = $true
}
$session = New-Object WinSCP.Session
$session.ExecutablePath = 'C:\Users\Lenovo\AppData\Local\Temp\winscp\WinSCP.exe'
try {
    $session.Open($sessionOptions)
    if ($session.FileExists("/home/sudhacreative/public_html/temp_reset.php")) {
        $session.RemoveFiles("/home/sudhacreative/public_html/temp_reset.php")
        Write-Host "Removed temp_reset.php from production server."
    }
    if ($session.FileExists("/home/sudhacreative/public_html/temp_reset_v2.php")) {
        $session.RemoveFiles("/home/sudhacreative/public_html/temp_reset_v2.php")
        Write-Host "Removed temp_reset_v2.php from production server."
    }
} finally {
    $session.Dispose()
}
