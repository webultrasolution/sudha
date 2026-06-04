Add-Type -Path 'C:\Users\Lenovo\AppData\Local\Temp\winscp\WinSCPnet.dll'

$sessionOptions = New-Object WinSCP.SessionOptions -Property @{
    Protocol = [WinSCP.Protocol]::Sftp
    HostName = '145.79.209.12'
    PortNumber = 65002
    UserName = 'u511039083'
    Password = 'M2Noida@278'
    GiveUpSecurityAndAcceptAnySshHostKey = $true
}

$session = New-Object WinSCP.Session
$session.ExecutablePath = 'C:\Users\Lenovo\AppData\Local\Temp\winscp\WinSCP.exe'

try {
    Write-Host "Connecting to server..." -ForegroundColor Yellow
    $session.Open($sessionOptions)
    Write-Host "Connected successfully!" -ForegroundColor Green

    $transferOptions = New-Object WinSCP.TransferOptions
    $transferOptions.TransferMode = [WinSCP.TransferMode]::Automatic

    $remotePath = "/home/u511039083/domains/webultrasolution.io/public_html/sudha/"
    $localPath  = "c:\x--ampp\htdocs\sudha\"

    Write-Host "Downloading files from $remotePath ..." -ForegroundColor Yellow
    $result = $session.GetFilesToDirectory($remotePath, $localPath, "*", $False, $transferOptions)
    $result.Check()

    Write-Host ("Download complete! Total files: " + $result.Transfers.Count) -ForegroundColor Green
    foreach ($transfer in $result.Transfers) {
        Write-Host ("  + " + $transfer.FileName) -ForegroundColor Cyan
    }
} catch {
    Write-Host ("Error: " + $_.Exception.Message) -ForegroundColor Red
} finally {
    $session.Dispose()
    Write-Host "Session closed." -ForegroundColor Gray
}
