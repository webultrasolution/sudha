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
    Write-Host "Connecting..." -ForegroundColor Yellow
    $session.Open($sessionOptions)
    Write-Host "Connected!" -ForegroundColor Green

    $remotePath = "/home/u511039083/domains/webultrasolution.io/public_html/sudha/"
    
    # List all files in root
    Write-Host "`n=== ROOT FILES ON SERVER ===" -ForegroundColor Cyan
    $dir = $session.ListDirectory($remotePath)
    foreach ($file in $dir.Files | Sort-Object Name) {
        $size = if ($file.IsDirectory) { "[DIR]" } else { "$([math]::Round($file.Length/1KB,1)) KB" }
        Write-Host ("  " + $file.Name + " -- " + $size)
    }

} catch {
    Write-Host ("Error: " + $_.Exception.Message) -ForegroundColor Red
} finally {
    $session.Dispose()
}
