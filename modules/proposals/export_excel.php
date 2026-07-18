<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT p.*, 
    c.name as client_name, c.address as client_address, c.city as client_city, c.gstin as client_gstin, c.contact_person
    FROM proposals p
    JOIN partners c ON p.client_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$proposal = $stmt->fetch();

if (!$proposal) die("Proposal not found.");

$items = $pdo->prepare("
    SELECT pi.*, s.site_code, s.name as site_name, s.location, s.city as site_city, s.area, s.state, s.district, s.type as site_type, s.width, s.height, s.latitude, s.longitude, s.light_type, s.sqft
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    WHERE pi.proposal_id = ?
");
$items->execute([$id]);
$items = $items->fetchAll();

$filename = "MediaPlan_" . str_replace(' ', '_', $proposal['campaign_name']) . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";

$company = resolveCompanyDetails();
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>Sudha Creative</Author>
  <Created><?php echo date('Y-m-d\TH:i:s\Z'); ?></Created>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Borders/>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="HeaderMain">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="12" ss:Bold="1"/>
   <Interior ss:Color="#FFFF00" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="HeaderCol">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1"/>
   <Interior ss:Color="#FFFF00" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="Cell">
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
  </Style>
  <Style ss:ID="CellCenter">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
  </Style>
  <Style ss:ID="CellRight">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
   <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="CellBold">
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1"/>
  </Style>
  <Style ss:ID="CellBoldRight">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1"/>
  </Style>
  <Style ss:ID="CellBoldRightRed">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="11" ss:Bold="1" ss:Color="#FF0000"/>
  </Style>
  <Style ss:ID="Terms">
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="9" ss:Color="#333333"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Media Plan">
  <Table>
   <Column ss:Width="30"/>
   <Column ss:Width="80"/>
   <Column ss:Width="80"/>
   <Column ss:Width="80"/>
   <Column ss:Width="100"/>
   <Column ss:Width="150"/>
   <Column ss:Width="70"/>
   <Column ss:Width="70"/>
   <Column ss:Width="90"/>
   <Column ss:Width="40"/>
   <Column ss:Width="70"/>
   <Column ss:Width="80"/>
   <Column ss:Width="60"/>
   <Column ss:Width="80"/>
   <Column ss:Width="80"/>
   <Column ss:Width="50"/>
   <Column ss:Width="95"/>

   <Row ss:Height="25">
    <Cell ss:MergeAcross="16" ss:StyleID="HeaderMain"><Data ss:Type="String">Proposal - <?php echo htmlspecialchars($proposal['campaign_name']); ?> (<?php 
    if (!empty($proposal['start_date']) && $proposal['start_date'] !== '0000-00-00' && !empty($proposal['end_date']) && $proposal['end_date'] !== '0000-00-00') {
        echo date('d M Y', strtotime($proposal['start_date'])) . ' To ' . date('d M Y', strtotime($proposal['end_date']));
    } else {
        echo 'N/A';
    }
    ?>)</Data></Cell>
   </Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>

   <!-- Company Info -->
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="CellBold"><Data ss:Type="String">From : <?php echo htmlspecialchars($company['name']); ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="16"><Data ss:Type="String">Address : <?php echo str_replace("\n", ", ", htmlspecialchars($company['address'])); ?></Data></Cell>
   </Row>
   <?php if (!empty($company['phone'])): ?>
   <Row>
    <Cell ss:MergeAcross="16"><Data ss:Type="String">Phone : <?php echo htmlspecialchars($company['phone']); ?></Data></Cell>
   </Row>
   <?php endif; ?>
   <?php if (!empty($company['email'])): ?>
   <Row>
    <Cell ss:MergeAcross="16"><Data ss:Type="String">Email : <?php echo htmlspecialchars($company['email']); ?></Data></Cell>
   </Row>
   <?php endif; ?>

   <!-- Client Info -->
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="CellBold"><Data ss:Type="String">To : <?php echo strtoupper(htmlspecialchars($proposal['client_name'])); ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="16"><Data ss:Type="String">Contact Person : <?php echo htmlspecialchars($proposal['contact_person'] ?: 'Concerned Person'); ?></Data></Cell>
   </Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>

   <!-- Table Header -->
   <Row ss:Height="20">
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Sr.</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">State</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">District</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">City</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Area</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Site name</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Latitude</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Longitude</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Media Type</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Qty.</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Size (WxH)</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Total Area(Sq Ft.)</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Lit</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">From Date</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">To Date</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Days</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Monthly Rental</Data></Cell>
   </Row>

   <!-- Data Rows -->
   <?php 
   $sn = 1; 
   foreach($items as $item): 
       $lit = 'Non Lit';
       if ($item['light_type'] == 'BL') $lit = 'Back Lit';
       if ($item['light_type'] == 'FL') $lit = 'Front Lit';
   ?>
   <Row>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="Number"><?php echo $sn++; ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?php echo htmlspecialchars($item['state'] ?: 'West Bengal'); ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?php echo htmlspecialchars($item['district'] ?? ''); ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?php echo htmlspecialchars($item['site_city'] ?? ''); ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?php echo htmlspecialchars($item['area'] ?: $item['location']); ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?php echo htmlspecialchars($item['site_name']); ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?php echo htmlspecialchars($item['latitude'] ?: 'N/A'); ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?php echo htmlspecialchars($item['longitude'] ?: 'N/A'); ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?php echo htmlspecialchars($item['site_type']); ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="Number">1</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?php echo (int)$item['width']; ?> X <?php echo (int)$item['height']; ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="Number"><?php echo (int)$item['sqft']; ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?php echo $lit; ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?php echo (!empty($proposal['start_date']) && $proposal['start_date'] !== '0000-00-00') ? date('d M Y', strtotime($proposal['start_date'])) : 'N/A'; ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?php echo (!empty($proposal['end_date']) && $proposal['end_date'] !== '0000-00-00') ? date('d M Y', strtotime($proposal['end_date'])) : 'N/A'; ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="Number"><?php echo $item['days']; ?></Data></Cell>
    <Cell ss:StyleID="CellRight"><Data ss:Type="Number"><?php echo $item['sale_rate']; ?></Data></Cell>
   </Row>
   <?php endforeach; ?>

   <!-- Totals -->
   <Row>
    <Cell ss:Index="16" ss:StyleID="CellBoldRight"><Data ss:Type="String">Subtotal</Data></Cell>
    <Cell ss:StyleID="CellRight"><Data ss:Type="Number"><?php echo $proposal['total_amount']; ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:Index="16" ss:StyleID="CellBoldRight"><Data ss:Type="String">GST (18%)</Data></Cell>
    <Cell ss:StyleID="CellRight"><Data ss:Type="Number"><?php echo $proposal['tax_amount']; ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:Index="16" ss:StyleID="CellBoldRight"><Data ss:Type="String">Grand Total</Data></Cell>
    <Cell ss:StyleID="CellBoldRightRed"><Data ss:Type="Number"><?php echo $proposal['grand_total']; ?></Data></Cell>
   </Row>

   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>

   <!-- Terms & Conditions -->
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="CellBold"><Data ss:Type="String">Terms &amp; Conditions:</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="Terms"><Data ss:Type="String">1) All media are subject to availability at the time of booking confirmation.</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="Terms"><Data ss:Type="String">2) The agency will provide a Proof of Execution Report and a Closure Report, including newspaper clippings or GPS-stamped images.</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="Terms"><Data ss:Type="String">3) Printing costs are subject to change based on the final material specifications and requirements.</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="Terms"><Data ss:Type="String">4) As per government regulations, all advertising/display content must include at least 60% regional language.</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="Terms"><Data ss:Type="String">5) Cancellations must be made with at least 7 days' prior notice via written email communication.</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="Terms"><Data ss:Type="String">6) The agency will not be held responsible for any loss or damage to the display caused by vandalism, theft, natural calamities, or any other events beyond our control.</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="Terms"><Data ss:Type="String">7) The display of offensive, obscene, or inappropriate content is strictly prohibited. This includes material that promotes hatred, discrimination, violence, or explicit content.</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="16" ss:StyleID="Terms"><Data ss:Type="String">8) The above estimate is valid for 7 days from the date of issuance.</Data></Cell>
   </Row>
  </Table>
 </Worksheet>
</Workbook>
