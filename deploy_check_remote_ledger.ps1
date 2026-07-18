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
    $transferOptions.ResumeSupport.State = [WinSCP.TransferResumeSupportState]::Off

    # Upload
    $session.PutFiles("c:\x--ampp\htdocs\sudha\check_remote_ledger.php", "/home/sudhacreative/public_html/check_remote_ledger.php", $False, $transferOptions).Check()
    
    # Request
    $response = Invoke-WebRequest -Uri "https://sudhacreative.com/check_remote_ledger.php" -UseBasicParsing
    Write-Host "Output from server:"
    Write-Host $response.Content
    
    # Delete from remote
    $session.RemoveFiles("/home/sudhacreative/public_html/check_remote_ledger.php").Check()
} catch {
    Write-Host ("Error: " + $_.Exception.Message) -ForegroundColor Red
} finally {
    $session.Dispose()
}
