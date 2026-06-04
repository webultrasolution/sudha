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

    $transferOptions = New-Object WinSCP.TransferOptions
    $transferOptions.TransferMode = [WinSCP.TransferMode]::Automatic
    $transferOptions.ResumeSupport.State = [WinSCP.TransferResumeSupportState]::Off

    $remoteRoot = "/home/u511039083/domains/webultrasolution.io/public_html/sudha/"
    $localRoot  = "c:\x--ampp\htdocs\sudha\"

    $filesToUpload = @(
        "ajax/approve_entity.php",
        "update_approved_rates.php"
    )

    foreach ($file in $filesToUpload) {
        $localFile = Join-Path $localRoot $file
        $remoteFile = $remoteRoot + $file.Replace('\', '/')
        
        Write-Host "Uploading $file ..." -ForegroundColor Yellow
        $result = $session.PutFiles($localFile, $remoteFile, $False, $transferOptions)
        $result.Check()
        Write-Host "Uploaded $file!" -ForegroundColor Green
    }

} catch {
    Write-Host ("Error during upload: " + $_.Exception.Message) -ForegroundColor Red
} finally {
    $session.Dispose()
}
