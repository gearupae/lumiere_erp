<?php

defined('BASEPATH') or exit('No direct script access allowed');
$dimensions = $pdf->getPageDimensions();

$pdf_logo_url = pdf_logo_url();
$pdf->writeHTMLCell(($dimensions['wk'] - ($dimensions['rm'] + $dimensions['lm'])), '', '', '', $pdf_logo_url, 0, 1, false, true, 'L', true);

$pdf->ln(4);
// Get Y position for the separation
$y = $pdf->getY();

$proposal_info = '<div style="color:#424242;">';
$proposal_info .= format_organization_info();
$proposal_info .= '</div>';

$pdf->writeHTMLCell(($swap == '0' ? (($dimensions['wk'] / 2) - $dimensions['rm']) : ''), '', '', ($swap == '0' ? $y : ''), $proposal_info, 0, 0, false, true, ($swap == '1' ? 'R' : 'J'), true);

$rowcount = max([$pdf->getNumLines($proposal_info, 80)]);

// Proposal to
$client_details = '<b>' . _l('proposal_to') . '</b>';
$client_details .= '<div style="color:#424242;">';
$client_details .= format_proposal_info($proposal, 'pdf');
$client_details .= '</div>';

$pdf->writeHTMLCell(($dimensions['wk'] / 2) - $dimensions['lm'], $rowcount * 7, '', ($swap == '1' ? $y : ''), $client_details, 0, 1, false, true, ($swap == '1' ? 'J' : 'R'), true);

$pdf->ln(6);

$proposal_date = _l('proposal_date') . ': ' . _d($proposal->date);
$open_till     = '';

if (!empty($proposal->open_till)) {
    $open_till = _l('proposal_open_till') . ': ' . _d($proposal->open_till) . '<br />';
}
$prepared_by ='';
if (!empty($proposal->prepared_by)) {
    $prepared_by = _l('Prepared by') . ': ' . _d($proposal->prepared_by) . '<br />';
}
$project ='';
if (!empty($proposal->project)) {
    $project = _l('Project') . ': ' . _d($proposal->project) . '<br />';
}
$c_phone ='';
if (!empty($proposal->c_phone)) {
    $c_phone = _l('Phone') . ': ' . _l($proposal->c_phone) . '<br />';
}
$assigned ='';
if (!empty($proposal->full_name)) {
    $assigned = _l('Assigned') . ': ' . ($proposal->full_name) . '<br />';
}

$project = '';
if ($proposal->project_id != '' && get_option('show_project_on_proposal') == 1) {
    $project .= _l('project') . ': ' . get_project_name_by_id($proposal->project_id) . '<br />';
}

$qty_heading = _l('estimate_table_quantity_heading', '', false);

if ($proposal->show_quantity_as == 2) {
    $qty_heading = _l($this->type . '_table_hours_heading', '', false);
} elseif ($proposal->show_quantity_as == 3) {
    $qty_heading = _l('estimate_table_quantity_heading', '', false) . '/' . _l('estimate_table_hours_heading', '', false);
}

// Group the items by 'group_name'
$groupedItems = array_reduce($proposal->items, function ($result, $item) {
    $groupName = $item['group_name'];

    if (!isset($result[$groupName])) {
        $result[$groupName] = array();
    }

    $result[$groupName][] = $item;

    return $result;
}, array());

$items_html = '<br /><br />';
$items_html .= '';

$items_html .= '
<table width="100%" bgcolor="#fff" cellspacing="0" cellpadding="8">
   <thead>
      <tr height="30" bgcolor="#14a4e9" style="color:#ffffff;">
         <th width="5%;" align="center">#</th>
         <th width="53%" align="left">Item</th>
         <th width="14%" align="right">Qty</th>';
if($proposal->show_rate == 1){
    $items_html .= '<th width="14%" align="right">Rate</th>';
}else{
    $items_html .='<th width="14%" align="right"></th>';
}
$items_html .=' <th width="14%" align="right">Amount</th>
      </tr>
   </thead><tbody>';
   $total = 0;
   $i=0;
   foreach ($groupedItems  as $key => $items) {
    if(($key == null)){
        $key = "Others";
    }
    $items_html .= ' <tr height="30" cellpadding="6" bgcolor="#43b3e9" style="color:#ffffff;">
        <td align="center" width="10%"></td>
        <td colspan="5" align="left" width="90%"><strong>'.$key.'</strong></td>
    </tr>
    ';

    $sub_total = 0;

    foreach($items as $item){
    $i++;
    $items_html .= '<tr style="font-size:14px;">
            <td align="center" width="5%">'.$i.'</td>
            <td class="description" align="left;" width="53%"><span style="font-size:14px;"><strong>'.$item['description'].'</strong></span><br><span style="color:#424242;">'.$item['long_description'].'</span></td>
            <td align="right" width="14%">'.$item['qty'].' '.$item['unit'].'</td>';
    if($proposal->show_rate == 1){
        $items_html .='<td align="right" width="14%">'.app_format_money($item['rate'],$proposal->currency_name).'</td>';
        $items_html .='<td class="amount" align="right" width="14%">'.app_format_money(($item['qty'] * $item['rate']),$proposal->currency_name).'</td>';
    }else{
        $items_html .='<td align="right" width="14%"></td>';
        $items_html .='<td align="right" width="14%">--</td>';
    }
    $items_html .= '</tr>';
        $sub_total += $item['qty'] * $item['rate'];

    }
    $items_html .= '<table cellpadding="6" style="font-size:14px"><tbody>
        <tr style="background-color:#f0f0f0;">
            <td align="right" width="85%"><strong> Total '.$key.' </strong></td>
            <td align="right" width="15%">'.app_format_money($sub_total,$proposal->currency_name).'</td>
        </tr></tbody></table>';
    $total += $sub_total;

}
$items_html .= '</tbody>

</table><br /><br /><br />';

$items_html .= '<table cellpadding="6" style="font-size:14px"><tbody>';
    if (is_sale_discount_applied($proposal)) {
        $items_html .= '
        <tr>
            <td align="right" width="85%"><strong>' . _l('estimate_discount');
        if (is_sale_discount($proposal, 'percent')) {
            $items_html .= ' (' . app_format_number($proposal->discount_percent, true) . '%)';
        }
        $items_html .= '</strong>';
        $items_html .= '</td>';
        $items_html .= '<td align="right" width="15%">-' . app_format_money($proposal->discount_total, $proposal->currency_name) . '</td>
        </tr>';
    }
    $itemss = get_items_table_data($proposal, 'proposal', 'pdf')
        ->set_headings('estimate');
    foreach ($itemss->taxes() as $tax) {
        $items_html .= '<tr>
        <td align="right" width="85%"><strong>' . $tax['taxname'] . ' (' . app_format_number($tax['taxrate']) . '%)' . '</strong></td>
        <td align="right" width="15%">' . app_format_money($tax['total_tax'], $proposal->currency_name) . '</td>
    </tr>';
    }
    
    if ((int)$proposal->adjustment != 0) {
        $items_html .= '<tr>
        <td align="right" width="85%"><strong>' . _l('estimate_adjustment') . '</strong></td>
        <td align="right" width="15%">' . app_format_money($proposal->adjustment, $proposal->currency_name) . '</td>
    </tr>';
    }
    $items_html .= '
    <tr style="background-color:#f0f0f0;">
        <td align="right" width="85%"><strong>' . _l('estimate_subtotal') . '</strong></td>
        <td align="right" width="15%">' . app_format_money($proposal->total, $proposal->currency_name) . '</td>
    </tr>';
    $items_html .= '</table>';
    
    if (get_option('total_to_words_enabled') == 1) {
        $items_html .= '<br /><br /><br />';
        $items_html .= '<strong style="text-align:center;">' . _l('num_word') . ': ' . strtoupper($CI->numberword->convert($proposal->total, $proposal->currency_name)) . '</strong>';
    }

$items_html .= '<br /><br /><br />';

$items_html .= '<br /> <h4>Special Notes </h4>'.$proposal->clientnote;
$items_html .= '<br /> <h4>Terms and Conditions </h4>'.$proposal->terms;

$proposal->content = str_replace('{proposal_items}', $items_html, $proposal->content);

// Get the proposals css
// Theese lines should aways at the end of the document left side. Dont indent these lines
$html = <<<EOF
<p style="font-size:20px;"># $number
<br /><span style="font-size:15px;">$proposal->subject</span>
</p>
$proposal_date
<br />
$open_till
$project
$prepared_by
$project
$c_phone
$assigned
<div style="width:675px !important;">
$proposal->content
</div>
EOF;

// var_dump($html, true, false, true, false, '');
$pdf->writeHTML($html, true, false, true, false, '');
