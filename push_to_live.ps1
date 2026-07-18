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
    Write-Host "Connected successfully to production SFTP server!" -ForegroundColor Green

    $transferOptions = New-Object WinSCP.TransferOptions
    $transferOptions.TransferMode = [WinSCP.TransferMode]::Automatic
    $transferOptions.ResumeSupport.State = [WinSCP.TransferResumeSupportState]::Off

    if (-not $session.FileExists("/home/sudhacreative/public_html/templates")) {
        $session.CreateDirectory("/home/sudhacreative/public_html/templates")
        Write-Host "Created remote templates directory!" -ForegroundColor Green
    }

    $remoteRoot = "/home/sudhacreative/public_html/"
    $localRoot  = "c:\x--ampp\htdocs\sudha\"

    $filesToUpload = @(
        "modules/partners/client_printing_rates.php",
        "modules/partners/printing_rates.php",
        "modules/operations/create_mounting_po.php",
        "modules/operations/mounting.php",
        "ajax/upload_printing_po.php",
        "modules/operations/generate_printing_po.php",
        "modules/operations/view_booking.php",
        "dashboard.php",
        "modules/financials/invoices.php",
        "modules/financials/invoice_view.php",
        "modules/financials/po_view.php",
        "modules/inventory/site_financials.php",
        "modules/financials/payments.php",
        "modules/financials/ledgers.php",
        "ajax/save_payment.php",
        "ajax/update_booking_item_period.php",
        "modules/operations/bookings.php",
        "modules/proposals/proposals.php",
        "modules/proposals/view.php",
        "modules/proposals/export_pdf.php",
        "modules/proposals/export_quotation.php",
        "modules/proposals/export_excel.php",
        "ajax/update_proposal_item.php",
        "ajax/delete_proposal_item.php",
        "ajax/save_booking_po.php",
        "ajax/save_direct_po.php",
        "ajax/upload_customer_po.php",
        "modules/operations/generate_invoice.php",
        "modules/operations/generate_ro_invoice.php",
        "includes/header.php",
        "includes/functions.php",
        "login.php",
        "forgot_password.php",
        "modules/users/index.php",
        "modules/users/profile.php",
        "modules/partners/clients.php",
        "modules/partners/vendors.php",
        "modules/inventory/sites.php",
        "ajax/import_inventory.php",
        "templates/inventory_template.csv",
        "assets/css/style.css",
        "autologin.php",
        "config/db.php"
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
