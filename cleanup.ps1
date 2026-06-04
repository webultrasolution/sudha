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
    $session.Open($sessionOptions)
    # Delete sensitive migration file
    $session.RemoveFiles("/home/u511039083/domains/webultrasolution.io/public_html/sudha/add_entity_cols.php")
    Write-Host "Deleted migration file from server." -ForegroundColor Green
} catch {
    Write-Host ("Error: " + $_.Exception.Message) -ForegroundColor Yellow
} finally {
    $session.Dispose()
}
