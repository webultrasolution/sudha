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
    $transferOptions = New-Object WinSCP.TransferOptions
    $transferOptions.TransferMode = [WinSCP.TransferMode]::Automatic
    $session.PutFiles("c:\x--ampp\htdocs\sudha\temp_reset_v2.php", "/home/sudhacreative/public_html/temp_reset_v2.php", $False, $transferOptions).Check()
    Write-Host "Uploaded temp_reset_v2.php successfully!"
} finally {
    $session.Dispose()
}
