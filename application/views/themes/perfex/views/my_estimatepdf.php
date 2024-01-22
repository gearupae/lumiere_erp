<?php

defined('BASEPATH') or exit('No direct script access allowed');

$dimensions = $pdf->getPageDimensions();

$info_right_column = '';
$info_left_column  = '';

$info_right_column .= '<span style="font-weight:bold;font-size:27px;">' . _l('estimate_pdf_heading') . '</span><br />';
$info_right_column .= '<b style="color:#4e4e4e;"># ' . $estimate_number . '</b>';

if (get_option('show_status_on_pdf_ei') == 1) {
    $info_right_column .= '<br /><span style="color:rgb(' . estimate_status_color_pdf($status) . ');text-transform:uppercase;">' . format_estimate_status($status, '', false) . '</span>';
}

// Add logo
$info_left_column .= pdf_logo_url();
// Write top left logo and right column info/text
pdf_multi_row($info_left_column, $info_right_column, $pdf, ($dimensions['wk'] / 2) - $dimensions['lm']);

$pdf->ln(10);

$organization_info = '<div style="color:#424242;">';
    $organization_info .= format_organization_info();
$organization_info .= '</div>';

// Estimate to
$estimate_info = '<b>' . _l('estimate_to') . '</b>';
$estimate_info .= '<div style="color:#424242;">';
$estimate_info .= format_customer_info($estimate, 'estimate', 'billing');
$estimate_info .= '</div>';

// ship to to
if ($estimate->include_shipping == 1 && $estimate->show_shipping_on_estimate == 1) {
    $estimate_info .= '<br /><b>' . _l('ship_to') . '</b>';
    $estimate_info .= '<div style="color:#424242;">';
    $estimate_info .= format_customer_info($estimate, 'estimate', 'shipping');
    $estimate_info .= '</div>';
}

$estimate_info .= '<br />' . _l('estimate_data_date') . ': ' . _d($estimate->date) . '<br />';

if (!empty($estimate->expirydate)) {
    $estimate_info .= _l('estimate_data_expiry_date') . ': ' . _d($estimate->expirydate) . '<br />';
}

if (!empty($estimate->reference_no)) {
    $estimate_info .= _l('reference_no') . ': ' . $estimate->reference_no . '<br />';
}

if ($estimate->sale_agent && get_option('show_sale_agent_on_estimates') == 1) {
    $estimate_info .= _l('sale_agent_string') . ': ' . get_staff_full_name($estimate->sale_agent) . '<br />';
}
if (!empty($estimate->prepared_by)) {
    $estimate_info .= _l('Prepared by') . ': ' . _l($estimate->prepared_by) . '<br />';
}
if (!empty($estimate->project)) {
    $estimate_info .= _l('Project') . ': ' . _l($estimate->project) . '<br />';
}
if (!empty($estimate->c_phone)) {
    $estimate_info .= _l('Phone') . ': ' . _l($estimate->c_phone) . '<br />';
}
if ($estimate->project_id && get_option('show_project_on_estimate') == 1) {
    $estimate_info .= _l('project') . ': ' . get_project_name_by_id($estimate->project_id) . '<br />';
}

foreach ($pdf_custom_fields as $field) {
    $value = get_custom_field_value($estimate->id, $field['id'], 'estimate');
    if ($value == '') {
        continue;
    }
    $estimate_info .= $field['name'] . ': ' . $value . '<br />';
}

$left_info  = $swap == '1' ? $estimate_info : $organization_info;
$right_info = $swap == '1' ? $organization_info : $estimate_info;

pdf_multi_row($left_info, $right_info, $pdf, ($dimensions['wk'] / 2) - $dimensions['lm']);

// The Table
$pdf->Ln(hooks()->apply_filters('pdf_info_and_table_separator', 6));

// The items table
$itemsdata = get_items_table_data($estimate, 'estimate', 'pdf');
$taxes = $itemsdata->taxes();
// $tblhtml = $items->table();
// Group the items by 'group_name'

$groupedItems = array_reduce($estimate->items, function ($result, $item) {
    $groupName = $item['group_name'];

    if (!isset($result[$groupName])) {
        $result[$groupName] = array();
    }

    $result[$groupName][] = $item;

    return $result;
}, array());

// $items_html .= '<html>
// <head></head>
// <body data-new-gr-c-s-check-loaded="14.1147.0" data-gr-ext-installed="">';

$items_html = ' 
<table  width="100%" bgcolor="#fff" cellspacing="0" cellpadding="8">
   <thead>
      <tr height="30" bgcolor="#14a4e9" style="color:#ffffff;">
         <th width="5%;" align="center">#</th>
         <th width="53%" align="left">Item</th>
         <th width="14%" align="right">Qty</th>';
if($estimate->show_rate == 1){
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
    if($estimate->show_rate == 1){
        $items_html .='<td align="right" width="14%">'.app_format_money($item['rate'],$estimate->currency_name).'</td>';
        $items_html .='<td class="amount" align="right" width="14%">'.app_format_money(($item['qty'] * $item['rate']),$estimate->currency_name).'</td>';
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
    <td align="right" width="15%">'.app_format_money($sub_total,$estimate->currency_name).'</td>
</tr></tbody></table>';
    $total += $sub_total;

}
$items_html .= '</tbody>
</table>
';



$pdf->writeHTML($items_html, true, false, false, false, '');

$pdf->Ln(8);

$tbltotal = '';
$tbltotal .= '<table cellpadding="6" style="font-size:' . ($font_size + 4) . 'px">';
$tbltotal .= '
<tr>
    <td align="right" width="85%"><strong>' . _l('estimate_subtotal') . '</strong></td>
    <td align="right" width="15%">' . app_format_money($estimate->subtotal, $estimate->currency_name) . '</td>
</tr>';

if (is_sale_discount_applied($estimate)) {
    $tbltotal .= '
    <tr>
        <td align="right" width="85%"><strong>' . _l('estimate_discount');
    // if (is_sale_discount($estimate, 'percent')) {
    //     $tbltotal .= ' (' . app_format_number($estimate->discount_percent, true) . '%)';
    // }
    $tbltotal .= '</strong>';
    $tbltotal .= '</td>';
    $tbltotal .= '<td align="right" width="15%">-' . app_format_money($estimate->discount_total, $estimate->currency_name) . '</td>
    </tr>';
     //for total before tax
  $tbltotal .= '<tr> <td align="right" width="85%"><strong> Total</strong></td>';
  $tbltotal.= ' <td align="right" width="15%">' . app_format_money($estimate->subtotal-$estimate->discount_total, $estimate->currency_name) . '</td></tr>';
}


foreach ($taxes as $tax) {
    $tbltotal .= '<tr>
    <td align="right" width="85%"><strong>' . $tax['taxname'] . ' (' . app_format_number($tax['taxrate']) . '%)' . '</strong></td>
    <td align="right" width="15%">' . app_format_money($tax['total_tax'], $estimate->currency_name) . '</td>
</tr>';
}

if ((int)$estimate->adjustment != 0) {
    $tbltotal .= '<tr>
    <td align="right" width="85%"><strong>' . _l('estimate_adjustment') . '</strong></td>
    <td align="right" width="15%">' . app_format_money($estimate->adjustment, $estimate->currency_name) . '</td>
</tr>';
}

$tbltotal .= '
<tr style="background-color:#f0f0f0;">
    <td align="right" width="85%"><strong>' . _l('estimate_total') . '</strong></td>
    <td align="right" width="15%">' . app_format_money($estimate->total, $estimate->currency_name) . '</td>
</tr>';

$tbltotal .= '</table>';

$pdf->writeHTML($tbltotal, true, false, false, false, '');

if (get_option('total_to_words_enabled') == 1) {
    // Set the font bold
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->writeHTMLCell('', '', '', '', _l('num_word') . ': ' . strtoupper($CI->numberword->convert($estimate->total, $estimate->currency_name)), 0, 1, false, true, 'C', true);
    // Set the font again to normal like the rest of the pdf
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(4);
}

if (!empty($estimate->clientnote)) {
    $pdf->Ln(4);
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->Cell(0, 0, _l('estimate_note'), 0, 1, 'L', 0, '', 0);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(2);
    $pdf->writeHTMLCell('', '', '', '', $estimate->clientnote, 0, 1, false, true, 'L', true);
}

if (!empty($estimate->terms)) {
    $pdf->Ln(4);
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->Cell(0, 0, _l('terms_and_conditions') . ":", 0, 1, 'L', 0, '', 0);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(2);
    $pdf->writeHTMLCell('', '', '', '', $estimate->terms, 0, 1, false, true, 'L', true);
}