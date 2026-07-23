<?php
require dirname(__DIR__).'/config.php';
requireAdmin();
if(!empty($_SESSION['must_change_password'])){header('Location: password.php');exit;}

$rows=db()->query("SELECT r.raffle_number,p.name,p.whatsapp,p.tiktok_account,p.token,p.correct_count,p.reviewed_at
    FROM raffle_numbers r
    JOIN participants p ON p.id=r.participant_id
    ORDER BY r.raffle_number")->fetchAll();

function xmlCell(string $value, string $style='Text'): string {
    return '<Cell ss:StyleID="'.$style.'"><Data ss:Type="String">'.htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8').'</Data></Cell>';
}

$filename='nomor-undian-'.date('Ymd-His').'.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11"/></Style>
  <Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#D9EAD3" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
  <Style ss:ID="Text"><NumberFormat ss:Format="@"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/></Borders></Style>
 </Styles>
 <Worksheet ss:Name="Nomor Undian">
  <Table>
   <Column ss:Width="40"/><Column ss:Width="145"/><Column ss:Width="170"/><Column ss:Width="125"/><Column ss:Width="125"/><Column ss:Width="120"/><Column ss:Width="95"/><Column ss:Width="130"/>
   <Row>
    <?=xmlCell('No.','Header')?><?=xmlCell('Nomor Undian','Header')?><?=xmlCell('Nama Pemilik','Header')?><?=xmlCell('Nomor WhatsApp','Header')?><?=xmlCell('Akun / Username TikTok','Header')?><?=xmlCell('Token Peserta','Header')?><?=xmlCell('Jumlah Jawaban Benar','Header')?><?=xmlCell('Tanggal Koreksi','Header')?>
   </Row>
   <?php foreach($rows as $i=>$row):?>
   <Row>
    <?=xmlCell((string)($i+1))?>
    <?=xmlCell((string)$row['raffle_number'])?>
    <?=xmlCell((string)$row['name'])?>
    <?=xmlCell((string)$row['whatsapp'])?>
    <?=xmlCell((string)$row['tiktok_account'])?>
    <?=xmlCell((string)$row['token'])?>
    <?=xmlCell((string)$row['correct_count'])?>
    <?=xmlCell((string)$row['reviewed_at'])?>
   </Row>
   <?php endforeach;?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>1</SplitHorizontal><TopRowBottomPane>1</TopRowBottomPane><Selected/></WorksheetOptions>
  <AutoFilter x:Range="R1C1:R<?=max(1,count($rows)+1)?>C8" xmlns="urn:schemas-microsoft-com:office:excel"/>
 </Worksheet>
</Workbook>
