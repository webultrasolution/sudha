Add-Type -Path 'C:\Users\Lenovo\AppData\Local\Temp\winscp\WinSCPnet.dll'

$sessionOptions = New-Object WinSCP.SessionOptions -Property @{
    Protocol = [WinSCP.Protocol]::Sftp
    HostName = 'sudhacreative.tech'
    PortNumber = 22
    UserName = 'sudhacreative'
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

    # Upload clear opcache script to remote public_html
    $session.PutFiles("c:\x--ampp\htdocs\sudha\scratch_clear_opcache.php", "/home/sudhacreative/public_html/scratch_clear_opcache.php", $False, $transferOptions).Check()
    
    # Trigger it via web request to clear OPcache
    $response = Invoke-WebRequest -Uri "https://sudhacreative.tech/scratch_clear_opcache.php" -UseBasicParsing
    Write-Host "OPcache reset output:"
    Write-Host $response.Content
    
    # Remove it from remote
    $session.RemoveFiles("/home/sudhacreative/public_html/scratch_clear_opcache.php").Check()
} catch {
    Write-Host ("Error: " + $_.Exception.Message) -ForegroundColor Red
} finally {
    $session.Dispose()
}
