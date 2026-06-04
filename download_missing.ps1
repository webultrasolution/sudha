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
    Write-Host "Connected!" -ForegroundColor Green

    $remotePath = "/home/u511039083/domains/webultrasolution.io/public_html/sudha/"
    $localPath  = "c:\x--ampp\htdocs\sudha\"
    $transferOptions = New-Object WinSCP.TransferOptions
    $transferOptions.TransferMode = [WinSCP.TransferMode]::Automatic
    $transferOptions.ResumeSupport.State = [WinSCP.TransferResumeSupportState]::Off

    # Download specific missing/key files
    $files = @("index.php", "php_error.log", "logout.php", "autologin.php", "alter_live_db.php", 
               "live_update.sql", "view_errors.php", "test.php", "test3.php", "test7.php",
               "test10.php", "test11.php", "test12.php", "mig.php", "tmp_alter.php", "tmp_desc.php",
               "replace.php", "replace2.php", "check_db.php", "check_invoices_table.php")

    foreach ($file in $files) {
        $remoteFile = $remotePath + $file
        $localFile  = $localPath + $file
        try {
            $result = $session.GetFiles($remoteFile, $localFile, $False, $transferOptions)
            $result.Check()
            Write-Host ("Downloaded: " + $file) -ForegroundColor Green
        } catch {
            Write-Host ("Skipped: " + $file + " -- " + $_.Exception.Message) -ForegroundColor Yellow
        }
    }
    
    Write-Host "`nAll key files downloaded!" -ForegroundColor Cyan

} catch {
    Write-Host ("Error: " + $_.Exception.Message) -ForegroundColor Red
} finally {
    $session.Dispose()
}
