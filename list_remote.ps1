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
    Write-Host "Connecting..." -ForegroundColor Yellow
    $session.Open($sessionOptions)
    Write-Host "Connected!" -ForegroundColor Green

    $remotePath = "/home/sudhacreative/public_html/"
    
    Write-Host "Searching recursively for inspect_prod_invoices2.php under $remotePath..." -ForegroundColor Cyan
    $files = $session.EnumerateRemoteFiles($remotePath, "*inspect_prod_invoices2.php", [WinSCP.EnumerationOptions]::AllDirectories)
    foreach ($file in $files) {
        Write-Host "Found: $($file.FullName) - Size: $($file.Length) bytes" -ForegroundColor Green
    }
    Write-Host "Search completed." -ForegroundColor Cyan

} catch {
    Write-Host ("Error: " + $_.Exception.Message) -ForegroundColor Red
} finally {
    $session.Dispose()
}
