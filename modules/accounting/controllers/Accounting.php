<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Accounting extends AdminController
{
	public function __construct()
    {
        parent::__construct();
        $this->load->model('accounting_model');
        hooks()->do_action('accounting_init'); 
        if(get_option('acc_add_default_account') == 0){
            $this->accounting_model->add_default_account();
        }

        if(get_option('acc_add_default_account_new') == 0){
            $this->accounting_model->add_default_account_new();
        }
    }

    /**
     * manage transaction
     * @return view
     */
    public function transaction()
    {
        if (!has_permission('accounting_transaction', '', 'view')) {
            access_denied('transaction');
        }
        $data          = [];
        $this->load->model('currencies_model');
        $data['currency'] = $this->currencies_model->get_base_currency();

        $data['_status'] = '';
        if( $this->input->get('status')){
            $data['_status'] = [$this->input->get('status')];
        }
        $data['tab_2'] = $this->input->get('tab');
        

        $data['group'] = $this->input->get('group');
        //$data['tab'][] = 'banking';
        $data['tab'][] = 'sales';
        $data['tab'][] = 'expenses';
        if(acc_get_status_modules('hr_payroll')){
            $data['tab'][] = 'payslips';
        }

        if(acc_get_status_modules('purchase')){
            $data['tab'][] = 'purchase';
        }

        if(acc_get_status_modules('warehouse')){
            $data['tab'][] = 'warehouse';
        }
        
        if ($data['group'] == '') {
            $data['group'] = 'banking';
        }

        if($data['group'] == 'sales'){
            $this->load->model('payment_modes_model');
            $data['count_invoice'] = $this->accounting_model->count_invoice_not_convert_yet();
            $data['count_payment'] = $this->accounting_model->count_payment_not_convert_yet();
            $data['invoices'] = $this->accounting_model->get_data_invoices_for_select();
            $data['payment_modes'] = $this->payment_modes_model->get();

            if ($data['tab_2'] == '') {
                $data['tab_2'] = 'payment';
            }
        }elseif ($data['group'] == 'warehouse') {
            $data['count_stock_import'] = $this->accounting_model->count_stock_import_not_convert_yet();
            $data['count_stock_export'] = $this->accounting_model->count_stock_export_not_convert_yet();
            $data['count_loss_adjustment'] = $this->accounting_model->count_loss_adjustment_not_convert_yet();
            $data['count_opening_stock'] = $this->accounting_model->count_opening_stock_not_convert_yet();


            if ($data['tab_2'] == '') {
                $data['tab_2'] = 'stock_import';
            }
        }elseif ($data['group'] == 'purchase') {
            
            $data['count_purchase_order'] = $this->accounting_model->count_purchase_order_not_convert_yet();
            $data['count_purchase_payment'] = $this->accounting_model->count_purchase_payment_not_convert_yet();

            if ($data['tab_2'] == '') {
                $data['tab_2'] = 'purchase_order';
            }
        }
        
        $data['accounts'] = $this->accounting_model->get_accounts();
        $data['account_to_select'] = $this->accounting_model->get_data_account_to_select();
        $data['title']        = _l($data['group']);
        $data['tabs']['view'] = 'transaction/' . $data['group'];
        $this->load->view('transaction/manage', $data);
    }

    /**
     * sales table
     * @return json
     */
    public function sales_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1', // bulk actions
                db_prefix() . 'invoicepaymentrecords.id as id',
                'amount',
                'invoiceid',
                db_prefix() . 'payment_modes.name as name',
                db_prefix() .'invoicepaymentrecords.date as date',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoicepaymentrecords.id and ' . db_prefix() . 'acc_account_history.rel_type = "payment") as count_account_historys'
            ];
            $where = [];
            if ($this->input->post('invoice')) {
                $invoice = $this->input->post('invoice');
                array_push($where, 'AND invoiceid IN (' . implode(', ', $invoice) . ')');
            }

            if ($this->input->post('payment_mode')) {
                $payment_mode = $this->input->post('payment_mode');
                array_push($where, 'AND paymentmode IN (' . implode(', ', $payment_mode) . ')');
            }

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoicepaymentrecords.id and ' . db_prefix() . 'acc_account_history.rel_type = "payment") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoicepaymentrecords.id and ' . db_prefix() . 'acc_account_history.rel_type = "payment") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoicepaymentrecords.id and ' . db_prefix() . 'acc_account_history.rel_type = "payment") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoicepaymentrecords.id and ' . db_prefix() . 'acc_account_history.rel_type = "payment") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }

            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'invoicepaymentrecords.date >= "' . $from_date . '" and ' . db_prefix() . 'invoicepaymentrecords.date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'invoicepaymentrecords.date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'invoicepaymentrecords.date <= "' . $to_date . '")');
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'invoicepaymentrecords';
            $join         = ['LEFT JOIN ' . db_prefix() . 'payment_modes ON ' . db_prefix() . 'payment_modes.id = ' . db_prefix() . 'invoicepaymentrecords.paymentmode',
                            'LEFT JOIN ' . db_prefix() . 'acc_account_history ON ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoicepaymentrecords.id and ' . db_prefix() . 'acc_account_history.rel_id = "payment"',
                            'LEFT JOIN ' . db_prefix() . 'invoices ON ' . db_prefix() . 'invoices.id = ' . db_prefix() . 'invoicepaymentrecords.invoiceid',
                            'LEFT JOIN ' . db_prefix() . 'currencies ON ' . db_prefix() . 'currencies.id = ' . db_prefix() . 'invoices.currency'
                        ];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['paymentmode', db_prefix(). 'currencies.name as currency_name']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

                $categoryOutput = _d($aRow['date']);

                $categoryOutput .= '<div class="row-options">';
                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))) {
                        $categoryOutput .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="payment-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="payment" data-amount="'.$aRow['amount'].'">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $categoryOutput .= '<a href="#" onclick="convert(this); return false;" id="payment-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="payment" data-amount="'.$aRow['amount'].'">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $categoryOutput .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'payment\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }



                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;

                $row[] = app_format_money($aRow['amount'], $aRow['currency_name']);

                $row[] = $aRow['name'];
                $row[] = '<a href="' . admin_url('invoices/list_invoices/' . $aRow['invoiceid']) . '" target="_blank">' . format_invoice_number($aRow['invoiceid']) . '</a>';

                $status_name = _l('has_not_been_converted');
                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                } 

                $row[] = '<span class="label label-' . $label_class . ' s-status payment-status-' . $aRow['id'] . '">' . $status_name . '</span>';

                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-amount' => $aRow['amount'],
                        'data-type' => 'payment',
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * sales table
     * @return json
     */
    public function sales_invoice_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1', // bulk actions
                db_prefix() . 'invoices.id as id',
                'total',
                'clientid',
                'number',
                db_prefix() .'invoices.date as date',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoices.id and ' . db_prefix() . 'acc_account_history.rel_type = "invoice") as count_account_historys',
                db_prefix() . 'invoices.status'
            ];
            $where = [];
            if ($this->input->post('invoice')) {
                $invoice = $this->input->post('invoice');
                array_push($where, 'AND id IN (' . implode(', ', $invoice) . ')');
            }

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoices.id and ' . db_prefix() . 'acc_account_history.rel_type = "invoice") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoices.id and ' . db_prefix() . 'acc_account_history.rel_type = "invoice") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoices.id and ' . db_prefix() . 'acc_account_history.rel_type = "invoice") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoices.id and ' . db_prefix() . 'acc_account_history.rel_type = "invoice") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }

            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'invoices.date >= "' . $from_date . '" and ' . db_prefix() . 'invoices.date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'invoices.date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'invoices.date <= "' . $to_date . '")');
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'invoices';
            $join         = ['LEFT JOIN ' . db_prefix() . 'acc_account_history ON ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'invoices.id and ' . db_prefix() . 'acc_account_history.rel_id = "invoice"',
                'LEFT JOIN ' . db_prefix() . 'currencies ON ' . db_prefix() . 'currencies.id = ' . db_prefix() . 'invoices.currency',
                        ];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [db_prefix(). 'currencies.name as currency_name']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

                $categoryOutput = '<a href="' . admin_url('invoices/list_invoices/' . $aRow['id']) . '" target="_blank">' . format_invoice_number($aRow['id']) . '</a>';

                $categoryOutput .= '<div class="row-options">';
                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))) {
                        $categoryOutput .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="invoice-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="invoice" data-amount="'.$aRow['total'].'">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $categoryOutput .= '<a href="#" onclick="convert(this); return false;" id="invoice-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="invoice" data-amount="'.$aRow['total'].'">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $categoryOutput .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'invoice\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }



                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;

                $row[] = _d($aRow['date']);
                $row[] = app_format_money($aRow['total'], $aRow['currency_name']);

                $row[] = get_company_name($aRow['clientid']);

                $status_name = _l('has_not_been_converted');
                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                } 

                $row[] = '<span class="label label-' . $label_class . ' s-status invoice-status-' . $aRow['id'] . '">' . $status_name . '</span>';

                $row[] = format_invoice_status($aRow[db_prefix() . 'invoices.status']);

                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-amount' => $aRow['total'],
                        'data-type' => 'invoice',
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * expenses table
     * @return json
     */
    public function expenses_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1', // bulk actions
                db_prefix() . 'expenses.id as id',
                'amount',
                'invoiceid',
                db_prefix() . 'expenses_categories.name as category_name',
                'expense_name',
                db_prefix() . 'payment_modes.name as payment_mode_name',
                db_prefix() . 'expenses.date as date',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'expenses.id and ' . db_prefix() . 'acc_account_history.rel_type = "expense") as count_account_historys'
            ];
            $where = [];

            if ($this->input->post('invoice')) {
                $invoice = $this->input->post('invoice');
                array_push($where, 'AND invoiceid IN (' . implode(', ', $invoice) . ')');
            }

            if ($this->input->post('payment_mode')) {
                $payment_mode = $this->input->post('payment_mode');
                array_push($where, 'AND paymentmode IN (' . implode(', ', $payment_mode) . ')');
            }

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'expenses.id and ' . db_prefix() . 'acc_account_history.rel_type = "expense") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'expenses.id and ' . db_prefix() . 'acc_account_history.rel_type = "expense") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'expenses.id and ' . db_prefix() . 'acc_account_history.rel_type = "expense") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'expenses.id and ' . db_prefix() . 'acc_account_history.rel_type = "expense") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }
            
            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'expenses.date >= "' . $from_date . '" and ' . db_prefix() . 'expenses.date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'expenses.date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'expenses.date <= "' . $to_date . '")');
            }

            $select_purchase = '0 as count_purchases';
            if(acc_get_status_modules('purchase')){
                $select_purchase = '(select count(*) from ' . db_prefix() . 'pur_orders where ' . db_prefix() . 'pur_orders.expense_convert = ' . db_prefix() . 'expenses.id) as count_purchases';
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'expenses';
            $join         = [
                'JOIN ' . db_prefix() . 'expenses_categories ON ' . db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'expenses.category',
                'LEFT JOIN ' . db_prefix() . 'payment_modes ON ' . db_prefix() . 'payment_modes.id = ' . db_prefix() . 'expenses.paymentmode',
                'LEFT JOIN ' . db_prefix() . 'currencies ON ' . db_prefix() . 'currencies.id = ' . db_prefix() . 'expenses.currency'
            ];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [db_prefix(). 'currencies.name as currency_name', $select_purchase]);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';
                $categoryOutput = $aRow['expense_name'];

                $categoryOutput .= '<div class="row-options">';
                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date)) && $aRow['count_purchases'] == 0) {
                        $categoryOutput .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="expense-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="expense" data-amount="'.$aRow['amount'].'">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $categoryOutput .= '<a href="#" onclick="convert(this); return false;" id="expense-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="expense" data-amount="'.$aRow['amount'].'">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $categoryOutput .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'expense\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;
                $row[] = _d($aRow['date']);

                $row[] = app_format_money($aRow['amount'], $aRow['currency_name']);

                $row[] = $aRow['category_name'];
                $row[] = $aRow['payment_mode_name'];
                $row[] = '<a href="' . admin_url('invoices/list_invoices/' . $aRow['invoiceid']) . '" target="_blank">' . format_invoice_number($aRow['invoiceid']) . '</a>';

                $status_name = _l('has_not_been_converted');
                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                }
                if ($aRow['count_purchases'] > 0) {
                    $row[] = '';
                }else{
                    $row[] = '<span class="label label-' . $label_class . ' s-status expense-status-' . $aRow['id'] . '">' . $status_name . '</span>';
                }
                
                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date)) && $aRow['count_purchases'] == 0){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-amount' => $aRow['amount'],
                        'data-type' => 'expense',
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * banking table
     * @return json
     */
    public function banking_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1', // bulk actions
                'id',
                db_prefix() . 'acc_transaction_bankings.date as date',
                'withdrawals',
                'deposits',
                'payee',
                'description',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'acc_transaction_bankings.id and ' . db_prefix() . 'acc_account_history.rel_type = "banking") as count_account_historys'
            
            ];
            $where = [];

            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'acc_transaction_bankings.date >= "' . $from_date . '" and ' . db_prefix() . 'acc_transaction_bankings.date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'acc_transaction_bankings.date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'acc_transaction_bankings.date <= "' . $to_date . '")');
            }

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'acc_transaction_bankings.id and ' . db_prefix() . 'acc_account_history.rel_type = "banking") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'acc_transaction_bankings.id and ' . db_prefix() . 'acc_account_history.rel_type = "banking") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'acc_transaction_bankings.id and ' . db_prefix() . 'acc_account_history.rel_type = "banking") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'acc_transaction_bankings.id and ' . db_prefix() . 'acc_account_history.rel_type = "banking") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_transaction_bankings';
            $join         = [];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, []);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';
                $categoryOutput = _d($aRow['date']);
                $amount = $aRow['withdrawals'] > 0 ? $aRow['withdrawals'] : $aRow['deposits'];
                $categoryOutput .= '<div class="row-options">';
                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))) {
                        $categoryOutput .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="banking-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="banking" data-amount="'.$amount.'">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $categoryOutput .= '<a href="#" onclick="convert(this); return false;" id="banking-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="banking" data-amount="'.$amount.'">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $categoryOutput .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'banking\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;

                $row[] = app_format_money($aRow['withdrawals'], $currency->name);
                $row[] = app_format_money($aRow['deposits'], $currency->name);

                $row[] = $aRow['payee'];
                $row[] = $aRow['description'];

                $status_name = _l('has_not_been_converted');
                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                } 

                $row[] = '<span class="label label-' . $label_class . ' s-status payment-status-' . $aRow['id'] . '">' . $status_name . '</span>';

                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-amount' => $amount,
                        'data-type' => 'banking',
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * manage chart of accounts
     */
    public function chart_of_accounts(){
        if (!has_permission('accounting_chart_of_accounts', '', 'view')) {
            access_denied('chart_of_accounts');
        }

        $data['title'] = _l('chart_of_accounts');
        $data['account_types'] = $this->accounting_model->get_account_types();
        $data['detail_types'] = $this->accounting_model->get_account_type_details();
        $data['accounts'] = $this->accounting_model->get_accounts();
        $this->load->view('chart_of_accounts/manage', $data);
    }

    /**
     * setting
     * @return view
     */
    public function setting()
    {
        if (!has_permission('accounting_setting', '', 'view')) {
            access_denied('setting');
        }
        
        $data          = [];
        $data['group'] = $this->input->get('group');

        $data['tab'][] = 'general';
        $data['tab'][] = 'banking_rules';
        $data['tab'][] = 'mapping_setup';
        $data['tab'][] = 'account_type_details';
        $data['tab'][] = 'plaid_environment';
        
        $data['tab_2'] = $this->input->get('tab');
        if ($data['group'] == '') {
            $data['group'] = 'general';
        }

        if ($data['group'] == 'mapping_setup') {
            if ($data['tab_2'] == '') {
                $data['tab_2'] = 'general_mapping_setup';
            }

            $data['items'] = $this->accounting_model->get_items_not_yet_auto();
            $this->load->model('invoice_items_model');
            $data['_items'] = $this->invoice_items_model->get();
            $this->load->model('taxes_model');
            $data['_taxes'] = $this->taxes_model->get();
            $data['taxes'] = $this->accounting_model->get_taxes_not_yet_auto();

            $this->load->model('expenses_model');
            $data['_categories'] = $this->expenses_model->get_category();
            $data['categories'] = $this->accounting_model->get_expense_category_not_yet_auto();

            $this->load->model('payment_modes_model');
            $data['_payment_modes'] = $this->payment_modes_model->get();
            $data['payment_modes'] = $this->accounting_model->get_payment_mode_not_yet_auto();
        }elseif ($data['group'] == 'account_type_details') {
            $data['account_types'] = $this->accounting_model->get_account_types();
        }
        $data['accounts'] = $this->accounting_model->get_accounts();
        $data['title']        = _l($data['group']);
        $data['tabs']['view'] = 'setting/' . $data['group'];
        $this->load->view('setting/manage', $data);
    }

    /**
     * update general setting
     */
    public function update_general_setting(){
        if (!has_permission('accounting_setting', '', 'edit') && !is_admin()) {
            access_denied('accounting_setting');
        }
        $data = $this->input->post();
        $success = $this->accounting_model->update_general_setting($data);
        if($success == true){
            $message = _l('updated_successfully', _l('setting'));
            set_alert('success', $message);
        }
        redirect(admin_url('accounting/setting?group=general'));
    }

    /**
     * update automatic conversion
     */
    public function update_automatic_conversion(){
        if (!has_permission('accounting_setting', '', 'edit') && !is_admin()) {
            access_denied('accounting_setting');
        }
        $data = $this->input->post();
        $success = $this->accounting_model->update_automatic_conversion($data);
        if($success == true){
            $message = _l('updated_successfully', _l('setting'));
            set_alert('success', $message);
        }
        redirect(admin_url('accounting/setting?group=mapping_setup'));
    }

    /**
     * accounts table
     * @return json
     */
    public function accounts_table()
    {
        if ($this->input->is_ajax_request()) {
            $acc_enable_account_numbers = get_option('acc_enable_account_numbers');
            $acc_show_account_numbers = get_option('acc_show_account_numbers');

            $accounts = $this->accounting_model->get_accounts();
            $account_types = $this->accounting_model->get_account_types();
            $detail_types = $this->accounting_model->get_account_type_details();

            $account_name = [];
            $account_type_name = [];
            $detail_type_name = [];

            foreach ($accounts as $key => $value) {
                $account_name[$value['id']] = $value['name'];
            }

            foreach ($account_types as $key => $value) {
                $account_type_name[$value['id']] = $value['name'];
            }

            foreach ($detail_types as $key => $value) {
                $detail_type_name[$value['id']] = $value['name'];
            }

            $array_history = [2,3,4,5,7,8,9,10];
            
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();

            if($acc_enable_account_numbers == 1 && $acc_show_account_numbers == 1){
                $select = [
                    '1', // bulk actions
                    'id',
                    'number',
                    'name',
                    'parent_account',
                    'account_type_id',
                    'account_detail_type_id',
                    'balance',
                    'key_name',
                    'active',
                ];
            }else {
                $select = [
                    '1', // bulk actions
                    'id',
                    'name',
                    'parent_account',
                    'account_type_id',
                    'account_detail_type_id',
                    'balance',
                    'key_name',
                    'active',
                ];
            }

            $where = [];
            if ($this->input->post('ft_active')) {
                $ft_active = $this->input->post('ft_active');
                if($ft_active == 'yes'){
                    array_push($where, 'AND active = 1');
                }elseif($ft_active == 'no'){
                    array_push($where, 'AND active = 0');
                }
            }
            if ($this->input->post('ft_account')) {
                $ft_account = $this->input->post('ft_account');
                array_push($where, 'AND id IN (' . implode(', ', $ft_account) . ')');
            }
            if ($this->input->post('ft_parent_account')) {
                $ft_parent_account = $this->input->post('ft_parent_account');
                array_push($where, 'AND parent_account IN (' . implode(', ', $ft_parent_account) . ')');
            }
            if ($this->input->post('ft_type')) {
                $ft_type = $this->input->post('ft_type');
                array_push($where, 'AND account_type_id IN (' . implode(', ', $ft_type) . ')');
            }
            if ($this->input->post('ft_detail_type')) {
                $ft_detail_type = $this->input->post('ft_detail_type');
                array_push($where, 'AND account_detail_type_id IN (' . implode(', ', $ft_detail_type) . ')');
            }

            $accounting_method = get_option('acc_accounting_method');

            if($accounting_method == 'cash'){
                $debit = '(SELECT sum(debit) as debit FROM '.db_prefix().'acc_account_history where (account = '.db_prefix().'acc_accounts.id or parent_account = '.db_prefix().'acc_accounts.id) AND (('.db_prefix().'acc_account_history.rel_type = "invoice" AND '.db_prefix().'acc_account_history.paid = 1) or rel_type != "invoice")) as debit';
                $credit = '(SELECT sum(credit) as credit FROM '.db_prefix().'acc_account_history where (account = '.db_prefix().'acc_accounts.id or parent_account = '.db_prefix().'acc_accounts.id) AND (('.db_prefix().'acc_account_history.rel_type = "invoice" AND '.db_prefix().'acc_account_history.paid = 1) or rel_type != "invoice")) as credit';
            }else{
                $debit = '(SELECT sum(debit) as debit FROM '.db_prefix().'acc_account_history where (account = '.db_prefix().'acc_accounts.id or parent_account = '.db_prefix().'acc_accounts.id)) as debit';
                $credit = '(SELECT sum(credit) as credit FROM '.db_prefix().'acc_account_history where (account = '.db_prefix().'acc_accounts.id or parent_account = '.db_prefix().'acc_accounts.id)) as credit';
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_accounts';
            $join         = [];
            $result       = $this->accounting_model->get_account_data_tables($aColumns, $sIndexColumn, $sTable, $join, $where, ['number', 'description', 'balance_as_of', $debit, $credit, 'default_account']);
            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

                $categoryOutput = '';
                if(isset($aRow['level'])){
                    for ($i=0; $i < $aRow['level']; $i++) { 
                        $categoryOutput .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                    }
                }
                
                if($acc_enable_account_numbers == 1 && $acc_show_account_numbers == 1 && $aRow['number'] != ''){
                    $categoryOutput .= $aRow['number'] .' - ';
                }

                if($aRow['name'] == ''){
                    $categoryOutput .= _l($aRow['key_name']);
                }else{
                    $categoryOutput .= $aRow['name'];
                }

                $categoryOutput .= '<div class="row-options">';

                if (has_permission('accounting_chart_of_accounts', '', 'edit')) {
                    $categoryOutput .= '<a href="#" onclick="edit_account(' . $aRow['id'] . '); return false;">' . _l('edit') . '</a>';
                }

                if (has_permission('accounting_chart_of_accounts', '', 'delete') && $aRow['default_account'] == 0) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/delete_account/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;
                if($aRow['parent_account'] != '' && $aRow['parent_account'] != 0){
                    $row[] = (isset($account_name[$aRow['parent_account']]) ? $account_name[$aRow['parent_account']] : '');
                }else{
                    $row[] = '';
                }
                $row[] = isset($account_type_name[$aRow['account_type_id']]) ? $account_type_name[$aRow['account_type_id']] : '';
                $row[] = isset($detail_type_name[$aRow['account_detail_type_id']]) ? $detail_type_name[$aRow['account_detail_type_id']] : '';
                if($aRow['account_type_id'] == 11 || $aRow['account_type_id'] == 12 || $aRow['account_type_id'] == 8 || $aRow['account_type_id'] == 9 || $aRow['account_type_id'] == 10 || $aRow['account_type_id'] == 7){
                    $row[] = app_format_money($aRow['credit'] - $aRow['debit'], $currency->name);
                }else{
                    $row[] = app_format_money($aRow['debit'] - $aRow['credit'], $currency->name);
                }
                $row[] = '';

                $checked = '';
                if ($aRow['active'] == 1) {
                    $checked = 'checked';
                }

                $_data = '<div class="onoffswitch">
                    <input type="checkbox" ' . ((!has_permission('accounting_chart_of_accounts', '', 'edit') && !is_admin()) ? 'disabled' : '') . ' data-switch-url="' . admin_url() . 'accounting/change_account_status" name="onoffswitch" class="onoffswitch-checkbox" id="c_' . $aRow['id'] . '" data-id="' . $aRow['id'] . '" ' . $checked . '>
                    <label class="onoffswitch-label" for="c_' . $aRow['id'] . '"></label>
                </div>';

                // For exporting
                $_data .= '<span class="hide">' . ($checked == 'checked' ? _l('is_active_export') : _l('is_not_active_export')) . '</span>';
                $row[] = $_data;
                
                $options = '';
                if(in_array($aRow['account_type_id'], $array_history)){
                    $options = icon_btn(admin_url('accounting/rp_account_history?account='.$aRow['id']), 'history', 'btn-default', [
                        'title' => _l('account_history'),
                    ]);
                }
                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     *
     *  add or edit account
     *  @param  integer  $id     The identifier
     *  @return view
     */
    public function account()
    {
        if (!has_permission('accounting_chart_of_accounts', '', 'edit') && !has_permission('accounting_chart_of_accounts', '', 'create')) {
            access_denied('accounting');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $data['description'] = $this->input->post('description', false);
            $message = '';
            if ($data['id'] == '') {
                if (!has_permission('accounting_chart_of_accounts', '', 'create')) {
                    access_denied('accounting');
                }
                $success = $this->accounting_model->add_account($data);
                if ($success) {
                    $message = _l('added_successfully', _l('acc_account'));
                }else {
                    $message = _l('add_failure');
                }
            } else {
                if (!has_permission('accounting_chart_of_accounts', '', 'edit')) {
                    access_denied('accounting');
                }
                $id = $data['id'];
                unset($data['id']);
                $success = $this->accounting_model->update_account($data, $id);
                if ($success) {
                    $message = _l('updated_successfully', _l('acc_account'));
                }else {
                    $message = _l('updated_fail');
                }
            }

            echo json_encode(['success' => $success, 'message' => $message]);
            die();
        }
    }

    /**
     * get data convert
     * @param  integer $id   
     * @param  string $type 
     * @return json       
     */
    public function get_data_convert($id, $type){
        $this->load->model('currencies_model');
        $currency = $this->currencies_model->get_base_currency();

        $html = '';
        $list_item = [];
        if($type == 'payment'){
            $this->load->model('payments_model');
            $payment = $this->payments_model->get($id);

            $this->load->model('invoices_model');
            $invoice = $this->invoices_model->get($payment->invoiceid);
            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                         <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('invoice').'</td>
                            <td>'. '<a href="' . admin_url('invoices/list_invoices/' . $payment->invoiceid) . '" target="_blank">' . format_invoice_number($payment->invoiceid) . '</a>' .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('acc_amount').'</td>
                            <td>'. app_format_money($payment->amount, $invoice->currency_name) .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('expense_dt_table_heading_date').'</td>
                            <td>'. _d($payment->date) .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('payment_modes').'</td>
                            <td>'. html_entity_decode($payment->name) .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('note').'</td>
                            <td colspan="2">'. html_entity_decode($payment->note) .'</td>
                         </tr>';
            $amount = 1;
            
            $_html = '';
            if($invoice->currency_name != $currency->name){
                $amount = $this->accounting_model->currency_converter($invoice->currency_name, $currency->name, 1);

                $edit_template = "";
                $edit_template .= render_input('edit_exchange_rate','exchange_rate', $amount, 'number');
                $edit_template .= "<div class='text-center mtop10'>";
                $edit_template .= "<button type='button' class='btn btn-success edit_conversion_rate_action'>"._l('copy_task_confirm')."</button>";
                $edit_template .= "</div>";
                $_html .= form_hidden('currency_from', $invoice->currency_name);
                $_html .= form_hidden('currency_to', $currency->name);
                $_html .= form_hidden('exchange_rate', $amount);
                $_html .= form_hidden('payment_amount', $payment->amount);
                $_html .= '<div class="row"><div class="col-md-12"><label class="currency_converter_label th font-medium mbot15 pull-left">1 '.$invoice->currency_name.' = '.$amount.' '.$currency->name.'</label><a href="#" onclick="return false;" data-placement="bottom" data-toggle="popover" data-content="'. htmlspecialchars($edit_template) .'" data-html="true" data-original-title class="pull-left mleft5 font-medium-xs"><i class="fa fa-pencil-square-o"></i></a><br></div></div>';
                $html .=   '<tr class="project-overview">
                                <td class="bold">'. _l('amount_after_convert').'</td>
                                <td class="amount_after_convert">'.app_format_money(round($amount*$payment->amount, 2), $currency->name).'</td>
                                <td>'.$_html.'</td>
                             </tr>';
            }
            $html .=   '</tbody>
                  </table>';
            if($invoice->currency_name != $currency->name){
                $amount = $this->accounting_model->currency_converter($invoice->currency_name, $currency->name, 1);

                $edit_template = "";
                $edit_template .= render_input('edit_exchange_rate','exchange_rate', $amount, 'number');
                $edit_template .= "<div class='text-center mtop10'>";
                $edit_template .= "<button type='button' class='btn btn-success edit_conversion_rate_action'>"._l('copy_task_confirm')."</button>";
                $edit_template .= "</div>";
                $html .= form_hidden('currency_from', $invoice->currency_name);
                $html .= form_hidden('currency_to', $currency->name);
                $html .= form_hidden('exchange_rate', $amount);
                $html .= '<h4>'._l('currency_converter').'</h4><div class="row"><div class="col-md-12"><label class="currency_converter_label th font-medium mbot15 pull-left">1 '.$invoice->currency_name.' = '.$amount.' '.$currency->name.'</label><a href="#" onclick="return false;" data-placement="bottom" data-toggle="popover" data-content="'. htmlspecialchars($edit_template) .'" data-html="true" data-original-title class="pull-left mleft5 font-medium-xs"><i class="fa fa-pencil-square-o"></i></a><br></div></div>';
                
            }
            $debit = get_option('acc_payment_deposit_to');
            $credit = get_option('acc_payment_payment_account');
        }elseif ($type == 'expense') {
            $this->load->model('expenses_model');
            $expense = $this->expenses_model->get($id);
            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('expense_category').'</td>
                            <td>'. $expense->category_name  .'</td>
                            <td></td>
                         </tr>
                        <tr class="project-overview">
                            <td class="bold">'. _l('expense_name').'</td>
                            <td>'. $expense->expense_name  .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('invoice').'</td>
                            <td>'. '<a href="' . admin_url('invoices/list_invoices/' . $expense->invoiceid) . '" target="_blank">' . format_invoice_number($expense->invoiceid) . '</a>' .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('acc_amount').'</td>
                            <td>'. app_format_money($expense->amount, $expense->currency_data->name) .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('expense_dt_table_heading_date').'</td>
                            <td>'. _d($expense->date) .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('payment_modes').'</td>
                            <td>'. html_entity_decode($expense->payment_mode_name) .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('note').'</td>
                            <td colspan="2">'. html_entity_decode($expense->note) .'</td>
                         </tr>';

            $this->load->model('currencies_model');
            $currency = $this->currencies_model->get_base_currency();
            $amount = 1;
            if($expense->currency_data->name != $currency->name){
                $amount = $this->accounting_model->currency_converter($expense->currency_data->name, $currency->name, 1);
                $_html = '';
                $edit_template = "";
                $edit_template .= render_input('edit_exchange_rate','exchange_rate', $amount, 'number');
                $edit_template .= "<div class='text-center mtop10'>";
                $edit_template .= "<button type='button' class='btn btn-success edit_conversion_rate_action'>"._l('copy_task_confirm')."</button>";
                $edit_template .= "</div>";
                $_html .= form_hidden('currency_from', $expense->currency_data->name);
                $_html .= form_hidden('currency_to', $currency->name);
                $_html .= form_hidden('exchange_rate', $amount);
                $_html .= form_hidden('expense_amount', $expense->amount);

                $_html .= '<div class="row"><div class="col-md-12"><label class="currency_converter_label th font-medium mbot15 pull-left">1 '.$expense->currency_data->name.' = '.$amount.' '.$currency->name.'</label><a href="#" onclick="return false;" data-placement="bottom" data-toggle="popover" data-content="'. htmlspecialchars($edit_template) .'" data-html="true" data-original-title class="pull-left mleft5 font-medium-xs"><i class="fa fa-pencil-square-o"></i></a><br></div></div>';

                $html .=   '<tr class="project-overview">
                                <td class="bold">'. _l('amount_after_convert').'</td>
                                <td class="amount_after_convert">'.app_format_money(round($amount*$expense->amount, 2), $currency->name).'</td>
                                <td>'.$_html.'</td>
                             </tr>';
                
            }

            $html .=    '</tbody>
                  </table>';

            $debit = get_option('acc_expense_deposit_to');
            $credit = get_option('acc_expense_payment_account');
        }elseif ($type == 'banking') {
            $banking = $this->accounting_model->get_transaction_banking($id);
            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('expense_dt_table_heading_date').'</td>
                            <td>'. _d($banking->date)  .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('withdrawals').'</td>
                            <td>'. app_format_money($banking->withdrawals, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('deposits').'</td>
                            <td>'. app_format_money($banking->deposits, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('payee').'</td>
                            <td>'. $banking->payee .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('description').'</td>
                            <td>'. $banking->description .'</td>
                         </tr>
                        </tbody>
                  </table>';

            $debit = 0;
            $credit = 0;
        }elseif ($type == 'invoice') {
            $this->load->model('invoices_model');
            $invoice = $this->invoices_model->get($id);
            $accounts = $this->accounting_model->get_accounts();

            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('number').'</td>
                            <td>'. format_invoice_number($invoice->id)  .'</td>
                            <td></td>
                        </tr>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('expense_dt_table_heading_date').'</td>
                            <td>'. _d($invoice->date)  .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('invoice_dt_table_heading_duedate').'</td>
                            <td>'. _d($invoice->duedate)  .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('customer').'</td>
                            <td>'. get_company_name($invoice->clientid) .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('total').'</td>
                            <td>'. app_format_money($invoice->total, $invoice->currency_name) .'</td>
                            <td></td>
                         </tr>';

            $this->load->model('currencies_model');
            $currency = $this->currencies_model->get_base_currency();
            $amount = 1;
            if($invoice->currency_name != $currency->name){
                $amount = $this->accounting_model->currency_converter($invoice->currency_name, $currency->name, 1);
                $_html = '';
                $edit_template = "";
                $edit_template .= render_input('edit_exchange_rate','exchange_rate', $amount, 'number');
                $edit_template .= "<div class='text-center mtop10'>";
                $edit_template .= "<button type='button' class='btn btn-success edit_conversion_rate_action'>"._l('copy_task_confirm')."</button>";
                $edit_template .= "</div>";
                $_html .= form_hidden('currency_from', $invoice->currency_name);
                $_html .= form_hidden('currency_to', $currency->name);
                $_html .= form_hidden('exchange_rate', $amount);
                $_html .= form_hidden('payment_amount', $invoice->total);

                $_html .= '<div class="row"><div class="col-md-12"><label class="currency_converter_label th font-medium mbot15 pull-left">1 '.$invoice->currency_name.' = '.$amount.' '.$currency->name.'</label><a href="#" onclick="return false;" data-placement="bottom" data-toggle="popover" data-content="'. htmlspecialchars($edit_template) .'" data-html="true" data-original-title class="pull-left mleft5 font-medium-xs"><i class="fa fa-pencil-square-o"></i></a><br></div></div>';

                $html .=   '<tr class="project-overview">
                                <td class="bold">'. _l('amount_after_convert').'</td>
                                <td class="amount_after_convert">'.app_format_money(round($amount*$invoice->total, 2), $currency->name).'</td>
                                <td>'.$_html.'</td>
                             </tr>';
                
            }

            $html .=    '</tbody>
                  </table>';



            if($invoice->items){
                $payment_account = get_option('acc_invoice_payment_account');
                $deposit_to = get_option('acc_invoice_deposit_to');

                $html .= '<h4>'._l('list_of_items').'</h4>';
                foreach ($invoice->items as $value) {
                    $item = $this->accounting_model->get_item_by_name($value['description']);
                    $item_id = '-1';
                    if(isset($item->id)){
                        $item_id = $item->id;
                    }
                    $list_item[] = $item_id;

                    $this->db->where('rel_id', $id);
                    $this->db->where('rel_type', $type);
                    $this->db->where('(itemable_id = '.$value['id'].' or item = '.$item_id.')');
                    $account_history = $this->db->get(db_prefix(). 'acc_account_history')->result_array();
                    foreach ($account_history as $key => $val) {
                        if($val['debit'] > 0){
                            $debit = $val['account'];
                        }

                        if($val['credit'] > 0){
                            $credit =  $val['account'];
                        }
                    }

                    if($account_history){
                        $html .= '
                        <div class="div_content">
                        <h5>'.$value['description'].'</h5>
                        <div class="row">
                                '.form_hidden('item_amount['.$value['id'].']', $value['qty'] * $value['rate']).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$value['id'].']',$accounts,array('id','name', 'account_type_name'),'payment_account',$credit,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$value['id'].']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$debit,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                    }else{
                        $item_automatic = $this->accounting_model->get_item_automatic($item_id);

                        if($item_automatic){
                            $html .= '
                            <div class="div_content">
                                <h5>'.$value['description'].'</h5>
                                <div class="row">
                                '.form_hidden('item_amount['.$value['id'].']', $value['qty'] * $value['rate']).'
                                  <div class="col-md-6"> '.
                                    render_select('payment_account['.$value['id'].']',$accounts,array('id','name', 'account_type_name'),'payment_account',$item_automatic->income_account,array(),array(),'','',false) .'
                                  </div>
                                  <div class="col-md-6">
                                    '. render_select('deposit_to['.$value['id'].']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to,array(),array(),'','',false).'
                                  </div>
                              </div>
                            </div>';
                        }else{

                            $html .= '
                            <div class="div_content">
                                <h5>'.$value['description'].'</h5>
                                <div class="row">
                                '.form_hidden('item_amount['.$value['id'].']', $value['qty'] * $value['rate']).'
                                  <div class="col-md-6"> '.
                                    render_select('payment_account['.$value['id'].']',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account,array(),array(),'','',false) .'
                                  </div>
                                  <div class="col-md-6">
                                    '. render_select('deposit_to['.$value['id'].']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to,array(),array(),'','',false).'
                                  </div>
                              </div>
                            </div>';
                        }
                    }
                }
            }

            $debit = get_option('acc_invoice_deposit_to');
            $credit = get_option('acc_invoice_payment_account');
        }elseif ($type == 'payslip') {
            $this->db->where('id', $id);
            $payslip = $this->db->get(db_prefix(). 'hrp_payslips')->row();

            $this->db->where('payslip_id', $id);
            $payslip_details = $this->db->get(db_prefix(). 'hrp_payslip_details')->result_array();

            $accounts = $this->accounting_model->get_accounts();


            $payment_account = get_option('acc_pl_total_insurance_payment_account');
            $deposit_to = get_option('acc_pl_total_insurance_deposit_to');

            if($payslip->payslip_status == 'payslip_closing'){
                $_data_status = ' <span class="label label-success "> '._l($payslip->payslip_status).' </span>';
            }else{
                $_data_status = ' <span class="label label-primary"> '._l($payslip->payslip_status).' </span>';
            }
            $total_insurance = 0;
            $net_pay = 0;
            $income_tax_paye = 0;
            foreach ($payslip_details as $key => $value) {
                if(is_numeric($value['total_insurance'])){
                    $total_insurance += $value['total_insurance'];
                }

                if(is_numeric($value['net_pay'])){
                    $net_pay += $value['net_pay'];
                }

                if(is_numeric($value['income_tax_paye'])){
                    $income_tax_paye += $value['income_tax_paye'];
                }
            }

            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('payslip_name').'</td>
                            <td>'. $payslip->payslip_name  .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('payslip_name').'</td>
                            <td>'. get_payslip_template_name($payslip->payslip_template_id) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('payslip_month').'</td>
                            <td>'. date('m-Y', strtotime($payslip->payslip_month))  .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('date_created').'</td>
                            <td>'. _dt($payslip->date_created)  .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('status').'</td>
                            <td>'. $_data_status  .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('ps_total_insurance').'</td>
                            <td>'. app_format_money($total_insurance, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('ps_income_tax_paye').'</td>
                            <td>'. app_format_money($income_tax_paye, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('ps_net_pay').'</td>
                            <td>'. app_format_money($net_pay, $currency->name) .'</td>
                         </tr>
                        </tbody>
                  </table>';

                $this->db->where('rel_id', $id);
                $this->db->where('rel_type', $type);
                $this->db->where('payslip_type', 'total_insurance');
                $account_history = $this->db->get(db_prefix(). 'acc_account_history')->result_array();
                
                $payment_account_insurance = get_option('acc_pl_total_insurance_payment_account');
                $deposit_to_insurance = get_option('acc_pl_total_insurance_deposit_to');
                foreach ($account_history as $key => $val) {
                    if($val['debit'] > 0){
                        $deposit_to_insurance =  $val['account'];
                    }

                    if($val['credit'] > 0){
                        $payment_account_insurance = $val['account'];
                    }
                }

                $html .= '
                        <div class="div_content">
                            <h5>'._l('ps_total_insurance').'</h5>
                            <div class="row">
                            '.form_hidden('total_insurance', $total_insurance).'
                              <div class="col-md-6"> '.
                                render_select('payment_account_insurance',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account_insurance,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to_insurance',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to_insurance,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';

                $this->db->where('rel_id', $id);
                $this->db->where('rel_type', $type);
                $this->db->where('payslip_type', 'tax_paye');
                $account_history = $this->db->get(db_prefix(). 'acc_account_history')->result_array();
                
                $payment_account_tax_paye = get_option('acc_pl_tax_paye_payment_account');
                $deposit_to_tax_paye = get_option('acc_pl_tax_paye_deposit_to');
                foreach ($account_history as $key => $val) {
                    if($val['debit'] > 0){
                        $deposit_to_tax_paye =  $val['account'];
                    }

                    if($val['credit'] > 0){
                        $payment_account_tax_paye = $val['account'];
                    }
                }

                $html .= '
                        <div class="div_content">
                            <h5>'._l('ps_income_tax_paye').'</h5>
                            <div class="row">
                            '.form_hidden('tax_paye', $income_tax_paye).'
                              <div class="col-md-6"> '.
                                render_select('payment_account_tax_paye',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account_tax_paye,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to_tax_paye',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to_tax_paye,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                        
                $this->db->where('rel_id', $id);
                $this->db->where('rel_type', $type);
                $this->db->where('payslip_type', 'net_pay');
                $account_history = $this->db->get(db_prefix(). 'acc_account_history')->result_array();
                
                $payment_account_net_pay = get_option('acc_pl_net_pay_payment_account');
                $deposit_to_net_pay = get_option('acc_pl_net_pay_deposit_to');
                foreach ($account_history as $key => $val) {
                    if($val['debit'] > 0){
                        $deposit_to_net_pay =  $val['account'];
                    }

                    if($val['credit'] > 0){
                        $payment_account_net_pay = $val['account'];
                    }
                }

                $html .= '
                        <div class="div_content">
                            <h5>'._l('ps_net_pay').'</h5>
                            <div class="row">
                            '.form_hidden('net_pay', $net_pay).'
                              <div class="col-md-6"> '.
                                render_select('payment_account_net_pay',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account_net_pay,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to_net_pay',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to_net_pay,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';

            $debit = get_option('acc_expense_deposit_to');
            $credit = get_option('acc_expense_payment_account');
        }elseif ($type == 'purchase_order') {
            $accounts = $this->accounting_model->get_accounts();

            $this->load->model('purchase/purchase_model');
            $purchase_order = $this->purchase_model->get_pur_order($id);
            $purchase_order_detail = $this->purchase_model->get_pur_order_detail($id);

            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('purchase_order').'</td>
                            <td>'. '<a href="' . admin_url('purchase/purchase_order/' . $purchase_order->id) . '">'.$purchase_order->pur_order_number. '</a>'  .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('order_date').'</td>
                            <td>'. _d($purchase_order->order_date) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('vendor').'</td>
                            <td>'. '<a href="' . admin_url('purchase/vendor/' . $purchase_order->vendor) . '" >' .  get_vendor_company_name($purchase_order->vendor) . '</a>' .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('po_value').'</td>
                            <td>'. app_format_money($purchase_order->subtotal, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('tax_value').'</td>
                            <td>'. app_format_money($purchase_order->total_tax, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('po_value_included_tax').'</td>
                            <td>'. app_format_money($purchase_order->total, $currency->name) .'</td>
                         </tr>
                        </tbody>
                  </table>';

            if($purchase_order_detail){
                $payment_account = get_option('acc_pur_order_payment_account');
                $deposit_to = get_option('acc_pur_order_deposit_to');

                $html .= '<h4>'._l('list_of_items').'</h4>';
                foreach ($purchase_order_detail as $value) {

                    $this->db->where('id', $value['item_code']);
                    $item = $this->db->get(db_prefix().'items')->row();

                    $item_description = '';
                    if(isset($item) && isset($item->commodity_code) && isset($item->description)){
                       $item_description = $item->commodity_code.' - '.$item->description;
                    }

                    $item_id = 0;
                    if(isset($item->id)){
                        $item_id = $item->id;
                    }

                    if($item_id == 0){
                        continue;
                    }
                    $list_item[] = $item_id;

                    $this->db->where('rel_id', $id);
                    $this->db->where('rel_type', $type);
                    $this->db->where('item', $item_id);
                    $account_history = $this->db->get(db_prefix(). 'acc_account_history')->result_array();
                    
                    foreach ($account_history as $key => $val) {
                        if($val['debit'] > 0){
                            $debit = $val['account'];
                        }

                        if($val['credit'] > 0){
                            $credit =  $val['account'];
                        }
                    }

                    if($account_history){
                        $html .= '
                        <div class="div_content">
                        <h5>'.$item_description.'</h5>
                        <div class="row">
                                '.form_hidden('item_amount['.$item_id.']', $value['into_money']).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$credit,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$debit,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                    }else{
                        $item_automatic = $this->accounting_model->get_item_automatic($item_id);

                        if($item_automatic){
                            $html .= '
                        <div class="div_content">
                            <h5>'.$item_description.'</h5>
                            <div class="row">
                            '.form_hidden('item_amount['.$item_id.']', $value['into_money']).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$item_automatic->expence_account,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                        }else{

                            $html .= '
                        <div class="div_content">
                            <h5>'.$item_description.'</h5>
                            <div class="row">
                            '.form_hidden('item_amount['.$item_id.']', $value['into_money']).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                        }
                    }
                }
            }

            $debit = 0;
            $credit = 0;
        }elseif ($type == 'stock_export') {
            $this->load->model('warehouse/warehouse_model');
            $goods_delivery = $this->warehouse_model->get_goods_delivery($id);
            $goods_delivery_detail = $this->warehouse_model->get_goods_delivery_detail($id);
            $accounts = $this->accounting_model->get_accounts();
            $status = '';

            if($goods_delivery->approval == 1){
                $status = '<span class="label label-tag tag-id-1 label-tab1"><span class="tag">'._l('approved').'</span><span class="hide">, </span></span>&nbsp';
            }elseif($goods_delivery->approval == 0){
                $status = '<span class="label label-tag tag-id-1 label-tab2"><span class="tag">'._l('not_yet_approve').'</span><span class="hide">, </span></span>&nbsp';
            }elseif($goods_delivery->approval == -1){
                $status = '<span class="label label-tag tag-id-1 label-tab3"><span class="tag">'._l('reject').'</span><span class="hide">, </span></span>&nbsp';
            }

            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('expense_dt_table_heading_date').'</td>
                            <td><a href="' . admin_url('warehouse/view_delivery/' . $goods_delivery->id ).'">' . $goods_delivery->goods_delivery_code . '</a></td>
                         </tr>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('accounting_date').'</td>
                            <td>'. _d($goods_delivery->date_c)  .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('status').'</td>
                            <td>'. $status .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('subtotal').'</td>
                            <td>'. app_format_money($goods_delivery->total_money, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('total_discount').'</td>
                            <td>'. app_format_money($goods_delivery->total_discount, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('total_money').'</td>
                            <td>'. app_format_money($goods_delivery->after_discount, $currency->name) .'</td>
                         </tr>
                        </tbody>
                  </table>';

            if($goods_delivery_detail){
                $payment_account = get_option('acc_wh_stock_export_payment_account');
                $deposit_to = get_option('acc_wh_stock_export_deposit_to');

                $html .= '<h4>'._l('list_of_items').'</h4>';

                foreach ($goods_delivery_detail as $value) {

                    $this->db->where('id', $value['commodity_code']);
                    $item = $this->db->get(db_prefix().'items')->row();

                    $item_description = '';
                    if(isset($item) && isset($item->commodity_code) && isset($item->description)){
                       $item_description = $item->commodity_code.' - '.$item->description;
                    }

                    $item_id = 0;
                    if(isset($item->id)){
                        $item_id = $item->id;
                    }

                    if($item_id == 0){
                        continue;
                    }

                    $list_item[] = $item_id;

                    $this->db->where('rel_id', $id);
                    $this->db->where('rel_type', $type);
                    $this->db->where('item', $item_id);
                    $account_history = $this->db->get(db_prefix(). 'acc_account_history')->result_array();
                    
                    foreach ($account_history as $key => $val) {
                        if($val['debit'] > 0){
                            $debit = $val['account'];
                        }

                        if($val['credit'] > 0){
                            $credit =  $val['account'];
                        }
                    }

                    if($account_history){
                        $html .= '
                        <div class="div_content">
                        <h5>'.$item_description.'</h5>
                        <div class="row">
                                '.form_hidden('item_amount['.$item_id.']', ($value['quantities'] * $value['unit_price'])).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$credit,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$debit,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                    }else{
                        $item_automatic = $this->accounting_model->get_item_automatic($item_id);

                        if($item_automatic){
                            $html .= '
                        <div class="div_content">
                            <h5>'.$item_description.'</h5>
                            <div class="row">
                            '.form_hidden('item_amount['.$item_id.']', ($value['quantities'] * $value['unit_price'])).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$item_automatic->inventory_asset_account,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                        }else{

                            $html .= '
                        <div class="div_content">
                            <h5>'.$item_description.'</h5>
                            <div class="row">
                            '.form_hidden('item_amount['.$item_id.']', ($value['quantities'] * $value['unit_price'])).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                        }
                    }
                }
            }

            $debit = 0;
            $credit = 0;
        }elseif ($type == 'stock_import') {
            $accounts = $this->accounting_model->get_accounts();

            $this->load->model('warehouse/warehouse_model');
            $goods_receipt = $this->warehouse_model->get_goods_receipt($id);
            $goods_receipt_detail = $this->warehouse_model->get_goods_receipt_detail($id);

            $status = '';

            if($goods_receipt->approval == 1){
                $status = '<span class="label label-tag tag-id-1 label-tab1"><span class="tag">'._l('approved').'</span><span class="hide">, </span></span>&nbsp';
            }elseif($goods_receipt->approval == 0){
                $status = '<span class="label label-tag tag-id-1 label-tab2"><span class="tag">'._l('not_yet_approve').'</span><span class="hide">, </span></span>&nbsp';
            }elseif($goods_receipt->approval == -1){
                $status = '<span class="label label-tag tag-id-1 label-tab3"><span class="tag">'._l('reject').'</span><span class="hide">, </span></span>&nbsp';
            }

            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                        <tr class="project-overview">
                            <td class="bold">'. _l('withdrawals').'</td>
                            <td><a href="' . admin_url('warehouse/view_purchase/' . $goods_receipt->id) . '" target="_blank">' . $goods_receipt->goods_receipt_code . '</a></td>
                        </tr>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('accounting_date').'</td>
                            <td>'. _d($goods_receipt->date_c)  .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('status').'</td>
                            <td>'. $status .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('total_tax_money').'</td>
                            <td>'. app_format_money($goods_receipt->total_tax_money, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('total_goods_money').'</td>
                            <td>'. app_format_money($goods_receipt->total_goods_money, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('value_of_inventory').'</td>
                            <td>'. app_format_money($goods_receipt->value_of_inventory, $currency->name) .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('total_money').'</td>
                            <td>'. app_format_money($goods_receipt->total_money, $currency->name) .'</td>
                         </tr>
                        </tbody>
                  </table>';

            if($goods_receipt_detail){
                $payment_account = get_option('acc_wh_stock_import_payment_account');
                $deposit_to = get_option('acc_wh_stock_import_deposit_to');

                $html .= '<h4>'._l('list_of_items').'</h4>';

                foreach ($goods_receipt_detail as $value) {

                    $this->db->where('id', $value['commodity_code']);
                    $item = $this->db->get(db_prefix().'items')->row();

                    $item_description = '';
                    if(isset($item) && isset($item->commodity_code) && isset($item->description)){
                       $item_description = $item->commodity_code.' - '.$item->description;
                    }

                    $item_id = 0;
                    if(isset($item->id)){
                        $item_id = $item->id;
                    }

                    if($item_id == 0){
                        continue;
                    }

                    $list_item[] = $item_id;

                    $this->db->where('rel_id', $id);
                    $this->db->where('rel_type', $type);
                    $this->db->where('item', $item_id);
                    $account_history = $this->db->get(db_prefix(). 'acc_account_history')->result_array();
                    
                    foreach ($account_history as $key => $val) {
                        if($val['debit'] > 0){
                            $debit = $val['account'];
                        }

                        if($val['credit'] > 0){
                            $credit =  $val['account'];
                        }
                    }

                    if($account_history){
                        $html .= '
                        <div class="div_content">
                        <h5>'.$item_description.'</h5>
                        <div class="row">
                                '.form_hidden('item_amount['.$item_id.']', $value['goods_money']).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$credit,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$debit,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                    }else{
                        $item_automatic = $this->accounting_model->get_item_automatic($item_id);

                        if($item_automatic){
                            $html .= '
                        <div class="div_content">
                            <h5>'.$item_description.'</h5>
                            <div class="row">
                            '.form_hidden('item_amount['.$item_id.']', $value['goods_money']).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$item_automatic->inventory_asset_account,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                        }else{

                            $html .= '
                        <div class="div_content">
                            <h5>'.$item_description.'</h5>
                            <div class="row">
                            '.form_hidden('item_amount['.$item_id.']', $value['goods_money']).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                        }
                    }
                }
            }

            $debit = 0;
            $credit = 0;
        }elseif ($type == 'loss_adjustment') {
            $accounts = $this->accounting_model->get_accounts();

            $this->load->model('warehouse/warehouse_model');

            $loss_adjustment = $this->warehouse_model->get_loss_adjustment($id);
            $loss_adjustment_detail = $this->warehouse_model->get_loss_adjustment_detailt_by_masterid($id);

            $banking = $this->accounting_model->get_transaction_banking($id);

            $status = '';

            if ((int) $loss_adjustment->status == 0) {
                $status = '<div class="btn btn-warning" >' . _l('draft') . '</div>';
            } elseif ((int) $loss_adjustment->status == 1) {
                $status = '<div class="btn btn-success" >' . _l('Adjusted') . '</div>';
            } elseif((int) $loss_adjustment->status == -1){

                $status = '<div class="btn btn-danger" >' . _l('reject') . '</div>';
            }

            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                        <tr class="project-overview">
                            <td class="bold">'. _l('type').'</td>
                            <td><a href="' . admin_url('warehouse/view_lost_adjustment/' . $loss_adjustment->id) . '" target="_blank">' . _l($loss_adjustment->type) . '</a></td>
                        </tr>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('_time').'</td>
                            <td>'. _d($loss_adjustment->time)  .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('status').'</td>
                            <td>'. $status .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('reason').'</td>
                            <td>'. html_entity_decode($loss_adjustment->reason) .'</td>
                         </tr>
                        </tbody>
                  </table>';

            if($loss_adjustment_detail){
                $decrease_payment_account = get_option('acc_wh_decrease_payment_account');
                $decrease_deposit_to = get_option('acc_wh_decrease_deposit_to');

                $increase_payment_account = get_option('acc_wh_increase_payment_account');
                $increase_deposit_to = get_option('acc_wh_increase_deposit_to');


                $html .= '<h4>'._l('list_of_items').'</h4>';

                foreach ($loss_adjustment_detail as $value) {
                    if($value['current_number'] < $value['updates_number']){
                        $number = $value['updates_number'] - $value['current_number'];
                        $payment_account = $increase_payment_account;
                        $deposit_to = $increase_deposit_to;
                    }else{
                        $number = $value['current_number'] - $value['updates_number'];
                        $payment_account = $decrease_payment_account;
                        $deposit_to = $decrease_deposit_to;
                    }

                    $this->db->where('id', $value['items']);
                    $item = $this->db->get(db_prefix().'items')->row();

                    $item_description = '';
                    if(isset($item) && isset($item->commodity_code) && isset($item->description)){
                       $item_description = $item->commodity_code.' - '.$item->description;
                    }

                    $item_id = 0;
                    if(isset($item->id)){
                        $item_id = $item->id;
                    }

                    if($item_id == 0){
                        continue;
                    }
                    $list_item[] = $item_id;

                    $this->db->where('rel_id', $id);
                    $this->db->where('rel_type', $type);
                    $this->db->where('item', $item_id);
                    $account_history = $this->db->get(db_prefix(). 'acc_account_history')->result_array();
                    
                    foreach ($account_history as $key => $val) {
                        if($val['debit'] > 0){
                            $debit = $val['account'];
                        }

                        if($val['credit'] > 0){
                            $credit =  $val['account'];
                        }
                    }

                    $price = 0;
                    if($value['lot_number'] != ''){
                        $this->db->where('lot_number', $value['lot_number']);
                        $this->db->where('expiry_date', $value['expiry_date']);
                        $receipt_detail = $this->db->get(db_prefix().'goods_receipt_detail')->row();
                        if($receipt_detail){
                            $price = $receipt_detail->unit_price;
                        }else{
                            $this->db->where('id' ,$item_id);
                            $item = $this->db->get(db_prefix().'items')->row();
                            if($item){
                                $price = $item->purchase_price;
                            }
                        }
                    }else{
                        $this->db->where('id' ,$item_id);
                        $item = $this->db->get(db_prefix().'items')->row();
                        if($item){
                            $price = $item->purchase_price;
                        }
                    }

                    if($account_history){
                        $html .= '
                        <div class="div_content">
                        <h5>'.$item_description.'</h5>
                        <div class="row">
                                '.form_hidden('item_amount['.$item_id.']', $number * $price).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$credit,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$debit,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                    }else{
                        $item_automatic = $this->accounting_model->get_item_automatic($item_id);

                        if($item_automatic){
                            $html .= '
                        <div class="div_content">
                            <h5>'.$item_description.'</h5>
                            <div class="row">
                            '.form_hidden('item_amount['.$item_id.']', $number * $price).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$item_automatic->inventory_asset_account,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                        }else{

                            $html .= '
                        <div class="div_content">
                            <h5>'.$item_description.'</h5>
                            <div class="row">
                            '.form_hidden('item_amount['.$item_id.']', $number * $price).'
                              <div class="col-md-6"> '.
                                render_select('payment_account['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account,array(),array(),'','',false) .'
                              </div>
                              <div class="col-md-6">
                                '. render_select('deposit_to['.$item_id.']',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to,array(),array(),'','',false).'
                              </div>
                          </div>
                        </div>';
                        }
                    }
                }
            }

            $debit = 0;
            $credit = 0;
        }elseif ($type == 'opening_stock') {

            $accounts = $this->accounting_model->get_accounts();
            $opening_stock = $this->accounting_model->get_opening_stock_data($id);
            $deposit_to = get_option('acc_wh_opening_stock_deposit_to');
            $payment_account = get_option('acc_wh_opening_stock_payment_account');
            $acc_first_month_of_financial_year = get_option('acc_first_month_of_financial_year');

            $date_financial_year = date('Y-m-d', strtotime($acc_first_month_of_financial_year . ' 01 '.date('Y')));

            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                        <tr class="project-overview">
                            <td class="bold">'. _l('commodity_code').'</td>
                            <td><a href="' . admin_url('warehouse/view_commodity_detail/' . $opening_stock->id) . '" target="_blank">' . $opening_stock->commodity_code . '</a></td>
                        </tr>
                        <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('commodity_name').'</td>
                            <td>'. $opening_stock->description .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('sku_code').'</td>
                            <td>'. $opening_stock->sku_code .'</td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('opening_stock').'</td>
                            <td>'. app_format_money($opening_stock->opening_stock, $currency->name) .'</td>
                         </tr>
                        </tbody>
                  </table><br>';

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', $type);
            $this->db->where('date >= "'.$date_financial_year.'"');
            $account_history = $this->db->get(db_prefix(). 'acc_account_history')->result_array();

            foreach ($account_history as $key => $value) {
                if($value['debit'] > 0){
                    $deposit_to = $value['account'];
                }

                if($value['credit'] > 0){
                    $payment_account =  $value['account'];
                }
            }

            $html .= '
                    <div class="row">
                      <div class="col-md-6"> '.
                        render_select('payment_account',$accounts,array('id','name', 'account_type_name'),'payment_account',$payment_account,array(),array(),'','',false) .'
                      </div>
                      <div class="col-md-6">
                        '. render_select('deposit_to',$accounts,array('id','name', 'account_type_name'),'deposit_to',$deposit_to,array(),array(),'','',false).'
                      </div>
                </div>';

            $debit = 0;
            $credit = 0;
        }elseif($type == 'purchase_payment'){
            $this->load->model('purchase/purchase_model');
            $payment = $this->purchase_model->get_payment_pur_invoice($id);

            $invoice = $this->purchase_model->get_pur_invoice($payment->pur_invoice);

            $html = '<table class="table border table-striped no-margin">
                      <tbody>
                         <tr class="project-overview">
                            <td class="bold" width="30%">'. _l('purchase_order').'</td>
                            <td>'.'<a href="'.admin_url('purchase/purchase_order/'.$invoice->pur_order).'">'.get_pur_order_subject($invoice->pur_order).'</a>' .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('acc_amount').'</td>
                            <td>'. app_format_money($payment->amount, $currency->name) .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('expense_dt_table_heading_date').'</td>
                            <td>'. _d($payment->date) .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('payment_modes').'</td>
                            <td>'. get_payment_mode_name_by_id($payment->paymentmode) .'</td>
                            <td></td>
                         </tr>
                         <tr class="project-overview">
                            <td class="bold">'. _l('note').'</td>
                            <td colspan="2">'. html_entity_decode($payment->note) .'</td>
                         </tr>';
            $amount = 1;
            
            
            $html .=   '</tbody>
                  </table>';
           
            $debit = get_option('acc_pur_payment_deposit_to');
            $credit = get_option('acc_pur_payment_payment_account');
        }

        $this->db->where('rel_id', $id);
        $this->db->where('rel_type', $type);
        $this->db->where('(tax = 0 or tax is null)');
        $account_history = $this->db->get(db_prefix(). 'acc_account_history')->result_array();
        foreach ($account_history as $key => $value) {
            if($value['debit'] > 0){
                $debit = $value['account'];
            }

            if($value['credit'] > 0){
                $credit =  $value['account'];
            }
        }

        echo json_encode(['html' => $html, 'debit' => $debit, 'credit' => $credit, 'list_item' => $list_item]);
        die();
    }

    /**
     * convert
     * @return json 
     */
    public function convert(){
        if (!has_permission('accounting_transaction', '', 'create')) {
            access_denied('accounting');
        }
        $data = $this->input->post();
        $success = $this->accounting_model->add_account_history($data);
        if ($success) {
            $message = _l('successfully_converted');
        }else {
            $message = _l('conversion_failed');
        }
        echo json_encode(['success' => $success, 'message' => $message]);
        die();
    }

    /**
     * transfer
     * @return view
     */
    public function transfer(){
        if (!has_permission('accounting_transfer', '', 'view')) {
            access_denied('accounting');
        }
        $data['title']         = _l('transfer');
        $data['accounts'] = $this->accounting_model->get_accounts('', 'find_in_set(account_type_id, "2,3,4,5,7,8,9,10")');

        $this->load->view('transfer/manage', $data);
    }

    /**
     * accounts table
     * @return json
     */
    public function transfer_table()
    {
        if ($this->input->is_ajax_request()) {
            $accounts = $this->accounting_model->get_accounts();
            $account_name = [];

            foreach ($accounts as $key => $value) {
                $account_name[$value['id']] = $value['name'];
            }

            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $select = [
                '1', // bulk actions
                'id',
                'transfer_funds_from',
                'transfer_funds_to',
                'transfer_amount',
            ];

            $where = [];

            if ($this->input->post('ft_transfer_funds_from')) {
                $ft_transfer_funds_from = $this->input->post('ft_transfer_funds_from');
                array_push($where, 'AND transfer_funds_from IN (' . implode(', ', $ft_transfer_funds_from) . ')');
            }

            if ($this->input->post('ft_transfer_funds_to')) {
                $ft_transfer_funds_to = $this->input->post('ft_transfer_funds_to');
                array_push($where, 'AND transfer_funds_to IN (' . implode(', ', $ft_transfer_funds_to) . ')');
            }

            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (date >= "' . $from_date . '" and date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (date <= "' . $to_date . '")');
            }
            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_transfers';
            $join         = [];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['date']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';
                $categoryOutput = (isset($account_name[$aRow['transfer_funds_from']]) ? $account_name[$aRow['transfer_funds_from']] : '');

                $categoryOutput .= '<div class="row-options">';

                if (has_permission('accounting_transfer', '', 'edit')) {
                    $categoryOutput .= '<a href="#" onclick="edit_transfer(' . $aRow['id'] . '); return false;">' . _l('edit') . '</a>';
                }

                if (has_permission('accounting_transfer', '', 'delete')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/delete_transfer/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;
                $row[] = (isset($account_name[$aRow['transfer_funds_to']]) ? $account_name[$aRow['transfer_funds_to']] : '');
                $row[] = app_format_money($aRow['transfer_amount'], $currency->name);
                $row[] = _d($aRow['date']);

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * add transfer
     * @return json
     */
    public function add_transfer(){
        $data = $this->input->post();
        $data['description'] = $this->input->post('description', false);
        if($data['id'] == ''){
            if (!has_permission('accounting_transfer', '', 'create')) {
                access_denied('accounting');
            }
            $success = $this->accounting_model->add_transfer($data);
            if ($success === 'close_the_book') {
                $message = _l('has_closed_the_book');
            }elseif($success){
                $message = _l('successfully_transferred');
            }else {
                $message = _l('transfer_failed');
            }
        }else{
            if (!has_permission('accounting_transfer', '', 'edit')) {
                access_denied('accounting');
            }
            $id = $data['id'];
            unset($data['id']);
            $success = $this->accounting_model->update_transfer($data, $id);
            if ($success === 'close_the_book') {
                $message = _l('has_closed_the_book');
            }elseif ($success) {
                $message = _l('updated_successfully', _l('transfer'));
            }
        }
        echo json_encode(['success' => $success, 'message' => $message]);
        die();
    }

    /**
     * journal entry
     * @return view
     */
    public function journal_entry(){
        if (!has_permission('accounting_journal_entry', '', 'view')) {
            access_denied('accounting');
        }
        $data['title']         = _l('journal_entry');
        $data['accounts'] = $this->accounting_model->get_accounts();
        $data['accounts_to_select'] = $this->accounting_model->get_data_account_to_select();
        $this->load->view('journal_entry/manage', $data);
    }

    /**
     * journal entry table
     * @return json
     */
    public function journal_entry_table(){
        if ($this->input->is_ajax_request()) {
           
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $select = [
                '1', // bulk actions
                'id',
                'number',
                'journal_date',
            ];

            $where = [];
            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (journal_date >= "' . $from_date . '" and journal_date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (journal_date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (journal_date <= "' . $to_date . '")');
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_journal_entries';
            $join         = [];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['amount', 'description']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';
                $categoryOutput = _d($aRow['journal_date']);

                $categoryOutput .= '<div class="row-options">';

                if (has_permission('accounting_journal_entry', '', 'edit')) {
                    $categoryOutput .= '<a href="' . admin_url('accounting/journal_entry_export/' . $aRow['id']) . '" class="text-success">' . _l('acc_export_excel') . '</a>';
                }

                if (has_permission('accounting_journal_entry', '', 'edit')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/new_journal_entry/' . $aRow['id']) . '">' . _l('edit') . '</a>';
                }

                if (has_permission('accounting_journal_entry', '', 'delete')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/delete_journal_entry/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;
                if(strlen($aRow['number'].' - '.html_entity_decode($aRow['description'])) > 150){
                    $row[] = '<div data-toggle="tooltip" data-title="'. $aRow['number'].' - '.html_entity_decode(strip_tags($aRow['description'])).'">'.substr($aRow['number'].' - '.html_entity_decode($aRow['description']), 0, 150).'...</div>';
                }else{
                    $row[] = $aRow['number'].' - '.html_entity_decode($aRow['description']);
                }
                $row[] = app_format_money($aRow['amount'], $currency->name);

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * add journal entry
     * @return view
     */
    public function new_journal_entry($id = ''){
        if ($this->input->post()) {
            $data                = $this->input->post();
            $data['description'] = $this->input->post('description', false);
            if($id == ''){
                if (!has_permission('accounting_journal_entry', '', 'create')) {
                    access_denied('accounting_journal_entry');
                }
                $success = $this->accounting_model->add_journal_entry($data);
                if ($success === 'close_the_book') {
                    $message = _l('has_closed_the_book');
                    set_alert('warning', _l('has_closed_the_book'));
                }elseif ($success) {
                    set_alert('success', _l('added_successfully', _l('journal_entry')));
                }
            }else{
                if (!has_permission('accounting_journal_entry', '', 'edit')) {
                    access_denied('accounting_journal_entry');
                }
                $success = $this->accounting_model->update_journal_entry($data, $id);
                if ($success === 'close_the_book') {
                    $message = _l('has_closed_the_book');
                    set_alert('warning', _l('has_closed_the_book'));
                }elseif ($success) {
                    set_alert('success', _l('updated_successfully', _l('journal_entry')));
                }
            }
            redirect(admin_url('accounting/journal_entry'));
        }

        if($id != ''){
            $data['journal_entry'] = $this->accounting_model->get_journal_entry($id);
        }
        $this->load->model('currencies_model');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['next_number'] = $this->accounting_model->get_journal_entry_next_number();
        $data['title'] = _l('journal_entry');
        $data['account_to_select'] = $this->accounting_model->get_data_account_to_select();

        $this->load->view('journal_entry/journal_entry', $data);
    }

    /**
     * delete journal entry
     * @param  integer $id
     * @return
     */
    public function delete_journal_entry($id)
    {
        if (!has_permission('accounting_journal_entry', '', 'delete')) {
            access_denied('accounting_journal_entry');
        }
        $success = $this->accounting_model->delete_journal_entry($id);
        $message = '';
        if ($success) {
            $message = _l('deleted', _l('journal_entry'));
            set_alert('success', $message);
        } else {
            $message = _l('can_not_delete');
            set_alert('warning', $message);
        }
        redirect(admin_url('accounting/journal_entry'));
    }

    /**
     * report manage
     * @return view
     */
    public function report(){
        if (!has_permission('accounting_report', '', 'view')) {
            access_denied('accounting_report');
        }
        $data['title'] = _l('accounting_report');

        $this->load->view('report/manage', $data);
    }

    /**
     * report balance sheet
     * @return view
     */
    public function rp_balance_sheet(){
        $this->load->model('currencies_model');
        $data['title'] = _l('balance_sheet');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/balance_sheet', $data);
    }

    /**
     * report balance sheet comparison
     * @return view
     */
    public function rp_balance_sheet_comparison(){
        $this->load->model('currencies_model');
        $data['title'] = _l('balance_sheet_comparison');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['accounting_method'] = get_option('acc_accounting_method');

        $this->load->view('report/includes/balance_sheet_comparison', $data);
    }

    /**
     * report balance sheet detail
     * @return view
     */
    public function rp_balance_sheet_detail(){
        $this->load->model('currencies_model');
        $data['title'] = _l('balance_sheet_detail');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/balance_sheet_detail', $data);
    }

    /**
     * report balance sheet summary
     * @return view 
     */
    public function rp_balance_sheet_summary(){
        $this->load->model('currencies_model');
        $data['title'] = _l('balance_sheet_summary');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/balance_sheet_summary', $data);
    }

    /**
     * report business snapshot
     * @return view
     */
    public function rp_business_snapshot(){
        $this->load->model('currencies_model');
        $data['title'] = _l('business_snapshot');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['data_report'] = $this->accounting_model->get_data_balance_sheet_summary([]);
        $this->load->view('report/includes/balance_sheet_summary', $data);
    }

    /**
     * custom summary report
     * @return view
     */
    public function rp_custom_summary_report(){
        $this->load->model('currencies_model');
        $data['title'] = _l('custom_summary_report');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_display_rows_by'] = '';
        $data['accounting_display_columns_by'] = '';
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/custom_summary_report', $data);
    }

    /**
     * report profit and loss as of total income
     * @return view
     */
    public function rp_profit_and_loss_as_of_total_income(){
        $this->load->model('currencies_model');
        $data['title'] = _l('profit_and_loss_as_of_total_income');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/profit_and_loss_as_of_total_income', $data);
    }

    /**
     * report profit and loss comparison
     * @return view
     */
    public function rp_profit_and_loss_comparison(){
        $this->load->model('currencies_model');
        $data['title'] = _l('profit_and_loss_comparison');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/profit_and_loss_comparison', $data);
    }

    /**
     * report profit and loss detail
     * @return view
     */
    public function rp_profit_and_loss_detail(){
        $this->load->model('currencies_model');
        $data['title'] = _l('profit_and_loss_detail');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/profit_and_loss_detail', $data);
    }

    /**
     * report profit and loss year to date comparison
     * @return view
     */
    public function rp_profit_and_loss_year_to_date_comparison(){
        $this->load->model('currencies_model');
        $data['title'] = _l('profit_and_loss_year_to_date_comparison');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $this->load->view('report/includes/profit_and_loss_year_to_date_comparison', $data);
    }

    /**
     * report profit and loss
     * @return view
     */
    public function rp_profit_and_loss(){
        $this->load->model('currencies_model');
        $data['title'] = _l('profit_and_loss');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/profit_and_loss', $data);
    }

    /**
     * report statement of cash flows
     * @return view
     */
    public function rp_statement_of_cash_flows(){
        $this->load->model('currencies_model');
        $data['title'] = _l('statement_of_cash_flows');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/statement_of_cash_flows', $data);
    }

    /**
     * report statement of changes in equity description
     * @return view
     */
    public function rp_statement_of_changes_in_equity(){
        $this->load->model('currencies_model');
        $data['title'] = _l('statement_of_changes_in_equity');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/statement_of_changes_in_equity', $data);
    }

    /**
     * report deposit detail
     * @return view
     */
    public function rp_deposit_detail(){
        $this->load->model('currencies_model');
        $data['title'] = _l('deposit_detail');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/deposit_detail', $data);
    }

    /**
     * report income by customer summary
     * @return view
     */
    public function rp_income_by_customer_summary(){
        $this->load->model('currencies_model');
        $data['title'] = _l('income_by_customer_summary');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/income_by_customer_summary', $data);
    }
    
    /**
     * report check detail
     * @return view
     */
    public function rp_check_detail(){
        $this->load->model('currencies_model');
        $data['title'] = _l('cheque_detail');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/check_detail', $data);
    }

    /**
     * report account list
     * @return view
     */
    public function rp_account_list(){
        $this->load->model('currencies_model');
        $data['title'] = _l('account_list');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/account_list', $data);
    }

    /**
     * report account history
     * @return view
     */
    public function rp_account_history(){
        $this->load->model('currencies_model');
        $data['title'] = _l('account_history');
        $data['account'] = $this->input->get('account');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['accounts'] = $this->accounting_model->get_accounts('', 'find_in_set(account_type_id, "2,3,4,5,7,8,9,10")');
        $this->load->view('report/includes/account_history', $data);
    }
    
    /**
     * report general ledger
     * @return view
     */
    public function rp_general_ledger(){
        $this->load->model('currencies_model');
        $data['title'] = _l('general_ledger');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/general_ledger', $data);
    }

    /**
     * report journal
     * @return view
     */
    public function rp_journal(){
        $this->load->model('currencies_model');
        $data['title'] = _l('journal');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/journal', $data);
    }

    /**
     * report recent transactions
     * @return view
     */
    public function rp_recent_transactions(){
        $this->load->model('currencies_model');
        $data['title'] = _l('recent_transactions');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/recent_transactions', $data);
    }

    /**
     * report transaction detail by account
     * @return view
     */
    public function rp_transaction_detail_by_account(){
        $this->load->model('currencies_model');
        $data['title'] = _l('transaction_detail_by_account');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/transaction_detail_by_account', $data);
    }

    /**
     * report transaction list by date
     * @return view
     */
    public function rp_transaction_list_by_date(){
        $this->load->model('currencies_model');
        $data['title'] = _l('transaction_list_by_date');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/transaction_list_by_date', $data);
    }

    /**
     * report trial balance
     * @return view
     */
    public function rp_trial_balance(){
        $this->load->model('currencies_model');
        $data['title'] = _l('trial_balance');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/trial_balance', $data);
    }

    /**
     * dashboard
     * @return view
     */
    public function dashboard(){
        if (!has_permission('accounting_dashboard', '', 'view')) {
            access_denied('accounting_dashboard');
        }
        $data['title'] = _l('dashboard');
        $this->load->model('currencies_model');

        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['currencys'] = $this->currencies_model->get();

        $data_filter = ['date' => 'last_30_days'];

        $this->load->view('dashboard/manage', $data);
    }

    /**
     * import xlsx banking
     * @return view
     */
    public function import_xlsx_banking() {
        if (!has_permission('accounting_transaction', '', 'create')) {
            access_denied('accounting_transaction');
        }

        $this->load->model('staff_model');
        $data_staff = $this->staff_model->get(get_staff_user_id());

        /*get language active*/
        if ($data_staff) {
            if ($data_staff->default_language != '') {
                $data['active_language'] = $data_staff->default_language;

            } else {

                $data['active_language'] = get_option('active_language');
            }

        } else {
            $data['active_language'] = get_option('active_language');
        }
        $data['title'] = _l('import_excel');

        $this->load->view('transaction/import_banking', $data);
    }

    /**
     * import file xlsx banking
     * @return json
     */
    public function import_file_xlsx_banking(){
        if(!class_exists('XLSXReader_fin')){
            require_once(module_dir_path(ACCOUNTING_MODULE_NAME).'assets/plugins/XLSXReader/XLSXReader.php');
        }
        require_once(module_dir_path(ACCOUNTING_MODULE_NAME).'assets/plugins/XLSXWriter/xlsxwriter.class.php');

        $filename ='';
        if($this->input->post()){
            if (isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '') {
                $this->delete_error_file_day_before(1, ACCOUTING_IMPORT_ITEM_ERROR);

                // Get the temp file path
                $tmpFilePath = $_FILES['file_csv']['tmp_name'];                
                // Make sure we have a filepath
                if (!empty($tmpFilePath) && $tmpFilePath != '') {
                    $rows          = [];
                    $arr_insert          = [];

                    $tmpDir = TEMP_FOLDER . '/' . time() . uniqid() . '/';

                    if (!file_exists(TEMP_FOLDER)) {
                        mkdir(TEMP_FOLDER, 0755);
                    }

                    if (!file_exists($tmpDir)) {
                        mkdir($tmpDir, 0755);
                    }

                    // Setup our new file path
                    $newFilePath = $tmpDir . $_FILES['file_csv']['name'];                    

                    if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                        //Writer file
                        $writer_header = array(
                            _l('invoice_payments_table_date_heading').' (dd/mm/YYYY)'            =>'string',
                            _l('withdrawals')     =>'string',
                            _l('deposits')    =>'string',
                            _l('payee')      =>'string',
                            _l('description')     =>'string',
                            _l('error')       =>'string',
                        );

                        $rowstyle[] =array('widths'=>[10,20,30,40]);

                        $writer = new XLSXWriter();
                        $writer->writeSheetHeader('Sheet1', $writer_header,  $col_options = ['widths'=>[40,40,40,40,50,50]]);

                        //Reader file
                        $xlsx = new XLSXReader_fin($newFilePath);
                        $sheetNames = $xlsx->getSheetNames();
                        $data = $xlsx->getSheetData($sheetNames[1]);

                        $arr_header = [];

                        $arr_header['date'] = 0;
                        $arr_header['withdrawals'] = 1;
                        $arr_header['deposits'] = 2;
                        $arr_header['payee'] = 3;
                        $arr_header['description'] = 4;

                        $total_rows = 0;
                        $total_row_false    = 0; 

                        for ($row = 1; $row < count($data); $row++) {

                            $total_rows++;

                            $rd = array();
                            $flag = 0;
                            $flag2 = 0;

                            $string_error ='';
                            $flag_position_group;
                            $flag_department = null;

                            $value_date  = isset($data[$row][$arr_header['date']]) ? $data[$row][$arr_header['date']] : '' ;
                            $value_withdrawals   = isset($data[$row][$arr_header['withdrawals']]) ? $data[$row][$arr_header['withdrawals']] : '' ;
                            $value_deposits     = isset($data[$row][$arr_header['deposits']]) ? $data[$row][$arr_header['deposits']] : '' ;
                            $value_payee    = isset($data[$row][$arr_header['payee']]) ? $data[$row][$arr_header['payee']] : '' ;
                            $value_description   = isset($data[$row][$arr_header['description']]) ? $data[$row][$arr_header['description']] : '' ;
                            if(is_numeric($value_date)){
                                $value_date = $this->accounting_model->convert_excel_date($value_date);
                            }

                            $reg_day = '/([0-9]{2})\/([0-9]{2})\/([0-9]{4})/'; /*yyyy-mm-dd*/

                            if(is_null($value_date) != true){
                                if(preg_match($reg_day, $value_date, $match) != 1){
                                    $string_error .=_l('invoice_payments_table_date_heading'). _l('invalid');
                                    $flag = 1; 
                                }
                            }else{
                                $string_error .= _l('invoice_payments_table_date_heading') . _l('not_yet_entered');
                                $flag = 1;
                            }

                            if (is_null($value_withdrawals) == true) {
                                $string_error .= _l('withdrawals') . _l('not_yet_entered');
                                $flag = 1;
                            }else{
                                if(!is_numeric($value_withdrawals) && $value_deposits == ''){
                                    $string_error .= _l('withdrawals') . _l('invalid');
                                    $flag = 1;
                                }
                            }

                            if (is_null($value_deposits) == true) {
                                $string_error .= _l('deposits') . _l('not_yet_entered');
                                $flag = 1;
                            }else{
                                if(!is_numeric($value_deposits) && $value_withdrawals == ''){
                                    $string_error .= _l('deposits') . _l('invalid');
                                    $flag = 1;
                                }
                            }

                            if (is_null($value_payee) == true) {
                                $string_error .= _l('payee') . _l('not_yet_entered');
                                $flag = 1;
                            }
                            

                            if(($flag == 1) || $flag2 == 1 ){
                                //write error file
                                $writer->writeSheetRow('Sheet1', [
                                    $value_date,
                                    $value_withdrawals,
                                    $value_deposits,
                                    $value_payee,
                                    $value_description,
                                    $string_error,
                                ]);

                                // $numRow++;
                                $total_row_false++;
                            }

                            if($flag == 0 && $flag2 == 0){

                                $rd['date']       = $value_date;
                                $rd['withdrawals']         = $value_withdrawals;
                                $rd['deposits']        = $value_deposits;
                                $rd['payee']       = $value_payee;
                                $rd['description']               = $value_description;
                                $rd['datecreated']               = date('Y-m-d H:i:s');
                                $rd['addedfrom']               = get_staff_user_id();

                                $rows[] = $rd;
                                array_push($arr_insert, $rd);

                            }

                        }

                        //insert batch
                        if(count($arr_insert) > 0){
                            $this->accounting_model->insert_batch_banking($arr_insert);
                        }

                        $total_rows = $total_rows;
                        $total_row_success = isset($rows) ? count($rows) : 0;
                        $dataerror = '';
                        $message ='Not enought rows for importing';

                        if($total_row_false != 0){
                            $filename = 'Import_banking_error_'.get_staff_user_id().'_'.strtotime(date('Y-m-d H:i:s')).'.xlsx';
                            $writer->writeToFile(str_replace($filename, ACCOUTING_IMPORT_ITEM_ERROR.$filename, $filename));
                        }


                    }
                }
            }
        }


        if (file_exists($newFilePath)) {
            @unlink($newFilePath);
        }

        echo json_encode([
            'message'           => $message,
            'total_row_success' => $total_row_success,
            'total_row_false'   => $total_row_false,
            'total_rows'        => $total_rows,
            'site_url'          => site_url(),
            'staff_id'          => get_staff_user_id(),
            'filename'          => ACCOUTING_IMPORT_ITEM_ERROR.$filename,
        ]);
    }
    /**
     * get data transfer
     * @param  integer $id 
     * @return json     
     */
    public function get_data_transfer($id){
        $transfer = $this->accounting_model->get_transfer($id);
        $transfer->date = _d($transfer->date);
        echo json_encode($transfer);
    }

    /**
     * delete transfer
     * @param  integer $id
     * @return
     */
    public function delete_transfer($id)
    {
        if (!has_permission('accounting_transfer', '', 'delete')) {
            access_denied('accounting_transfer');
        }

        $success = $this->accounting_model->delete_transfer($id);
        $message = '';
        if ($success) {
            $message = _l('deleted', _l('transfer'));
            set_alert('success', $message);
        } else {
            $message = _l('can_not_delete');
            set_alert('warning', $message);
        }
        redirect(admin_url('accounting/transfer'));
    }

    /**
     * get data account
     * @param  integer $id 
     * @return json     
     */
    public function get_data_account($id){
        $account = $this->accounting_model->get_accounts($id);
        $account->balance_as_of = _d($account->balance_as_of);
        $account->name = $account->name != '' ? $account->name : _l($account->key_name);

        if($account->balance == 0){
            if($account->account_type_id > 10 || $account->account_type_id == 1 || $account->account_type_id == 6){
                $account->balance = 1;
            }else{
                $this->db->where('account', $id);
                $count = $this->db->count_all_results(db_prefix().'acc_account_history');
                if($count > 0){
                    $account->balance = 1;
                }
            }
        }

        echo json_encode($account);
    }
    
    /**
     * delete account
     * @param  integer $id
     * @return
     */
    public function delete_account($id)
    {
        if (!has_permission('accounting_chart_of_accounts', '', 'delete')) {
            access_denied('accounting_chart_of_accounts');
        }
        $success = $this->accounting_model->delete_account($id);
        $message = '';
        
        if ($success === 'have_transaction') {
            $message = _l('cannot_delete_transaction_already_exists');
            set_alert('warning', $message);
        }elseif ($success) {
            $message = _l('deleted', _l('acc_account'));
            set_alert('success', $message);
        } else {
            $message = _l('can_not_delete');
            set_alert('warning', $message);
        }
        redirect(admin_url('accounting/chart_of_accounts'));
    }

    /**
     * add rule
     * @return view
     */
    public function new_rule($id = ''){
        if (!has_permission('accounting_rule', '', 'create') && !is_admin() ) {
            access_denied('accounting_rule');
        }

        if ($this->input->post()) {
            $data                = $this->input->post();
            if($id == ''){
                $success = $this->accounting_model->add_rule($data);
                if ($success) {
                    set_alert('success', _l('added_successfully', _l('banking_rule')));
                }
            }else{
                $success = $this->accounting_model->update_rule($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('banking_rule')));
                }
            }
            redirect(admin_url('accounting/setting?group=banking_rules'));
        }

        if($id != ''){
            $data['rule'] = $this->accounting_model->get_rule($id);
        }
        $this->load->model('currencies_model');

        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['accounts'] = $this->accounting_model->get_accounts();
        $data['title'] = _l('banking_rule');
        $data['account_to_select'] = $this->accounting_model->get_data_account_to_select();

        $this->load->view('setting/rule', $data);
    }

    /**
     * delete convert
     * @param  integer $id
     * @return json
     */
    public function delete_convert($id,$type)
    {
        if (!has_permission('accounting_transaction', '', 'delete')) {
            access_denied('accounting_transaction');
        }
        $success = $this->accounting_model->delete_convert($id,$type);

        $message = _l('problem_deleting', _l('acc_convert'));

        if ($success) {
            $message = _l('deleted', _l('acc_convert'));
        }

        echo json_encode(['success' => $success, 'message' => $message]);
    }

    /**
     * reconcile
     * @return view or redirect
     */
    public function reconcile(){
        if (!has_permission('accounting_reconcile', '', 'view')) {
            access_denied('accounting_reconcile');
        }
        if ($this->input->post()) {
            if (!has_permission('accounting_reconcile', '', 'create')) {
                access_denied('accounting_reconcile');
            }
            $data                = $this->input->post();
            if($data['resume'] == 0){
                unset($data['resume']);
                $success = $this->accounting_model->add_reconcile($data);
            }
            redirect(admin_url('accounting/reconcile_account/'.$data['account']));

        }
        $this->load->model('currencies_model');
        $data['currency'] = $this->currencies_model->get_base_currency();

        $data['title']         = _l('reconcile');
        $data['accounts'] = $this->accounting_model->get_accounts('', 'find_in_set(account_type_id, "2,3,4,5,7,8,9,10,20,21,22,23,24,25")');
        $data['beginning_balance'] = 0;
        $data['resume'] = 0;

        $closing_date = false;
        $reconcile = $this->accounting_model->get_reconcile_by_account($data['accounts'][0]['id']);
        if($reconcile){
            if(get_option('acc_close_the_books') == 1){
                if(strtotime($reconcile->ending_date) <= strtotime(get_option('acc_closing_date')) && strtotime(date('Y-m-d')) > strtotime(get_option('acc_closing_date'))){
                    $closing_date = true;
                }
            }
            $data['beginning_balance'] = $reconcile->ending_balance;
            if($reconcile->finish == 0){
                $data['resume'] = 1;
            }
        }
        $data['accounts_to_select'] = $this->accounting_model->get_data_account_to_select();

        $hide_restored=' hide';

        $check_reconcile_restored = $this->accounting_model->check_reconcile_restored($data['accounts'][0]['id']);
        if($check_reconcile_restored){
            $hide_restored='';
        }

        $data['hide_restored'] = $closing_date == false ? $hide_restored : 'hide';

        $this->load->view('reconcile/reconcile', $data);
    }

    /**
     * reconcile account
     * @param  integer $account 
     * @return view          
     */
    public function reconcile_account($account){
        if (!has_permission('accounting_reconcile', '', 'create') && !is_admin() ) {
            access_denied('accounting_reconcile');
        }
        $data['accounts'] = $this->accounting_model->get_accounts();
        $data['account'] = $this->accounting_model->get_accounts($account);
        $data['reconcile'] = $this->accounting_model->get_reconcile_by_account($account);
        $this->load->model('currencies_model');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['title'] = _l('reconcile');

        $this->load->view('reconcile/reconcile_account', $data);
    }

    /**
     * get info reconcile
     * @param  integer $account
     * @return json
     */
    public function get_info_reconcile($account) {
        $reconcile = $this->accounting_model->get_reconcile_by_account($account);
        $beginning_balance = 0;
        $resume_reconciling = false;
        $hide_restored = true;

        $check_reconcile_restored = $this->accounting_model->check_reconcile_restored($account);
        if($check_reconcile_restored){
            $hide_restored = false;
        }
        $closing_date = false;

        if ($reconcile) {
            if(get_option('acc_close_the_books') == 1){
                if(strtotime($reconcile->ending_date) <= strtotime(get_option('acc_closing_date')) && strtotime(date('Y-m-d')) > strtotime(get_option('acc_closing_date'))){
                    $closing_date = true;
                }
            }

            $beginning_balance = $reconcile->ending_balance;
            if ($reconcile->finish == 0) {
                $resume_reconciling = true;
            }
        }

        echo json_encode(['beginning_balance' => $beginning_balance, 'resume_reconciling' => $resume_reconciling, 'hide_restored' => $hide_restored, 'closing_date' => $closing_date]);
        die();
    }

    /**
     * reconcile history table
     * @return json
     */
    public function reconcile_history_table(){
        if ($this->input->is_ajax_request()) {
            $accounts = $this->accounting_model->get_accounts();
            $account_name = [];

            foreach ($accounts as $key => $value) {
                $account_name[$value['id']] = $value['name'];
            }

            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $select = [
                db_prefix() .'acc_account_history.id as id',
                'account',
                'rel_type',
                'debit',
                'credit',
                db_prefix() .'acc_account_history.description as description',
                db_prefix() . 'acc_account_history.customer as history_customer'
            ];

            $where = [];

            if ($this->input->post('account') && $this->input->post('reconcile')) {
                $account = $this->input->post('account');
                array_push($where, 'AND (account = ' . $account.') and (reconcile = 0 or reconcile = '.$this->input->post('reconcile').') ');
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_account_history';
            $join         = ['LEFT JOIN ' . db_prefix() . 'acc_transfers ON ' . db_prefix() . 'acc_transfers.id = ' . db_prefix() . 'acc_account_history.rel_id and ' . db_prefix() . 'acc_account_history.rel_type = "transfer"',
            'LEFT JOIN ' . db_prefix() . 'acc_journal_entries ON ' . db_prefix() . 'acc_journal_entries.id = ' . db_prefix() . 'acc_account_history.rel_id and ' . db_prefix() . 'acc_account_history.rel_type = "journal_entry"',
            'LEFT JOIN ' . db_prefix() . 'invoicepaymentrecords ON ' . db_prefix() . 'invoicepaymentrecords.id = ' . db_prefix() . 'acc_account_history.rel_id and ' . db_prefix() . 'acc_account_history.rel_type = "payment"',
                        'LEFT JOIN ' . db_prefix() . 'invoices ON ' . db_prefix() . 'invoices.id = ' . db_prefix() . 'invoicepaymentrecords.invoiceid and ' . db_prefix() . 'acc_account_history.rel_type = "payment"',
                            'LEFT JOIN ' . db_prefix() . 'expenses ON ' . db_prefix() . 'expenses.id = ' . db_prefix() . 'acc_account_history.rel_id and ' . db_prefix() . 'acc_account_history.rel_type = "expense"'];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [ db_prefix() . 'expenses.clientid as expenses_customer', db_prefix() . 'expenses.date as expenses_date', db_prefix() . 'invoices.clientid as payment_customer', db_prefix() . 'invoicepaymentrecords.date as payment_date', db_prefix() . 'acc_journal_entries.journal_date as journal_date', db_prefix() . 'acc_transfers.date as transfer_date', 'date_format('.db_prefix() . 'acc_account_history.datecreated, \'%Y-%m-%d\') as history_date', 'reconcile','split']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $checked = '';
                if($aRow['reconcile'] != 0){
                    $checked = 'checked';
                }
                $row[] = '<div class="checkbox"><input '.$checked.' type="checkbox" id="history_checkbox_' . $aRow['id'] . '" value="' . $aRow['id'] . '" data-payment="'.$aRow['credit'] .'" data-deposit="'.$aRow['debit'] .'"><label class="label_checkbox"></label></div>';
                if($aRow['rel_type'] == 'payment'){
                    $row[] = _d($aRow['payment_date']);
                }elseif ($aRow['rel_type'] == 'expense') {
                    $row[] = _d($aRow['expenses_date']);
                }elseif ($aRow['rel_type'] == 'journal_entry') {
                    $row[] = _d($aRow['journal_date']);
                }elseif ($aRow['rel_type'] == 'transfer') {
                    $row[] = _d($aRow['transfer_date']);
                }else{
                    $row[] = _d($aRow['history_date']);
                }
                $row[] = _l($aRow['rel_type']);
                if($aRow['split'] > 0 && isset($account_name[$aRow['split']])){
                    $row[] = $account_name[$aRow['split']];
                }else{
                    $row[] = '-Split-';
                }

                if($aRow['rel_type'] == 'payment'){
                    $row[] = get_company_name($aRow['payment_customer']);
                }elseif ($aRow['rel_type'] == 'expense') {
                    $row[] = get_company_name($aRow['expenses_customer']);
                }else{
                    $row[] = get_company_name($aRow['history_customer']);
                }

                $row[] = $aRow['description'];
                if($aRow['credit'] > 0){
                    $row[] = app_format_money($aRow['credit'], $currency->name);
                }else{
                    $row[] = '';
                }

                if($aRow['debit'] > 0){
                    $row[] = app_format_money($aRow['debit'], $currency->name);
                }else{
                    $row[] = '';
                }

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     *
     *  add adjustment
     *  @return view
     */
    public function adjustment()
    {
        if (!has_permission('accounting_reconcile', '', 'create')) {
            access_denied('accounting');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $message = '';
            $success = $this->accounting_model->add_adjustment($data);

            if ($success === 'close_the_book') {
                $message = _l('has_closed_the_book');
            }elseif ($success) {
                $message = _l('added_successfully', _l('adjustment'));
            }else {
                $message = _l('add_failure');
            }

            echo json_encode(['success' => $success, 'message' => $message]);
            die();
        }
    }

    /**
     * reconcile account
     * @param  integer $account 
     * @return view          
     */
    public function finish_reconcile_account(){
        if (!has_permission('accounting_reconcile', '', 'create') && !is_admin() ) {
            access_denied('accounting_reconcile');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $message = '';
            $success = $this->accounting_model->finish_reconcile_account($data);

            if ($success) {
                $message = _l('added_successfully', _l('reconcile'));
                set_alert('success', $message);
            }else {
                $message = _l('add_failure');
                set_alert('warning', $message);
            }
        }

        redirect(admin_url('accounting/reconcile'));
    }

    /**
     * edit reconcile
     * @return redirect 
     */
    public function edit_reconcile(){
        if (!has_permission('accounting_reconcile', '', 'edit') && !is_admin() ) {
            access_denied('accounting_reconcile');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $id = $data['reconcile_id'];
            $account = $data['account'];
            unset($data['reconcile_id']);
            $message = '';
            $success = $this->accounting_model->update_reconcile($data, $id);

            if ($success) {
                $message = _l('updated_successfully', _l('reconcile'));
                set_alert('success', $message);
            }
        }

        redirect(admin_url('accounting/reconcile_account/'.$account));
    }

    /**
     * banking rules table
     * @return json
     */
    public function banking_rules_table(){
        if ($this->input->is_ajax_request()) {
           
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $select = [
                'id',
                'name',
            ];

            $where = [];
            $from_date = '';
            $to_date   = '';

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_banking_rules';
            $join         = [];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['transaction']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $categoryOutput = $aRow['name'];

                $categoryOutput .= '<div class="row-options">';

                if (has_permission('accounting_setting', '', 'edit')) {
                    $categoryOutput .= '<a href="' . admin_url('accounting/new_rule/' . $aRow['id']) . '">' . _l('edit') . '</a>';
                }

                if (has_permission('accounting_setting', '', 'delete')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/delete_rule/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;
                $row[] = _l($aRow['transaction']);

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * delete rule
     * @param  integer $id
     * @return
     */
    public function delete_rule($id)
    {
        if (!has_permission('accounting_setting', '', 'delete')) {
            access_denied('accounting_setting');
        }

        $success = $this->accounting_model->delete_rule($id);
        $message = '';
        if ($success) {
            $message = _l('deleted', _l('rule'));
            set_alert('success', $message);
        } else {
            $message = _l('can_not_delete');
            set_alert('warning', $message);
        }
        redirect(admin_url('accounting/setting?group=banking_rules'));
    }

    /**
     * view report
     * @return view
     */
    public function view_report(){
        $data_filter = $this->input->post();
        
        $this->load->model('currencies_model');
        $data['title'] = _l($data_filter['type']);
        $data['currency'] = $this->currencies_model->get_base_currency();

        switch ($data_filter['type']) {
            case 'balance_sheet':
                    $data['data_report'] = $this->accounting_model->get_data_balance_sheet($data_filter);
                break;
            case 'balance_sheet_comparison':
                    $data['data_report'] = $this->accounting_model->get_data_balance_sheet_comparison($data_filter);
                break;
            case 'balance_sheet_detail':
                    $data['data_report'] = $this->accounting_model->get_data_balance_sheet_detail($data_filter);
                break;
            case 'balance_sheet_summary':
                    $data['data_report'] = $this->accounting_model->get_data_balance_sheet_summary($data_filter);
                break;
            case 'custom_summary_report':
                    switch ($data_filter['display_rows_by']) {
                        case 'customers':
                            $data_filter['type'] = 'custom_summary_report_by_customer';
                            $data['data_report'] = $this->accounting_model->get_data_custom_summary_report_by_customer($data_filter);
                            break;

                        case 'vendors':
                            $data_filter['type'] = 'custom_summary_report_by_vendors';
                            $data['data_report'] = $this->accounting_model->get_data_custom_summary_report_by_vendors($data_filter);
                            break;

                        case 'employees':
                            $data_filter['type'] = 'custom_summary_report_by_employees';
                            $data['data_report'] = $this->accounting_model->get_data_custom_summary_report_by_employees($data_filter);
                            break;

                        case 'product_service':
                            $data_filter['type'] = 'custom_summary_report_by_product_service';
                            $data['data_report'] = $this->accounting_model->get_data_custom_summary_report_by_product_service($data_filter);
                            break;

                        case 'income_statement':
                            $data_filter['type'] = 'custom_summary_report_by_income_statement';
                            $data['data_report'] = $this->accounting_model->get_data_custom_summary_report_by_income_statement($data_filter);
                            break;

                        case 'balance_sheet':
                            $data_filter['type'] = 'custom_summary_report_by_balance_sheet';
                            $data['data_report'] = $this->accounting_model->get_data_custom_summary_report_by_balance_sheet($data_filter);
                            break;

                        case 'balance_sheet_summary':
                            $data_filter['type'] = 'custom_summary_report_by_balance_sheet_summary';
                            $data['data_report'] = $this->accounting_model->get_data_custom_summary_report_by_balance_sheet($data_filter);
                            break;

                        default:
                            // code...
                            break;
                    }
                    
                    
                break;
            case 'profit_and_loss_as_of_total_income':
                    $data['data_report'] = $this->accounting_model->get_data_profit_and_loss_as_of_total_income($data_filter);
                break;
            case 'profit_and_loss_comparison':
                    $data['data_report'] = $this->accounting_model->get_data_profit_and_loss_comparison($data_filter);
                break;
            case 'profit_and_loss_detail':
                    $data['data_report'] = $this->accounting_model->get_data_profit_and_loss_detail($data_filter);
                break;
            case 'profit_and_loss_year_to_date_comparison':
                    $data['data_report'] = $this->accounting_model->get_data_profit_and_loss_year_to_date_comparison($data_filter);
                break;
            case 'profit_and_loss':
                    $data['data_report'] = $this->accounting_model->get_data_profit_and_loss($data_filter);
                break;
            case 'statement_of_cash_flows':
                    $data['data_report'] = $this->accounting_model->get_data_statement_of_cash_flows($data_filter);
                break;
            case 'statement_of_changes_in_equity':
                    $data['data_report'] = $this->accounting_model->get_data_statement_of_changes_in_equity($data_filter);
                break;
            case 'deposit_detail':
                    $data['data_report'] = $this->accounting_model->get_data_deposit_detail($data_filter);
                break;
            case 'income_by_customer_summary':
                    $data['data_report'] = $this->accounting_model->get_data_income_by_customer_summary($data_filter);
                break;
            case 'check_detail':
                    $data['data_report'] = $this->accounting_model->get_data_check_detail($data_filter);
                break;
            case 'general_ledger':
                    $data['data_report'] = $this->accounting_model->get_data_general_ledger($data_filter);
                break;
            case 'journal':
                    $data['data_report'] = $this->accounting_model->get_data_journal($data_filter);
                break;
            case 'recent_transactions':
                    $data['data_report'] = $this->accounting_model->get_data_recent_transactions($data_filter);
                break;
            case 'transaction_detail_by_account':
                    $data['data_report'] = $this->accounting_model->get_data_transaction_detail_by_account($data_filter);
                break;
            case 'transaction_list_by_date':
                    $data['data_report'] = $this->accounting_model->get_data_transaction_list_by_date($data_filter);
                break;
            case 'trial_balance':
                    $data['data_report'] = $this->accounting_model->get_data_trial_balance($data_filter);
                break;
            case 'account_history':
                    $data['data_report'] = $this->accounting_model->get_data_account_history($data_filter);
                break;
            case 'tax_detail_report':
                    $data['data_report'] = $this->accounting_model->get_data_tax_detail_report($data_filter);
                break;
            case 'tax_summary_report':
                    $data['data_report'] = $this->accounting_model->get_data_tax_summary_report($data_filter);
                break;
            case 'tax_liability_report':
                    $data['data_report'] = $this->accounting_model->get_data_tax_liability_report($data_filter);
                break;
            case 'account_list':
                    $data['data_report'] = $this->accounting_model->get_data_account_list($data_filter);
                break;
            case 'accounts_receivable_ageing_detail':
                    $data['data_report'] = $this->accounting_model->get_data_accounts_receivable_ageing_detail($data_filter);
                break;
            case 'accounts_receivable_ageing_summary':
                    $data['data_report'] = $this->accounting_model->get_data_accounts_receivable_ageing_summary($data_filter);
                break;
            case 'accounts_payable_ageing_detail':
                    $data['data_report'] = $this->accounting_model->get_data_accounts_payable_ageing_detail($data_filter);
                break;
            case 'accounts_payable_ageing_summary':
                    $data['data_report'] = $this->accounting_model->get_data_accounts_payable_ageing_summary($data_filter);
                break;
            case 'profit_and_loss_12_months':
                    $data['data_report'] = $this->accounting_model->get_data_profit_and_loss_12_months($data_filter);
                break;
            case 'budget_overview':
                    $data['data_report'] = $this->accounting_model->get_data_budget_overview($data_filter);
                break;
            case 'budget_variance':
                    $data['data_report'] = $this->accounting_model->get_data_budget_variance($data_filter);
                break;
            case 'budget_comparison':
                    $data['data_report'] = $this->accounting_model->get_data_budget_comparison($data_filter);
                break;
            case 'profit_and_loss_budget_performance':
                    $data['data_report'] = $this->accounting_model->get_data_profit_and_loss_budget_performance($data_filter);
                break;
            case 'profit_and_loss_budget_vs_actual':
                    $data['data_report'] = $this->accounting_model->get_data_profit_and_loss_budget_vs_actual($data_filter);
                break;
            case 'bank_reconciliation_summary':
                    $data['data_report'] = $this->accounting_model->get_data_bank_reconciliation_summary($data_filter);
                break;
            case 'bank_reconciliation_detail':
                    $data['data_report'] = $this->accounting_model->get_data_bank_reconciliation_detail($data_filter);
                break;
            default:
                break;
        }

        $this->load->view('report/details/'.$data_filter['type'], $data);
    }

    /**
     * get data dashboard
     * @return json
     */
    public function get_data_dashboard(){
        $data_filter = $this->input->get();

        $data['profit_and_loss_chart'] = $this->accounting_model->get_data_profit_and_loss_chart($data_filter);
        $data['expenses_chart'] = $this->accounting_model->get_data_expenses_chart($data_filter);
        $data['income_chart'] = $this->accounting_model->get_data_income_chart($data_filter);
        $data['sales_chart'] = $this->accounting_model->get_data_sales_chart($data_filter);
        $data['bank_accounts'] = $this->accounting_model->get_data_bank_accounts_dashboard($data_filter);
        $data['convert_status'] = $this->accounting_model->get_data_convert_status_dashboard($data_filter);

        echo json_encode($data);
    }

    /**
     * update reset all data accounting module
     */
    public function reset_data(){
        if (!has_permission('accounting_setting', '', 'delete') && !is_admin() ) {
            access_denied('accounting_setting');
        }

        $success = $this->accounting_model->reset_data();
        if($success == true){
            $message = _l('reset_data_successfully');
            set_alert('success', $message);
        }
        redirect(admin_url('accounting/setting?group=general'));
    }

    /* Change status to account active or inactive / ajax */
    public function change_account_status($id, $status)
    {
        if (has_permission('accounting_chart_of_accounts', '', 'edit')) {
            if ($this->input->is_ajax_request()) {
                $this->accounting_model->change_account_status($id, $status);
            }
        }
    }

    /**
     * item automatic table
     * @return json
     */
    public function item_automatic_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
           
            $select = [
                db_prefix() . 'acc_item_automatics.id as id',
                'rate',
                'description',
            ];
            $where = [];

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_item_automatics';
            $join         = ['LEFT JOIN ' . db_prefix() . 'items ON ' . db_prefix() . 'items.id = ' . db_prefix() . 'acc_item_automatics.item_id',
                            'LEFT JOIN ' . db_prefix() . 'items_groups ON ' . db_prefix() . 'items_groups.id = ' . db_prefix() . 'items.group_id',
                        ];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [db_prefix() . 'items_groups.name as group_name', 'inventory_asset_account', 'income_account', 'expense_account','item_id']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $categoryOutput = $aRow['description'];

                $categoryOutput .= '<div class="row-options">';
                    
                if (has_permission('accounting_setting', '', 'edit')) {
                    $categoryOutput .= '<a href="#" onclick="edit_item_automatic(this); return false;" data-id="'.$aRow['id'].'" data-inventory-asset-account="'.$aRow['inventory_asset_account'].'" data-income-account="'.$aRow['income_account'].'" data-expense-account="'.$aRow['expense_account'].'" data-item-id="'.$aRow['item_id'].'">' . _l('edit') . '</a>';
                }
                if (has_permission('accounting_setting', '', 'delete')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/delete_item_automatic/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;

                $row[] = app_format_money($aRow['rate'], $currency->name);

                $row[] = $aRow['group_name'];

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * add or edit item automatic
     * @return json
     */
    public function item_automatic(){
        $data = $this->input->post();
        if($data['id'] == ''){
            if (!has_permission('accounting_setting', '', 'create')) {
                access_denied('accounting');
            }
            $success = $this->accounting_model->add_item_automatic($data);
            if($success){
                $message = _l('added_successfully', _l('item_automatic'));
            }else {
                $message = _l('add_failure');
            }
        }else{
            if (!has_permission('accounting_setting', '', 'edit')) {
                access_denied('accounting');
            }
            $id = $data['id'];
            unset($data['id']);
            $success = $this->accounting_model->update_item_automatic($data, $id);
            $message = _l('fail');
            if ($success) {
                $message = _l('updated_successfully', _l('item_automatic'));
            }
        }

        echo json_encode(['success' => $success, 'message' => $message]);
        die();
    }

    /**
     * delete item automatic
     * @param  integer $id
     * @return
     */
    public function delete_item_automatic($id)
    {
        if (!has_permission('accounting_setting', '', 'delete')) {
            access_denied('accounting');
        }

        $success = $this->accounting_model->delete_item_automatic($id);
        $message = '';
        if ($success) {
            $message = _l('deleted', _l('item_automatic'));
            set_alert('success', $message);
        } else {
            $message = _l('can_not_delete');
            set_alert('warning', $message);
        }
        redirect(admin_url('accounting/setting?group=mapping_setup'));
    }

    /**
     * transaction bulk action
     */
    public function transaction_bulk_action()
    {
        $total_deleted = 0;
        if ($this->input->post()) {
            $type    = $this->input->post('type');
            $ids       = $this->input->post('ids');

            $is_admin  = is_admin();
            if (is_array($ids)) {
                if($type == 'payment'){
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_convert') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->automatic_payment_conversion($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'payment')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }elseif ($type == 'invoice') {
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_convert') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->automatic_invoice_conversion($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'invoice')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }elseif ($type == 'expense') {
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_convert') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->automatic_expense_conversion($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'expense')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }elseif ($type == 'banking') {
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_delete') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->delete_banking($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'banking')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }elseif ($type == 'payslip') {
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_convert') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->automatic_payslip_conversion($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'payslip')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }elseif ($type == 'purchase_order') {
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_convert') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->automatic_purchase_order_conversion($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'purchase_order')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }elseif ($type == 'purchase_payment') {
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_convert') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->automatic_purchase_payment_conversion($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'purchase_payment')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }elseif ($type == 'stock_import') {
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_convert') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->automatic_stock_import_conversion($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'stock_import')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }elseif ($type == 'stock_export') {
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_convert') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->automatic_stock_export_conversion($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'stock_export')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }elseif ($type == 'loss_adjustment') {
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_convert') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->automatic_loss_adjustment_conversion($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'loss_adjustment')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }elseif ($type == 'opening_stock') {
                    foreach ($ids as $id) {
                        if ($this->input->post('mass_convert') === 'true') {
                            if (has_permission('accounting_transaction', '', 'create')) {
                                if ($this->accounting_model->automatic_opening_stock_conversion($id)) {
                                    $total_deleted++;
                                }
                            }
                        }elseif($this->input->post('mass_delete_convert') === 'true'){
                            if (has_permission('accounting_transaction', '', 'delete')) {
                                if ($this->accounting_model->delete_convert($id, 'opening_stock')) {
                                    $total_deleted++;
                                }
                            }
                        }
                    }
                }
            }
            if ($this->input->post('mass_convert') === 'true') {
                set_alert('success', _l('total_converted', $total_deleted));
            }elseif ($this->input->post('mass_delete_convert') === 'true') {
                set_alert('success', _l('total_convert_deleted', $total_deleted));
            }elseif ($this->input->post('mass_delete') === 'true') {
                set_alert('success', _l('total_deleted', $total_deleted));
            }
        }
    }

    /**
     * journal entry bulk action
     */
    public function journal_entry_bulk_action()
    {
        $total_deleted = 0;
        if ($this->input->post()) {
            $ids       = $this->input->post('ids');
            $is_admin  = is_admin();
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if($this->input->post('mass_delete') === 'true'){
                        if (has_permission('accounting_journal_entry', '', 'delete')) {
                            if ($this->accounting_model->delete_journal_entry($id)) {
                                $total_deleted++;
                            }
                        }
                    }
                }
                
            }
            if ($this->input->post('mass_delete') === 'true') {
                set_alert('success', _l('total_deleted', $total_deleted));
            }
        }
    }

    /**
     * transfer bulk action
     */
    public function transfer_bulk_action()
    {
        $total_deleted = 0;
        if ($this->input->post()) {
            $ids       = $this->input->post('ids');
            $is_admin  = is_admin();
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if($this->input->post('mass_delete') === 'true'){
                        if (has_permission('accounting_transfer', '', 'delete')) {
                            if ($this->accounting_model->delete_transfer($id)) {
                                $total_deleted++;
                            }
                        }
                    }
                }
                
            }
            if ($this->input->post('mass_delete') === 'true') {
                set_alert('success', _l('total_deleted', $total_deleted));
            }
        }
    }

    /**
     * tax mapping table
     * @return json
     */
    public function tax_mapping_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
           
            $select = [
                db_prefix() . 'acc_tax_mappings.id as id',
                'name',
                'taxrate',
            ];
            $where = [];

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_tax_mappings';
            $join         = ['LEFT JOIN ' . db_prefix() . 'taxes ON ' . db_prefix() . 'taxes.id = ' . db_prefix() . 'acc_tax_mappings.tax_id'];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['tax_id', 'payment_account', 'deposit_to', 'expense_deposit_to', 'expense_payment_account']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $categoryOutput = $aRow['tax_id'];

                $categoryOutput .= '<div class="row-options">';
                    
                if (has_permission('accounting_setting', '', 'edit')) {
                    $categoryOutput .= '<a href="#" onclick="edit_tax_mapping(this); return false;" data-id="'.$aRow['id'].'" data-deposit-to="'.$aRow['deposit_to'].'" data-payment-account="'.$aRow['payment_account'].'" data-expense-deposit-to="'.$aRow['expense_deposit_to'].'" data-expense-payment-account="'.$aRow['expense_payment_account'].'" data-tax-id="'.$aRow['tax_id'].'">' . _l('edit') . '</a>';
                }
                if (has_permission('accounting_setting', '', 'delete')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/delete_tax_mapping/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;
                $row[] = $aRow['name'];

                $row[] = $aRow['taxrate'];

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * add or edit tax mapping
     * @return json
     */
    public function tax_mapping(){
        $data = $this->input->post();
        if($data['id'] == ''){
            if (!has_permission('accounting_setting', '', 'create')) {
                access_denied('accounting');
            }
            $success = $this->accounting_model->add_tax_mapping($data);
            if($success){
                $message = _l('added_successfully', _l('tax_mapping'));
            }else {
                $message = _l('add_failure');
            }
        }else{
            if (!has_permission('accounting_setting', '', 'edit')) {
                access_denied('accounting');
            }
            $id = $data['id'];
            unset($data['id']);
            $success = $this->accounting_model->update_tax_mapping($data, $id);
            $message = _l('fail');
            if ($success) {
                $message = _l('updated_successfully', _l('tax_mapping'));
            }
        }

        echo json_encode(['success' => $success, 'message' => $message]);
        die();
    }

    /**
     * delete tax mapping
     * @param  integer $id
     * @return
     */
    public function delete_tax_mapping($id)
    {
        if (!has_permission('accounting_setting', '', 'delete')) {
            access_denied('accounting');
        }

        $success = $this->accounting_model->delete_tax_mapping($id);
        $message = '';
        if ($success) {
            $message = _l('deleted', _l('tax_mapping'));
            set_alert('success', $message);
        } else {
            $message = _l('can_not_delete');
            set_alert('warning', $message);
        }
        redirect(admin_url('accounting/setting?group=mapping_setup'));
    }

    /**
     * accounts bulk action
     */
    public function accounts_bulk_action()
    {
        $total_deleted = 0;
        if ($this->input->post()) {
            $ids       = $this->input->post('ids');
            $is_admin  = is_admin();
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if($this->input->post('mass_delete') === 'true'){
                        if (has_permission('accounting_chart_of_accounts', '', 'delete')) {
                            $success = $this->accounting_model->delete_account($id);
                            if ($success === 'have_transaction') {
                                $message = _l('cannot_delete_transaction_already_exists');
                                set_alert('warning', $message);
                            }elseif ($success) {
                                $total_deleted++;
                            } 
                        }
                    }elseif($this->input->post('mass_activate') === 'true'){
                        if (has_permission('accounting_chart_of_accounts', '', 'edit')) {
                            if ($this->accounting_model->change_account_status($id, 1)) {
                                $total_deleted++;
                            }
                        }
                    }elseif($this->input->post('mass_deactivate') === 'true'){
                        if (has_permission('accounting_chart_of_accounts', '', 'edit')) {
                            if ($this->accounting_model->change_account_status($id, 0)) {
                                $total_deleted++;
                            }
                        }
                    }
                }
                
            }
            if ($this->input->post('mass_delete') === 'true') {
                set_alert('success', _l('total_deleted', $total_deleted));
            }elseif ($this->input->post('mass_activate') === 'true') {
                set_alert('success', _l('total_activate', $total_deleted));
            }elseif ($this->input->post('mass_deactivate') === 'true') {
                set_alert('success', _l('total_deactivate', $total_deleted));
            }
        }
    }

    /**
     * expense category mapping table
     * @return json
     */
    public function expense_category_mapping_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
           
            $select = [
                db_prefix() . 'acc_expense_category_mappings.id as id',
                'name',
                'description',
                'preferred_payment_method',
            ];
            $where = [];

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_expense_category_mappings';
            $join         = ['LEFT JOIN ' . db_prefix() . 'expenses_categories ON ' . db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'acc_expense_category_mappings.category_id'];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['category_id', 'payment_account', 'deposit_to']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $categoryOutput = $aRow['category_id'];

                $categoryOutput .= '<div class="row-options">';
                    
                if (has_permission('accounting_setting', '', 'edit')) {
                    $categoryOutput .= '<a href="#" onclick="edit_expense_category_mapping(this); return false;" data-id="'.$aRow['id'].'" data-deposit-to="'.$aRow['deposit_to'].'" data-payment-account="'.$aRow['payment_account'].'" data-category-id="'.$aRow['category_id'].'" data-preferred-payment-method="'.$aRow['preferred_payment_method'].'">' . _l('edit') . '</a>';
                }

                if (has_permission('accounting_setting', '', 'delete')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/delete_expense_category_mapping/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;
                $row[] = $aRow['name'];

                $row[] = $aRow['description'];

                $checked = '';
                if ($aRow['preferred_payment_method'] == 1) {
                    $checked = 'checked';
                }

                $_data = '<div class="onoffswitch">
                    <input type="checkbox" ' . ((!is_admin() && has_permission('accounting_setting', '', 'edit')) ? 'disabled' : '') . ' data-switch-url="' . admin_url() . 'accounting/change_preferred_payment_method" name="onoffswitch" class="onoffswitch-checkbox" id="c_' . $aRow['id'] . '" data-id="' . $aRow['id'] . '" ' . $checked . '>
                    <label class="onoffswitch-label" for="c_' . $aRow['id'] . '"></label>
                </div>';

                // For exporting
                $_data .= '<span class="hide">' . ($checked == 'checked' ? _l('is_active_export') : _l('is_not_active_export')) . '</span>';
                $row[] = $_data;
                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * add or edit expense category mapping
     * @return json
     */
    public function expense_category_mapping(){
        $data = $this->input->post();
        if($data['id'] == ''){
            if (!has_permission('accounting_setting', '', 'create')) {
                access_denied('accounting');
            }
            $success = $this->accounting_model->add_expense_category_mapping($data);
            if($success){
                $message = _l('added_successfully', _l('expense_category_mapping'));
            }else {
                $message = _l('add_failure');
            }
        }else{
            if (!has_permission('accounting_setting', '', 'edit')) {
                access_denied('accounting');
            }
            $id = $data['id'];
            unset($data['id']);
            $success = $this->accounting_model->update_expense_category_mapping($data, $id);
            $message = _l('fail');
            if ($success) {
                $message = _l('updated_successfully', _l('expense_category_mapping'));
            }
        }

        echo json_encode(['success' => $success, 'message' => $message]);
        die();
    }

    /**
     * delete expense_category mapping
     * @param  integer $id
     * @return
     */
    public function delete_expense_category_mapping($id)
    {
        if (!has_permission('accounting_setting', '', 'delete')) {
            access_denied('accounting');
        }

        $success = $this->accounting_model->delete_expense_category_mapping($id);
        $message = '';
        if ($success) {
            $message = _l('deleted', _l('expense_category_mapping'));
            set_alert('success', $message);
        } else {
            $message = _l('can_not_delete');
            set_alert('warning', $message);
        }
        redirect(admin_url('accounting/setting?group=mapping_setup'));
    }

    /**
     * tax detail report
     * @return view
     */
    public function rp_tax_detail_report(){
        $this->load->model('currencies_model');
        $data['title'] = _l('tax_detail_report');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/tax_detail_report', $data);
    }

    /**
     * tax summary report
     * @return view
     */
    public function rp_tax_summary_report(){
        $this->load->model('currencies_model');
        $data['currency'] = $this->currencies_model->get_base_currency();

        $this->load->model('taxes_model');
        $data['taxes'] = $this->taxes_model->get();

        $data['title'] = _l('tax_summary_report');
        $data['from_date'] = date('Y-m-01');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['to_date'] = date('Y-m-d');
        $this->load->view('report/includes/tax_summary_report', $data);
    }

    /**
     * tax liability report
     * @return view
     */
    public function rp_tax_liability_report(){
        $this->load->model('currencies_model');
        $data['currency'] = $this->currencies_model->get_base_currency();

        $this->load->model('taxes_model');
        $data['taxes'] = $this->taxes_model->get();

        $data['title'] = _l('tax_liability_report');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $this->load->view('report/includes/tax_liability_report', $data);
    }


    /**
     * get data convert status dashboard
     * @return json
     */
    public function get_data_convert_status_dashboard(){
        $data_filter = $this->input->get();

        $data['convert_status'] = $this->accounting_model->get_data_convert_status_dashboard($data_filter);

        echo json_encode($data);
    }

    /**
     * get data income chart
     * @return json
     */
    public function get_data_income_chart(){
        $data_filter = $this->input->get();

        $data['income_chart'] = $this->accounting_model->get_data_income_chart($data_filter);

        echo json_encode($data);
    }

    /**
     * get data sales chart
     * @return json
     */
    public function get_data_sales_chart(){
        $data_filter = $this->input->get();

        $data['sales_chart'] = $this->accounting_model->get_data_sales_chart($data_filter);

        echo json_encode($data);
    }

    /**
     * payment mode mapping table
     * @return json
     */
    public function payment_mode_mapping_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
           
            $select = [
                db_prefix() . 'acc_payment_mode_mappings.id as id',
                'name',
            ];
            $where = [];

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_payment_mode_mappings';
            $join         = ['LEFT JOIN ' . db_prefix() . 'payment_modes ON ' . db_prefix() . 'payment_modes.id = ' . db_prefix() . 'acc_payment_mode_mappings.payment_mode_id'];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['payment_mode_id', 'payment_account', 'deposit_to',  'expense_payment_account', 'expense_deposit_to','description']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $categoryOutput = $aRow['name'];

                $categoryOutput .= '<div class="row-options">';
                    
                if (has_permission('accounting_setting', '', 'edit')) {
                    $categoryOutput .= '<a href="#" onclick="edit_payment_mode_mapping(this); return false;" data-id="'.$aRow['id'].'" data-deposit-to="'.$aRow['deposit_to'].'" data-payment-account="'.$aRow['payment_account'].'" data-expense-deposit-to="'.$aRow['expense_deposit_to'].'" data-expense-payment-account="'.$aRow['expense_payment_account'].'" data-payment-mode-id="'.$aRow['payment_mode_id'].'">' . _l('edit') . '</a>';
                }
                if (has_permission('accounting_setting', '', 'delete')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/delete_payment_mode_mapping/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;

                $row[] = $aRow['description'];

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * add or edit payment mode mapping
     * @return json
     */
    public function payment_mode_mapping(){
        $data = $this->input->post();
        if($data['id'] == ''){
            if (!has_permission('accounting_setting', '', 'create')) {
                access_denied('accounting');
            }
            $success = $this->accounting_model->add_payment_mode_mapping($data);
            if($success){
                $message = _l('added_successfully', _l('payment_mode_mapping'));
            }else {
                $message = _l('add_failure');
            }
        }else{
            if (!has_permission('accounting_setting', '', 'edit')) {
                access_denied('accounting');
            }
            $id = $data['id'];
            unset($data['id']);
            $success = $this->accounting_model->update_payment_mode_mapping($data, $id);
            $message = _l('fail');
            if ($success) {
                $message = _l('updated_successfully', _l('payment_mode_mapping'));
            }
        }

        echo json_encode(['success' => $success, 'message' => $message]);
        die();
    }

    /**
     * delete payment mode mapping
     * @param  integer $id
     * @return
     */
    public function delete_payment_mode_mapping($id)
    {
        if (!has_permission('accounting_setting', '', 'delete')) {
            access_denied('accounting');
        }

        $success = $this->accounting_model->delete_payment_mode_mapping($id);
        $message = '';
        if ($success) {
            $message = _l('deleted', _l('payment_mode_mapping'));
            set_alert('success', $message);
        } else {
            $message = _l('can_not_delete');
            set_alert('warning', $message);
        }
        redirect(admin_url('accounting/setting?group=mapping_setup'));
    }

    /* Change status to payment mode mapping active or inactive / ajax */
    public function change_active_payment_mode_mapping($id, $status)
    {
        if (has_permission('accounting_setting', '', 'edit')) {
            if ($this->input->is_ajax_request()) {
                $this->accounting_model->change_active_payment_mode_mapping($status);
            }
        }
    }

    /* Change status to expense category mapping active or inactive / ajax */
    public function change_active_expense_category_mapping($id, $status)
    {
        if (has_permission('accounting_setting', '', 'edit')) {
            if ($this->input->is_ajax_request()) {
                $this->accounting_model->change_active_expense_category_mapping($status);
            }
        }
    }

    /**
     * account type details table
     * @return json
     */
    public function account_type_details_table(){
        if ($this->input->is_ajax_request()) {
           
            $this->load->model('currencies_model');
            $account_types = $this->accounting_model->get_account_types();

            $account_type_name = [];
            foreach ($account_types as $key => $value) {
                $account_type_name[$value['id']] = $value['name'];
            }

            $currency = $this->currencies_model->get_base_currency();
            $select = [
                'id',
                'name',
            ];

            $where = [];
            $from_date = '';
            $to_date   = '';

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'acc_account_type_details';
            $join         = [];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['account_type_id']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];

                $categoryOutput = $aRow['name'];

                $categoryOutput .= '<div class="row-options">';

                if (has_permission('accounting_setting', '', 'edit')) {
                    $categoryOutput .= '<a href="#" onclick="edit_account_type_detail(' . $aRow['id'] . '); return false;">' . _l('edit') . '</a>';
                }

                if (has_permission('accounting_setting', '', 'delete')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/delete_account_type_detail/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }

                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;
                $row[] = isset($account_type_name[$aRow['account_type_id']]) ? $account_type_name[$aRow['account_type_id']] : '';

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     *
     *  add or edit account type detail
     *  @param  integer  $id     The identifier
     *  @return view
     */
    public function account_type_detail()
    {
        if (!has_permission('accounting_setting', '', 'edit') && !has_permission('accounting_setting', '', 'create')) {
            access_denied('accounting');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $data['note'] = $this->input->post('note', false);
            $message = '';
            if ($data['id'] == '') {
                if (!has_permission('accounting_setting', '', 'create')) {
                    access_denied('accounting');
                }
                $success = $this->accounting_model->add_account_type_detail($data);
                if ($success) {
                    $message = _l('added_successfully', _l('account_type_detail'));
                }else {
                    $message = _l('add_failure');
                }
            } else {
                if (!has_permission('accounting_setting', '', 'edit')) {
                    access_denied('accounting');
                }
                $id = $data['id'];
                unset($data['id']);
                $success = $this->accounting_model->update_account_type_detail($data, $id);
                if ($success) {
                    $message = _l('updated_successfully', _l('account_type_detail'));
                }else {
                    $message = _l('updated_fail');
                }
            }

            echo json_encode(['success' => $success, 'message' => $message]);
            die();
        }
    }

    /**
     * delete account type detail
     * @param  integer $id
     * @return
     */
    public function delete_account_type_detail($id)
    {
        if (!has_permission('accounting_setting', '', 'delete')) {
            access_denied('accounting_setting');
        }
        $success = $this->accounting_model->delete_account_type_detail($id);
        $message = '';
        
        if ($success === 'have_account') {
            $message = _l('cannot_delete_account_already_exists');
            set_alert('warning', $message);
        }elseif ($success) {
            $message = _l('deleted', _l('account_type_detail'));
            set_alert('success', $message);
        } else {
            $message = _l('can_not_delete');
            set_alert('warning', $message);
        }
        redirect(admin_url('accounting/setting?group=account_type_details'));
    }

    /**
     * get data account type detail
     * @param  integer $id 
     * @return json     
     */
    public function get_data_account_type_detail($id){
        $account_type_detail = $this->accounting_model->get_data_account_type_details($id);

        echo json_encode($account_type_detail);
    }

    /**
     * journal entry export
     * @param  integer $id
     */
    public function journal_entry_export($id){
        $this->delete_error_file_day_before(1,ACCOUTING_EXPORT_XLSX); 

        $this->load->model('currencies_model');

        $currency = $this->currencies_model->get_base_currency();

        $header = [];
        $header = [ _l('asp_order'), _l('asp_date'), _l('asp_creation_date'), _l('asp_invoice_number'), _l('asp_reference'), _l('asp_book'), _l('asp_account'), _l('asp_nif'), _l('asp_desc'), _l('asp_total_invoice'), _l('asp_subtotal_1'), _l('asp_vat_1'), _l('asp_subtotal_2'), _l('asp_vat_2'), _l('asp_subtotal_3'), _l('asp_vat_3'),  _l('asp_subtotal_4'), _l('asp_vat_4'),  _l('asp_subtotal_5'), _l('asp_vat_5'), _l('asp_libro_contrapartida'), _l('asp_cuenta_contrapartida'), _l('asp_lote_a_contabilizar')];

        $accounts = $this->accounting_model->get_accounts();

        $account_name = [];
        foreach ($accounts as $key => $value) {
            $account_name[$value['id']] = $value['name'];
        }

        $journal_entry = $this->accounting_model->get_journal_entry($id);

        if(!class_exists('XLSXWriter')){
            require_once(module_dir_path(ACCOUNTING_MODULE_NAME).'/assets/plugins/XLSXWriter/xlsxwriter.class.php');             
        }

        $header = [ 
           1 => _l('acc_account'), 
           2 => _l('debit'), 
           3 => _l('credit'), 
           4 => _l('description'), 
        ];

        $widths_arr = array();
       
        for($i = 1; $i <= count($header); $i++ ){
            if($i == 1){
                $widths_arr[] = 60;
            }else if($i == 8){
                $widths_arr[] = 60;
            }else{
                $widths_arr[] = 40;
            }
        }

        $writer = new XLSXWriter();
        $writer->writeSheetRow('Sheet1', []);
        $writer->writeSheetRow('Sheet1', [1 => _l('journal_date').': '. _d($journal_entry->journal_date), ]);
        $writer->writeSheetRow('Sheet1', [1 => _l('number').': '. $journal_entry->number, ]);
        $writer->writeSheetRow('Sheet1', [1 => _l('description').': '. $journal_entry->Description, ]);
        $writer->writeSheetRow('Sheet1', []);

        
        $style3 = array('fill' => '#C65911', 'height'=>25, 'font-style'=>'bold', 'color' => '#FFFFFF', 'border'=>'left,right,top,bottom', 'border-color' => '#FFFFFF', 'font-size' => 15, 'font' => 'Calibri');
        $style1 = array('fill' => '#F8CBAD', 'height'=>25, 'border'=>'left,right,top,bottom', 'border-color' => '#FFFFFF', 'font-size' => 15, 'font' => 'Calibri', 'color' => '#000000');
        $style2 = array('fill' => '#FCE4D6', 'height'=>25, 'border'=>'left,right,top,bottom', 'border-color' => '#FFFFFF', 'font-size' => 15, 'font' => 'Calibri', 'color' => '#000000');

        $writer->writeSheetRow('Sheet1', $header, $style3);

        foreach($journal_entry->details as $k => $row){
            $row['account'] = isset($account_name[$row['account']]) ? $account_name[$row['account']] : $row['account'];
            $row['debit'] =$row['debit'] > 0 ? app_format_money($row['debit'], $currency->name) : '';
            $row['credit'] =$row['credit'] > 0 ? app_format_money($row['credit'], $currency->name) : '';
            if(($k%2) == 0){
                $writer->writeSheetRow('Sheet1', $row , $style1);
            }else{
                $writer->writeSheetRow('Sheet1', $row , $style2);
            }
        }

        $writer->writeSheetRow('Sheet1', [1 => _l('total'), 2 => app_format_money($journal_entry->amount, $currency->name), 3 => app_format_money($journal_entry->amount, $currency->name), 4 => ''], $style3);

        $filename = 'journal_entry_'.time().'.xlsx';
        $writer->writeToFile(str_replace($filename, ACCOUTING_EXPORT_XLSX.$filename, $filename));
        $this->download_xlsx_file(ACCOUTING_EXPORT_XLSX.$filename);
        die();
    }

    /**
     * download xlsx file
     * @param  string $filename
     */
    public function download_xlsx_file($filename){
        $file = $filename;
        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        ob_end_clean();
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($file) . "\"");
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        readfile($file);
        unlink($file);
        exit();
    }

    /**
     * delete error file day before
     * @param  string $before_day  
     * @param  string $folder_name 
     * @return boolean              
     */
    public function delete_error_file_day_before($before_day ='', $folder_name='')
    {
        if($before_day != ''){
            $day = $before_day;
        }else{
            $day = '7';
        }

        if($folder_name != ''){
            $folder = $folder_name;
        }else{
            $folder = ACCOUTING_IMPORT_ITEM_ERROR;
        }

        //Delete old file before 7 day
        $date = date_create(date('Y-m-d H:i:s'));
        date_sub($date,date_interval_create_from_date_string($day." days"));
        $before_7_day = strtotime(date_format($date,"Y-m-d H:i:s"));

        foreach(glob($folder . '*') as $file) {

            $file_arr = explode("/",$file);
            $filename = array_pop($file_arr);

            if(file_exists($file)) {
                //don't delete index.html file
                if($filename != 'index.html'){
                    $file_name_arr = explode("_",$filename);
                    $date_create_file = array_pop($file_name_arr);
                    $date_create_file =  str_replace('.xlsx', '', $date_create_file);

                    if((float)$date_create_file <= (float)$before_7_day){
                        unlink($folder.$filename);
                    }
                }
            }
        }
        return true;
    }

    /* Change status to preferred payment method on or off / ajax */
    public function change_preferred_payment_method($id, $status)
    {
        if (has_permission('staff', '', 'edit')) {
            if ($this->input->is_ajax_request()) {
                $this->accounting_model->change_preferred_payment_method($id, $status);
            }
        }
    }

    /**
     * payslips table
     * @return json
     */
    public function payslips_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1',
                'payslip_name',
                'payslip_template_id',
                'payslip_month',
                'staff_id_created',
                'date_created',
                'payslip_status',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'hrp_payslips.id and ' . db_prefix() . 'acc_account_history.rel_type = "payslip") as count_account_historys',
                'id',
            ];

            $where = [];

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'hrp_payslips.id and ' . db_prefix() . 'acc_account_history.rel_type = "payslip") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'hrp_payslips.id and ' . db_prefix() . 'acc_account_history.rel_type = "payslip") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'hrp_payslips.id and ' . db_prefix() . 'acc_account_history.rel_type = "payslip") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'hrp_payslips.id and ' . db_prefix() . 'acc_account_history.rel_type = "payslip") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }
            
            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'hrp_payslips.payslip_month >= "' . $from_date . '" and ' . db_prefix() . 'hrp_payslips.payslip_month <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'hrp_payslips.payslip_month >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'hrp_payslips.payslip_month <= "' . $to_date . '")');
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'hrp_payslips';
            $join         = [
            ];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, []);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';
                //load by manager
                if(!is_admin() && !has_permission('hrp_payslip','','view')){
                    //View own
                    $code = '<a href="' . admin_url('hr_payroll/view_payslip_detail_v2/' . $aRow['id']) . '" target="_blank">' . $aRow['payslip_name'] . '</a>';
                    $code .= '<div class="row-options">';
                }else{
                    //admin or view global
                    $code = '<a href="' . admin_url('hr_payroll/view_payslip_detail/' . $aRow['id']) . '" target="_blank">' . $aRow['payslip_name'] . '</a>';
                    $code .= '<div class="row-options">';
                }

                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['payslip_month'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))) {
                        $code .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="payslip-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="payslip">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $code .= '<a href="#" onclick="convert(this); return false;" id="payslip-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="payslip">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $code .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'payslip\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }

                $code .= '</div>';

                $row[] = $code;

                $row[] = get_payslip_template_name($aRow['payslip_template_id']);

                $row[] =  date('m-Y', strtotime($aRow['payslip_month']));

                $_data = '<a href="' . admin_url('staff/profile/' . $aRow['staff_id_created']) . '" target="_blank">' . staff_profile_image($aRow['staff_id_created'], [
                'staff-profile-image-small',
                ]) . '</a>';
                $_data .= ' <a href="' . admin_url('staff/profile/' . $aRow['staff_id_created']) . '" target="_blank">' . get_staff_full_name($aRow['staff_id_created']) . '</a>';

                $row[] = $_data;
                $row[] = _dt($aRow['date_created']);

                if($aRow['payslip_status'] == 'payslip_closing'){
                    $row[] = ' <span class="label label-success "> '._l($aRow['payslip_status']).' </span>';
                }else{
                    $row[] = ' <span class="label label-primary"> '._l($aRow['payslip_status']).' </span>';
                }

                $status_name = _l('has_not_been_converted');
                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                } 
                $row[] = '<span class="label label-' . $label_class . ' s-status payslip-status-' . $aRow['id'] . '">' . $status_name . '</span>';
                
                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['payslip_month'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-type' => 'payslip',
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * purchase order table
     * @return json
     */
    public function purchase_order_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1',
                'pur_order_number',
                'order_date',
                db_prefix().'pur_orders.vendor as vendor',
                'subtotal',
                'total_tax',
                'total',
                'number',
                'expense_convert',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_orders.id and ' . db_prefix() . 'acc_account_history.rel_type = "purchase_order") as count_account_historys',
                db_prefix() .'pur_orders.id as id',
            ];

            $where = [];

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_orders.id and ' . db_prefix() . 'acc_account_history.rel_type = "purchase_order") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_orders.id and ' . db_prefix() . 'acc_account_history.rel_type = "purchase_order") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_orders.id and ' . db_prefix() . 'acc_account_history.rel_type = "purchase_order") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_orders.id and ' . db_prefix() . 'acc_account_history.rel_type = "purchase_order") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }
            
            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'pur_orders.order_date >= "' . $from_date . '" and ' . db_prefix() . 'pur_orders.order_date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'pur_orders.order_date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'pur_orders.order_date <= "' . $to_date . '")');
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'pur_orders';
            $join         = [
                'LEFT JOIN '.db_prefix().'pur_vendor ON '.db_prefix().'pur_vendor.userid = '.db_prefix().'pur_orders.vendor',
                'LEFT JOIN '.db_prefix().'departments ON '.db_prefix().'departments.departmentid = '.db_prefix().'pur_orders.department',
                'LEFT JOIN '.db_prefix().'projects ON '.db_prefix().'projects.id = '.db_prefix().'pur_orders.project',
                'LEFT JOIN '.db_prefix().'expenses ON '.db_prefix().'expenses.id = '.db_prefix().'pur_orders.expense_convert',
            ];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['company','pur_order_number','expense_convert',db_prefix().'projects.name as project_name',db_prefix().'departments.name as department_name', db_prefix().'expenses.id as expense_id', db_prefix().'expenses.expense_name as expense_name']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

                $numberOutput = '';
    
                $numberOutput = '<a href="' . admin_url('purchase/purchase_order/' . $aRow['id']) . '"  onclick="init_pur_order(' . $aRow['id'] . '); return false;" >'.$aRow['pur_order_number']. '</a>';
                
                $numberOutput .= '<div class="row-options">';

                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['order_date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))) {
                        $numberOutput .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="purchase-order-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="purchase_order">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $numberOutput .= '<a href="#" onclick="convert(this); return false;" id="purchase-order-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="purchase_order">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $numberOutput .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'purchase_order\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }

                $numberOutput .= '</div>';

                $row[] = $numberOutput;

                $row[] = _d($aRow['order_date']);

                $row[] = '<a href="' . admin_url('purchase/vendor/' . $aRow['vendor']) . '" >' .  $aRow['company'] . '</a>';

                $row[] = app_format_money($aRow['subtotal'], $currency->name);

                $row[] = app_format_money($aRow['total_tax'], $currency->name);

                $row[] = app_format_money($aRow['total'], $currency->name);

                $paid = $aRow['total'] - purorder_inv_left_to_pay($aRow['id']);

                $percent = 0;

                if($aRow['total'] > 0){

                    $percent = ($paid / $aRow['total'] ) * 100;

                }

                $row[] = '<div class="progress">

                              <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40"

                              aria-valuemin="0" aria-valuemax="100" style="width:'.round($percent).'%">

                               ' .round($percent).' % 

                              </div>

                            </div>';

                if($aRow['expense_convert'] == 0){
                    $row[] = '';
                }else{
                    if($aRow['expense_name'] != ''){
                        $row[] = '<a href="'.admin_url('expenses/list_expenses/'.$aRow['expense_convert']).'">#'.$aRow['expense_id'].' - '. $aRow['expense_name'].'</a>';
                    }else{
                        $row[] = '<a href="'.admin_url('expenses/list_expenses/'.$aRow['expense_convert']).'">#'.$aRow['expense_id'].'</a>';
                    }
                }

                $status_name = _l('has_not_been_converted');
                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                } 

                $row[] = '<span class="label label-' . $label_class . ' s-status purchase_order-status-' . $aRow['id'] . '">' . $status_name . '</span>';
                
                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['order_date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-type' => 'purchase_order',
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * stock import table
     * @return json
     */
    public function stock_import_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1',
                'goods_receipt_code',
                'date_c',
                'total_tax_money', 
                'total_goods_money',
                'value_of_inventory',
                'total_money',
                'approval',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'goods_receipt.id and ' . db_prefix() . 'acc_account_history.rel_type = "stock_import") as count_account_historys',
                'id',
            ];

            $where = [];

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'goods_receipt.id and ' . db_prefix() . 'acc_account_history.rel_type = "stock_import") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'goods_receipt.id and ' . db_prefix() . 'acc_account_history.rel_type = "stock_import") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'goods_receipt.id and ' . db_prefix() . 'acc_account_history.rel_type = "stock_import") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'goods_receipt.id and ' . db_prefix() . 'acc_account_history.rel_type = "stock_import") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }
            
            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'goods_receipt.date_c >= "' . $from_date . '" and ' . db_prefix() . 'goods_receipt.date_c <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'goods_receipt.date_c >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'goods_receipt.date_c <= "' . $to_date . '")');
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'goods_receipt';
            $join         = [
            ];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['date_add','goods_receipt_code', 'supplier_code']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

                $name = '<a href="' . admin_url('warehouse/edit_purchase/' . $aRow['id'] ).'">' . $aRow['goods_receipt_code'] . '</a>';

                $name .= '<div class="row-options">';

                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date_c'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))) {
                        $name .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="stock-import-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="stock_import">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $name .= '<a href="#" onclick="convert(this); return false;" id="stock-import-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="stock_import">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $name .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'stock_import\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }
                
                $name .= '</div>';

                $row[] = $name;

                $row[] =  _d($aRow['date_c']);

                $row[] = app_format_money((float)$aRow['total_tax_money'],'');

                $row[] = app_format_money((float)$aRow['total_goods_money'],'');

                $row[] = app_format_money((float)$aRow['value_of_inventory'],'');

                $row[] = app_format_money((float)$aRow['total_money'],'');

                if($aRow['approval'] == 1){
                    $row[] = '<span class="label label-tag tag-id-1 label-tab1"><span class="tag">'._l('approved').'</span><span class="hide">, </span></span>&nbsp';
                }elseif($aRow['approval'] == 0){
                    $row[] = '<span class="label label-tag tag-id-1 label-tab2"><span class="tag">'._l('not_yet_approve').'</span><span class="hide">, </span></span>&nbsp';
                }elseif($aRow['approval'] == -1){
                    $row[] = '<span class="label label-tag tag-id-1 label-tab3"><span class="tag">'._l('reject').'</span><span class="hide">, </span></span>&nbsp';
                }

                $status_name = _l('has_not_been_converted');
                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                } 
                $row[] = '<span class="label label-' . $label_class . ' s-status stock-import-status-' . $aRow['id'] . '">' . $status_name . '</span>';
                
                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date_c'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-type' => 'stock_import',
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * stock export table
     * @return json
     */
    public function stock_export_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1',
                'goods_delivery_code',
                'customer_code',
                'date_add',
                'invoice_id',
                'approval',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'goods_delivery.id and ' . db_prefix() . 'acc_account_history.rel_type = "stock_export") as count_account_historys',
                'id',
            ];

            $where = [];

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'goods_delivery.id and ' . db_prefix() . 'acc_account_history.rel_type = "stock_export") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'goods_delivery.id and ' . db_prefix() . 'acc_account_history.rel_type = "stock_export") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'goods_delivery.id and ' . db_prefix() . 'acc_account_history.rel_type = "stock_export") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'goods_delivery.id and ' . db_prefix() . 'acc_account_history.rel_type = "stock_export") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }
            
            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'goods_delivery.date_c >= "' . $from_date . '" and ' . db_prefix() . 'goods_delivery.date_c <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'goods_delivery.date_c >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'goods_delivery.date_c <= "' . $to_date . '")');
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'goods_delivery';
            $join         = [
            ];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['date_add','date_c','goods_delivery_code','total_money']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

                $name = '<a href="' . admin_url('warehouse/edit_delivery/' . $aRow['id'] ).'">' . $aRow['goods_delivery_code'] . '</a>';

                $name .= '<div class="row-options">';

                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date_c'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))) {
                        $name .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="stock-export-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="stock_export">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $name .= '<a href="#" onclick="convert(this); return false;" id="stock-export-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="stock_export">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $name .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'stock_export\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }

                $name .= '</div>';

                $row[] = $name;

                $_data = '';
                if($aRow['customer_code']){
                    $this->db->where(db_prefix() . 'clients.userid', $aRow['customer_code']);
                    $client = $this->db->get(db_prefix() . 'clients')->row();
                    if($client){
                        $_data = $client->company;
                    }

                }

                $row[] = $_data;

                $row[] =  _d($aRow['date_c']);

                $_data = '';

                if($aRow['invoice_id']){
                   $_data = format_invoice_number($aRow['invoice_id']).get_invoice_company_projecy($aRow['invoice_id']);
                }

                $row[] = $_data;

                if($aRow['approval'] == 1){
                    $row[] = '<span class="label label-tag tag-id-1 label-tab1"><span class="tag">'._l('approved').'</span><span class="hide">, </span></span>&nbsp';
                }elseif($aRow['approval'] == 0){
                    $row[] = '<span class="label label-tag tag-id-1 label-tab2"><span class="tag">'._l('not_yet_approve').'</span><span class="hide">, </span></span>&nbsp';
                }elseif($aRow['approval'] == -1){
                    $row[] = '<span class="label label-tag tag-id-1 label-tab3"><span class="tag">'._l('reject').'</span><span class="hide">, </span></span>&nbsp';
                }

                $status_name = _l('has_not_been_converted');
                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                } 
                $row[] = '<span class="label label-' . $label_class . ' s-status stock-export-status-' . $aRow['id'] . '">' . $status_name . '</span>';
                
                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date_c'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-type' => 'stock_export',
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * loss adjustment table
     * @return json
     */
    public function loss_adjustment_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();

            $time_filter = $this->input->post('time_filter');
            $date_create = $this->input->post('date_create');
            $type_filter = $this->input->post('type_filter');
            $status_filter = $this->input->post('status_filter');

            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1',
                'time',
                'type',
                'status',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'wh_loss_adjustment.id and ' . db_prefix() . 'acc_account_history.rel_type = "loss_adjustment") as count_account_historys',
                'id',
            ];

            $where = [];

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'wh_loss_adjustment.id and ' . db_prefix() . 'acc_account_history.rel_type = "loss_adjustment") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'wh_loss_adjustment.id and ' . db_prefix() . 'acc_account_history.rel_type = "loss_adjustment") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'wh_loss_adjustment.id and ' . db_prefix() . 'acc_account_history.rel_type = "loss_adjustment") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'wh_loss_adjustment.id and ' . db_prefix() . 'acc_account_history.rel_type = "loss_adjustment") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }
            
            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'wh_loss_adjustment.date_create >= "' . $from_date . '" and ' . db_prefix() . 'wh_loss_adjustment.date_create <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'wh_loss_adjustment.date_create >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'wh_loss_adjustment.date_create <= "' . $to_date . '")');
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'wh_loss_adjustment';
            $join         = [
            ];
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, []);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

                $name = _l($aRow['type']);
                $name .= '<div class="row-options">';

                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date_create'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))) {
                        $name .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="loss-adjustment-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="loss_adjustment">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $name .= '<a href="#" onclick="convert(this); return false;" id="loss-adjustment-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="loss_adjustment">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $name .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'loss_adjustment\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }
                $name .= '</div>';
                $row[] = $name;

                $row[] = _dt($aRow['time']);

                $status = '';
                if ((int) $aRow['status'] == 0) {
                    $status = '<div class="btn btn-warning" >' . _l('draft') . '</div>';
                } elseif ((int) $aRow['status'] == 1) {
                    $status = '<div class="btn btn-success" >' . _l('Adjusted') . '</div>';
                } elseif((int) $aRow['status'] == -1){

                    $status = '<div class="btn btn-danger" >' . _l('reject') . '</div>';
                }

                $row[] = $status;

                $status_name = _l('has_not_been_converted');
                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                } 
                $row[] = '<span class="label label-' . $label_class . ' s-status stock-export-status-' . $aRow['id'] . '">' . $status_name . '</span>';
                
                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date_create'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-type' => 'loss_adjustment',
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * update payslip automatic conversion
     */
    public function update_payslip_automatic_conversion(){
        if (!has_permission('accounting_setting', '', 'edit') && !is_admin()) {
            access_denied('accounting_setting');
        }
        $data = $this->input->post();
        $success = $this->accounting_model->update_payslip_automatic_conversion($data);
        if($success == true){
            $message = _l('updated_successfully', _l('setting'));
            set_alert('success', $message);
        }
        redirect(admin_url('accounting/setting?group=mapping_setup&tab=payslip'));
    }

    /**
     * opening stock table
     * @return json
     */
    public function opening_stock_table()
    {
        if ($this->input->is_ajax_request()) {
            $acc_first_month_of_financial_year = get_option('acc_first_month_of_financial_year');

            $date_financial_year = date('Y-m-d', strtotime($acc_first_month_of_financial_year . ' 01 '.date('Y')));

            $this->load->model('warehouse/warehouse_model');
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1',
                'commodity_code',
                'description',
                'sku_code',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'items.id and ' . db_prefix() . 'acc_account_history.rel_type = "opening_stock" and ' . db_prefix() . 'acc_account_history.date >= "'.$date_financial_year.'") as count_account_historys',
                'id',
            ];

            $where = [];

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'items.id and ' . db_prefix() . 'acc_account_history.rel_type = "opening_stock" and ' . db_prefix() . 'acc_account_history.date >= "'.$date_financial_year.'") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'items.id and ' . db_prefix() . 'acc_account_history.rel_type = "opening_stock" and ' . db_prefix() . 'acc_account_history.date >= "'.$date_financial_year.'") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'items.id and ' . db_prefix() . 'acc_account_history.rel_type = "opening_stock" and ' . db_prefix() . 'acc_account_history.date >= "'.$date_financial_year.'") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'items.id and ' . db_prefix() . 'acc_account_history.rel_type = "opening_stock" and ' . db_prefix() . 'acc_account_history.date >= "'.$date_financial_year.'") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }
            
            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'items';
            $join         = [
            ];

            $result = $this->accounting_model->get_opening_stock_data_tables($aColumns, $sIndexColumn, $sTable, $join, $where, []);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

                $code = '<a href="' . admin_url('warehouse/view_commodity_detail/' . $aRow['id']) . '">' . $aRow['commodity_code'] . '</a>';
                $code .= '<div class="row-options">';

                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && ($acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))) {
                        $code .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="opening-stock-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="opening_stock" data-amount="'.$aRow['opening_stock'].'">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $code .= '<a href="#" onclick="convert(this); return false;" id="opening-stock-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="opening_stock" data-amount="'.$aRow['opening_stock'].'">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $code .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'opening_stock\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }

                $code .= '</div>';

                $row[] = $code;

                $inventory = $this->warehouse_model->check_inventory_min($aRow['id']);

                if ($inventory) {
                    $row[] = '<a href="#" onclick="show_detail_item(this);return false;" data-name="' . $aRow['description'] . '"  data-commodity_id="' . $aRow['id'] . '"  >' . $aRow['description'] . '</a>';
                } else {

                    $row[] = '<a href="#" class="text-danger"  onclick="show_detail_item(this);return false;" data-name="' . $aRow['description'] . '" data-warehouse_id="' . $aRow['warehouse_id'] . '" data-commodity_id="' . $aRow['id'] . '"  >' . $aRow['description'] . '</a>';
                    
                }

                $row[] = '<span class="label label-tag tag-id-1"><span class="tag">' . $aRow['sku_code'] . '</span><span class="hide">, </span></span>&nbsp';
                $row[] = app_format_money($aRow['opening_stock'], $currency->name);

                $status_name = _l('has_not_been_converted');
                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                } 
                $row[] = '<span class="label label-' . $label_class . ' s-status stock-export-status-' . $aRow['id'] . '">' . $status_name . '</span>';
                
                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && ($acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-type' => 'opening_stock',
                        'data-amount' => $aRow['opening_stock'],
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * update warehouse automatic conversion
     */
    public function update_warehouse_automatic_conversion(){
        if (!has_permission('accounting_setting', '', 'edit') && !is_admin()) {
            access_denied('accounting_setting');
        }
        $data = $this->input->post();
        $success = $this->accounting_model->update_warehouse_automatic_conversion($data);
        if($success == true){
            $message = _l('updated_successfully', _l('setting'));
            set_alert('success', $message);
        }
        redirect(admin_url('accounting/setting?group=mapping_setup&tab=warehouse'));
    }
    
    /**
     * purchase payment table
     * @return json
     */
    public function purchase_payment_table()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $acc_closing_date = '';
            if(get_option('acc_close_the_books') == 1){
                $acc_closing_date = get_option('acc_closing_date');
            }
            $select = [
                '1', // bulk actions
                db_prefix() . 'pur_invoice_payment.id as id',
                'amount',
                db_prefix() . 'payment_modes.name as name',
                db_prefix() . 'pur_invoices.pur_order',
                db_prefix() .'pur_invoice_payment.date as date',
                '(select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_invoice_payment.id and ' . db_prefix() . 'acc_account_history.rel_type = "purchase_payment") as count_account_historys'
            ];
            $where = [];
            //array_push($where, 'AND (' . db_prefix() . 'pur_invoices.pur_order is not null)');
            
            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if($value == 'converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_invoice_payment.id and ' . db_prefix() . 'acc_account_history.rel_type = "purchase_payment") > 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_invoice_payment.id and ' . db_prefix() . 'acc_account_history.rel_type = "purchase_payment") > 0)';
                        }
                    }

                    if($value == 'has_not_been_converted'){
                        if($where_status != ''){
                            $where_status .= ' or ((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_invoice_payment.id and ' . db_prefix() . 'acc_account_history.rel_type = "purchase_payment") = 0)';
                        }else{
                            $where_status .= '((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_invoice_payment.id and ' . db_prefix() . 'acc_account_history.rel_type = "purchase_payment") = 0)';
                        }
                    }
                }

                if($where_status != ''){
                    array_push($where, 'AND ('. $where_status . ')');
                }
            }

            $from_date = '';
            $to_date   = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'pur_invoice_payment.date >= "' . $from_date . '" and ' . db_prefix() . 'pur_invoice_payment.date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'pur_invoice_payment.date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'pur_invoice_payment.date <= "' . $to_date . '")');
            }

            $aColumns     = $select;
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'pur_invoice_payment';
            $join         = ['LEFT JOIN ' . db_prefix() . 'payment_modes ON ' . db_prefix() . 'payment_modes.id = ' . db_prefix() . 'pur_invoice_payment.paymentmode',
                            'LEFT JOIN ' . db_prefix() . 'acc_account_history ON ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_invoice_payment.id and ' . db_prefix() . 'acc_account_history.rel_id = "purchase_payment"',
                            'LEFT JOIN ' . db_prefix() . 'pur_invoices ON ' . db_prefix() . 'pur_invoices.id = ' . db_prefix() . 'pur_invoice_payment.pur_invoice',
                        ];

            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['paymentmode', db_prefix() . 'pur_invoices.pur_order']);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row   = [];
                $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

                $categoryOutput = _d($aRow['date']);

                $categoryOutput .= '<div class="row-options">';
                if ($aRow['count_account_historys'] == 0) {
                    if (has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))) {
                        $categoryOutput .= '<a href="#" onclick="convert(this); return false;" class="text-success" id="purchase-payment-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="purchase_payment" data-amount="'.$aRow['amount'].'">' . _l('acc_convert') . '</a>';
                    }
                }else{
                    if (has_permission('accounting_transaction', '', 'edit')) {
                        $categoryOutput .= '<a href="#" onclick="convert(this); return false;" id="purchase-payment-id-'.$aRow['id'].'" data-id="'.$aRow['id'].'" data-type="purchase_payment" data-amount="'.$aRow['amount'].'">' . _l('edit') . '</a>';
                    }
                    if (has_permission('accounting_transaction', '', 'delete')) {
                        $categoryOutput .= ' | <a href="#" onclick="delete_convert('.$aRow['id'].', \'purchase_payment\'); return false;" class="text-danger">' . _l('delete') . '</a>';
                    }
                }



                $categoryOutput .= '</div>';
                $row[] = $categoryOutput;

                $row[] = app_format_money($aRow['amount'], $currency->name);

                $row[] = $aRow['name'];

                $row[] = '<a href="'.admin_url('purchase/purchase_order/'.$aRow[db_prefix().'pur_invoices.pur_order']).'">'.get_pur_order_subject($aRow[ db_prefix().'pur_invoices.pur_order']).'</a>';

                $status_name = _l('has_not_been_converted');

                $label_class = 'default';

                if ($aRow['count_account_historys'] > 0) {
                    $label_class = 'success';
                    $status_name = _l('acc_converted');
                } 

                $row[] = '<span class="label label-' . $label_class . ' s-status payment-status-' . $aRow['id'] . '">' . $status_name . '</span>';

                $options = '';
                if($aRow['count_account_historys'] == 0 && has_permission('accounting_transaction', '', 'create') && (($acc_closing_date != '' && strtotime($acc_closing_date) <= strtotime($aRow['date'])) || $acc_closing_date == '' || strtotime(date('Y-m-d')) <= strtotime($acc_closing_date))){
                    $options = icon_btn('#', 'share', 'btn-success', [
                        'title' => _l('acc_convert'),
                        'data-id' =>$aRow['id'],
                        'data-amount' => $aRow['amount'],
                        'data-type' => 'purchase_payment',
                        'onclick' => 'convert(this); return false;'
                    ]);
                }

                $row[] =  $options;

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * update purchase automatic conversion
     */
    public function update_purchase_automatic_conversion(){
        if (!has_permission('accounting_setting', '', 'edit') && !is_admin()) {
            access_denied('accounting_setting');
        }
        $data = $this->input->post();
        $success = $this->accounting_model->update_purchase_automatic_conversion($data);
        if($success == true){
            $message = _l('updated_successfully', _l('setting'));
            set_alert('success', $message);
        }
        redirect(admin_url('accounting/setting?group=mapping_setup&tab=purchase'));
    }

    /**
     * Budget
     * @return view
     */
    public function budget(){
        if ($this->input->post()) {
            $data = $this->input->post();
            $message = '';

            if (!has_permission('accounting_budget', '', 'edit')) {
                access_denied('accounting_budget');
            }

            $success = $this->accounting_model->update_budget_detail($data);
            if ($success) {
                $message = _l('updated_successfully', _l('budget'));
            }

            echo json_encode([
                'success' => $success,
                'message' => $message,
            ]);
            die();
        }
        if (!has_permission('accounting_budget', '', 'view')) {
            access_denied('budget');
        }

        $data['budgets'] = $this->accounting_model->get_budgets();

        if(count($data['budgets']) > 0){
            $data_fill = [];
            $data_fill['budget'] = $data['budgets'][0]['id'];
            $data_fill['view_type'] = 'monthly';

            $data['nestedheaders'] = $this->accounting_model->get_nestedheaders_budget($data['budgets'][0]['id'], 'monthly');
            $data['columns'] = $this->accounting_model->get_columns_budget($data['budgets'][0]['id'], 'monthly');
            $data['data_budget'] = $this->accounting_model->get_data_budget($data_fill);
        }else{
            $data['nestedheaders'] = [];
            $data['columns'] = [];
            $data['data_budget'] =[];
            $data['hide_handson'] = 'true';
        }

        $data['title'] = _l('budget');
        $this->load->view('budget/manage', $data);
    }

    /**
     * Gets the data budget.
     * @return json data budget
     */
    public function get_data_budget() {
        $data = $this->input->post();
        
        $data_budget = $this->accounting_model->get_data_budget($data);
        $nestedheaders = $this->accounting_model->get_nestedheaders_budget($data['budget'], $data['view_type']);
        $columns = $this->accounting_model->get_columns_budget($data['budget'], $data['view_type']);
        echo json_encode([
            'columns' => $columns,
            'nestedheaders' => $nestedheaders,
            'data_budget' => $data_budget,
        ]);
        die();
    }

     /**
     * Add budget.
     * @return json data budget
     */
    public function add_budget() {
        $data = $this->input->post();

        $budget = $this->accounting_model->add_budget($data);
        $budget_id = '';
        $success = false;
        $message = _l('add_failure');
        $name = $data['year'].' - '. _l($data['type']);

        if($budget){
            $message = _l('added_successfully', _l('acc_account'));
            $success = true;
            $budget_id = $budget;
        }
        echo json_encode([
            'name' => $name,
            'id' => $budget_id,
            'success' => $success,
            'message' => $message
        ]);
        die();
    }

     /**
     * check budget.
     * @return json data budget
     */
    public function check_budget() {
        $data = $this->input->post();

        $success = $this->accounting_model->check_budget($data);

        echo json_encode([
            'success' => $success,
        ]);
        die();
    }

    /**
     * update budget.
     * @return json data budget
     */
     public function update_budget() {
        $data = $this->input->post();
        $success = false;
        if (isset($data['budget'])) {
            $id = $data['budget'];
            unset($data['budget']);
            
            $success = $this->accounting_model->update_budget($data, $id);
        }

        echo json_encode([
            'success' => $success,
        ]);
        die();
     }

     /**
     * reconcile restored
     * @param  [type] $account 
     * @param  [type] $company 
     * @return [type]          
     */
    public function reconcile_restored($account) {
        if ($this->input->is_ajax_request()) {
            $success = false;
            $message = _l('acc_restored_failure');
            $hide_restored = true;
            
            $reconcile_restored = $this->accounting_model->reconcile_restored($account);
            if($reconcile_restored){
                $success = true;
                $message = _l('acc_restored_successfully');
            }

            $check_reconcile_restored = $this->accounting_model->check_reconcile_restored($account);
            if($check_reconcile_restored){
                $hide_restored = false;
            }

            $closing_date = false;
            $reconcile = $this->accounting_model->get_reconcile_by_account($account);

            if ($reconcile) {
                if(get_option('acc_close_the_books') == 1){
                    $closing_date = (strtotime(get_option('acc_closing_date')) > strtotime(date('Y-m-d'))) ? true : false;
                }
            }

            echo json_encode([
                'success' => $success,
                'hide_restored' => $hide_restored,
                'closing_date' => $closing_date,
                'message' => $message,
            ]);
            die();
        }
    }

    /**
     * report Accounts receivable ageing detail
     * @return view
     */
    public function rp_accounts_receivable_ageing_detail() {
        $this->load->model('currencies_model');
        $data['title'] = _l('accounts_receivable_ageing_detail');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/accounts_receivable_ageing_detail', $data);
    }

    /**
     * report Accounts payable ageing detail
     * @return view
     */
    public function rp_accounts_payable_ageing_detail() {
        $this->load->model('currencies_model');
        $data['title'] = _l('accounts_payable_ageing_detail');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/accounts_payable_ageing_detail', $data);
    }

    /**
     * report Accounts receivable ageing summary
     * @return view
     */
    public function rp_accounts_receivable_ageing_summary() {
        $this->load->model('currencies_model');
        $data['title'] = _l('accounts_receivable_ageing_summary');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/accounts_receivable_ageing_summary', $data);
    }

    /**
     * report Accounts payable ageing summary
     * @return view
     */
    public function rp_accounts_payable_ageing_summary() {
        $this->load->model('currencies_model');
        $data['title'] = _l('accounts_payable_ageing_summary');
        $data['from_date'] = date('Y-m-01');
        $data['to_date'] = date('Y-m-d');
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/accounts_payable_ageing_summary', $data);
    }

    /**
     * report profit and loss trailing 12 months
     * @return view
     */
    public function rp_profit_and_loss_12_months() {
        $this->load->model('currencies_model');
        $data['title'] = _l('profit_and_loss_12_months');
        $acc_first_month_of_financial_year = get_option('acc_first_month_of_financial_year');

        $data['from_date'] = date('Y-m-d', strtotime($acc_first_month_of_financial_year . ' 01 '.date('Y')));
        $data['to_date'] = date('Y-m-t', strtotime($data['from_date'] . '  - 1 month + 1 year '));

        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/profit_and_loss_12_months', $data);
    }

    /**
     * report budget overview
     * @return view
     */
    public function rp_budget_overview() {
        $this->load->model('currencies_model');
        $data['title'] = _l('budget_overview');
        $acc_first_month_of_financial_year = get_option('acc_first_month_of_financial_year');

        $data['from_date'] = date('Y-m-d', strtotime($acc_first_month_of_financial_year . ' 01 '.date('Y')));
        $data['to_date'] = date('Y-m-t', strtotime($data['from_date'] . '  - 1 month + 1 year '));

        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['budgets'] = $this->accounting_model->get_budgets();
        $this->load->view('report/includes/budget_overview', $data);
    }

    /**
     * rp profit and loss budget performance
     */
    public function rp_profit_and_loss_budget_performance(){
        $this->load->model('currencies_model');
        $data['title'] = _l('profit_and_loss_budget_performance');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['budgets'] = $this->accounting_model->get_budgets('', 'type = "profit_and_loss_accounts"');

        $this->load->view('report/includes/profit_and_loss_budget_performance', $data);
    }

    /**
     * profit and loss budget vs actual
     */
    public function rp_profit_and_loss_budget_vs_actual(){
        $this->load->model('currencies_model');
        $data['title'] = _l('profit_and_loss_budget_vs_actual');
        $data['from_date'] = date('Y-01-01');
        $data['to_date'] = date('Y-m-d');
        $data['currency'] = $this->currencies_model->get_base_currency();
        $data['accounting_method'] = get_option('acc_accounting_method');
        $data['budgets'] = $this->accounting_model->get_budgets('', 'type = "profit_and_loss_accounts"');
        
        $this->load->view('report/includes/profit_and_loss_budget_vs_actual', $data);
    }

    /**
     * delete budget
     * @param  integer $id
     * @return
     */
    public function delete_budget($id)
    {
        if (!has_permission('accounting_budget', '', 'delete')) {
            access_denied('accounting_budget');
        }
        $success = $this->accounting_model->delete_budget($id);
        $message = '';
        if ($success) {
            $message = _l('deleted', _l('budget'));
        } else {
            $message = _l('can_not_delete');
        }

        echo json_encode(['success' => $success, 'message' => $message]);
        die();
    }

    /**
     * { accounts import }
     */
    public function accounts_import(){
        if (!has_permission('accounting_chart_of_accounts', '', 'create')) {
            access_denied('chart_of_accounts');
        }

        $this->load->model('staff_model');
        $data_staff = $this->staff_model->get(get_staff_user_id());

        /*get language active*/
        if ($data_staff) {
            if ($data_staff->default_language != '') {
                $data['active_language'] = $data_staff->default_language;

            } else {

                $data['active_language'] = get_option('active_language');
            }

        } else {
            $data['active_language'] = get_option('active_language');
        }
        $data['title'] = _l('import_excel');

        $this->load->view('chart_of_accounts/import_excel', $data);
    }

    /**
     * import file xlsx banking
     * @return json
     */
    public function import_file_xlsx_account() {
        if (!class_exists('XLSXReader_fin')) {
            require_once module_dir_path(ACCOUNTING_MODULE_NAME) . 'assets/plugins/XLSXReader/XLSXReader.php';
        }
        require_once module_dir_path(ACCOUNTING_MODULE_NAME) . 'assets/plugins/XLSXWriter/xlsxwriter.class.php';

        $filename = '';
        $account_types = $this->accounting_model->get_account_types();
        if ($this->input->post()) {
            if (isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '') {
                $this->delete_error_file_day_before(1, ACCOUTING_IMPORT_ITEM_ERROR);

                // Get the temp file path
                $tmpFilePath = $_FILES['file_csv']['tmp_name'];
                // Make sure we have a filepath
                if (!empty($tmpFilePath) && $tmpFilePath != '') {
                    $rows = [];
                    $arr_insert = [];

                    $tmpDir = TEMP_FOLDER . '/' . time() . uniqid() . '/';

                    if (!file_exists(TEMP_FOLDER)) {
                        mkdir(TEMP_FOLDER, 0755);
                    }

                    if (!file_exists($tmpDir)) {
                        mkdir($tmpDir, 0755);
                    }

                    // Setup our new file path
                    $newFilePath = $tmpDir . $_FILES['file_csv']['name'];

                    if (move_uploaded_file($tmpFilePath, $newFilePath)) {

                        $accounts = $this->accounting_model->get_accounts();

                        $account_name = [];
                        foreach($accounts as $account){
                            $_name = '';
                            if ($account['name'] == '') {
                                $_name .= _l($account['key_name']);
                            } else {
                                $_name .= $account['name'];
                            }
                            $account_name[trim($_name)] = $account['id'];
                        }


                        //Writer file
                        $writer_header = array(
                            _l('type') => 'string',
                            _l('sub_type') => 'string',
                            _l('account_code') => 'string',
                            _l('account_name') => 'string',
                            _l('sub_account_of') => 'string',
                            _l('error') => 'string',
                        );

                        $rowstyle[] = array('widths' => [10, 20, 30, 40]);

                        $writer = new XLSXWriter();
                        $writer->writeSheetHeader('Sheet1', $writer_header, $col_options = ['widths' => [40, 40, 40, 40, 50, 50]]);

                        //Reader file
                        $xlsx = new XLSXReader_fin($newFilePath);
                        $sheetNames = $xlsx->getSheetNames();
                        $data = $xlsx->getSheetData(array_shift($sheetNames));

                        $arr_header = [];

                        $arr_header['type'] = 0;
                        $arr_header['sub_type'] = 1;
                        $arr_header['account_code'] = 2;
                        $arr_header['account_name'] = 3;
                        $arr_header['sub_account_of'] = 4;

                        $total_rows = 0;
                        $total_row_false = 0;

                        $check_arr = [];
                        $check_arr_account_name = [];

                        for($row_check = 1; $row_check < count($data); $row_check++){
                            $sub_account_of = isset($data[$row_check][$arr_header['sub_account_of']]) ? $data[$row_check][$arr_header['sub_account_of']] : '';

                            if((is_null($sub_account_of) == true || $sub_account_of == '') && isset($data[$row_check][$arr_header['account_name']])){
                                $check_arr[] = $data[$row_check];
                                $check_arr_account_name[] = $data[$row_check][$arr_header['account_name']];
                            }
                        }


                        for ($row = 1; $row < count($data); $row++) {

                            $total_rows++;

                            $rd = array();
                            $flag = 0;
                            $flag2 = 0;

                            $string_error = '';
                            $flag_position_group;
                            $flag_department = null;

                            $value_type = isset($data[$row][$arr_header['type']]) ? $data[$row][$arr_header['type']] : '';
                            $value_sub_type = isset($data[$row][$arr_header['sub_type']]) ? $data[$row][$arr_header['sub_type']] : '';
                            $value_account_code = isset($data[$row][$arr_header['account_code']]) ? $data[$row][$arr_header['account_code']] : '';
                            $value_account_name = isset($data[$row][$arr_header['account_name']]) ? $data[$row][$arr_header['account_name']] : '';
                            $value_sub_account_of = isset($data[$row][$arr_header['sub_account_of']]) ? $data[$row][$arr_header['sub_account_of']] : '';

                            $reg_day = '/([0-9]{2})\/([0-9]{2})\/([0-9]{4})/'; /*yyyy-mm-dd*/

                            if (is_null($value_type) != true) {
                                if(is_numeric($value_type)){
                                    if(get_account_type_by_id($value_type) == false){
                                        $string_error .= _l('type') .' '. _l('invalid').' ';
                                        $flag = 1;
                                    }else{
                                        $value_type = get_account_type_by_id($value_type);
                                    }
                                }else{
                                    if(get_account_type_by_name($value_type) == false){
                                        $string_error .= _l('type') .' '. _l('invalid').' ';
                                        $flag = 1;
                                    }else{
                                        $value_type = get_account_type_by_name($value_type);
                                    }
                                }
                            }

                            if (is_null($value_sub_type) != true) {
                                if(is_numeric($value_sub_type)){
                                    if(get_account_sub_type_by_id($value_sub_type) == false){
                                        $string_error .= _l('sub_type') .' '. _l('invalid').' ';
                                        $flag = 1;
                                    }else{
                                        $value_sub_type = get_account_sub_type_by_id($value_sub_type);
                                    }
                                }else{
                                    if(get_account_sub_type_by_name($value_sub_type) == false){
                                        $string_error .= _l('sub_type') .' '. _l('invalid').' ';
                                        $flag = 1;
                                    }else{
                                        $value_sub_type = get_account_sub_type_by_name($value_sub_type);
                                    }
                                }
                            }

                            if (is_null($value_account_name) == true || $value_account_name == '') {
                                $string_error .= _l('account_name') .' '. _l('not_yet_entered').' ';
                                $flag = 1;
                            }

                            if (is_null($value_sub_account_of) == false && $value_sub_account_of != '') {
                                if(!in_array($value_sub_account_of, $check_arr_account_name)){
                                    if(is_numeric($value_sub_account_of)){
                                        if(get_account_by_id($value_sub_account_of) == false){
                                            $string_error .= _l('sub_account_of') .' '. _l('invalid').' ';
                                            $flag = 1;
                                        }else{
                                            $value_sub_account_of = get_account_by_id($value_sub_account_of);
                                        }
                                    }else{
                                        if(!array_key_exists($value_sub_account_of, $account_name)){
                                            if($string_error != ''){
                                                $string_error .= ', ';
                                            }
                                            $string_error .= _l('sub_account_of') .' '. _l('invalid');
                                            $flag = 1;
                                        }else{
                                            $value_sub_account_of = $account_name[$value_sub_account_of];
                                        }
                                    }
                                }
                            }

                            if (($flag == 1) || $flag2 == 1) {
                                //write error file
                                $writer->writeSheetRow('Sheet1', [
                                    $value_type,
                                    $value_sub_type,
                                    $value_account_code,
                                    $value_account_name,
                                    $value_sub_account_of,
                                    $string_error,
                                ]);

                                // $numRow++;
                                $total_row_false++;
                            }

                            if ($flag == 0 && $flag2 == 0) {

                                $rd['account_type_id'] = $value_type;
                                $rd['account_detail_type_id'] = $value_sub_type;
                                $rd['number'] = $value_account_code;
                                $rd['name'] = $value_account_name;
                                $rd['parent_account'] = $value_sub_account_of;
                                $rd['active'] = 1;

                                $rows[] = $rd;
                                array_push($arr_insert, $rd);

                            }

                        }

                        //insert batch
                        if (count($arr_insert) > 0) {
                            $this->accounting_model->insert_batch_account($arr_insert);
                        }

                        $total_rows = $total_rows;
                        $total_row_success = isset($rows) ? count($rows) : 0;
                        $dataerror = '';
                        $message = 'Not enought rows for importing';

                        if ($total_row_false != 0) {
                            $filename = 'Import_account_error_' . get_staff_user_id() . '_' . strtotime(date('Y-m-d H:i:s')) . '.xlsx';
                            $writer->writeToFile(str_replace($filename, ACCOUTING_IMPORT_ITEM_ERROR . $filename, $filename));
                        }

                    }
                }
            }
        }

        if (file_exists($newFilePath)) {
            @unlink($newFilePath);
        }

        echo json_encode([
            'message' => $message,
            'total_row_success' => $total_row_success,
            'total_row_false' => $total_row_false,
            'total_rows' => $total_rows,
            'site_url' => site_url(),
            'staff_id' => get_staff_user_id(),
            'filename' => ACCOUTING_IMPORT_ITEM_ERROR . $filename,
        ]);
    }

    /**
     * { budget import }
     */
    public function budget_import(){
        if (!has_permission('accounting_budget', '', 'create')) {
            access_denied('accounting_budget');
        }

        $this->load->model('staff_model');
        $data_staff = $this->staff_model->get(get_staff_user_id());

        /*get language active*/
        if ($data_staff) {
            if ($data_staff->default_language != '') {
                $data['active_language'] = $data_staff->default_language;

            } else {

                $data['active_language'] = get_option('active_language');
            }

        } else {
            $data['active_language'] = get_option('active_language');
        }
        $data['title'] = _l('import_excel');

        $this->load->view('budget/import_excel', $data);
    }

    /**
     * import file xlsx banking
     * @return json
     */
    public function import_file_xlsx_budget() {
        if (!class_exists('XLSXReader_fin')) {
            require_once module_dir_path(ACCOUNTING_MODULE_NAME) . 'assets/plugins/XLSXReader/XLSXReader.php';
        }
        require_once module_dir_path(ACCOUNTING_MODULE_NAME) . 'assets/plugins/XLSXWriter/xlsxwriter.class.php';

        $filename = '';

        if ($this->input->post()) {
            $year = $this->input->post('year');
            $type = $this->input->post('type');
            $name = $year.' - '. _l($type);

            $import_type = $this->input->post('import_type');

            $accounts = $this->accounting_model->get_accounts();

            $data_return = [];

            $account_name = [];
            foreach($accounts as $account){
                $_name = '';
                if ($account['name'] == '') {
                    $_name .= _l($account['key_name']);
                } else {
                    $_name .= $account['name'];
                }
                $account_name[trim($_name)] = $account['id'];
            }


            $this->db->where('year', $year);
            $this->db->where('type', $type);
            $budget = $this->db->get(db_prefix() . 'acc_budgets')->row();

            if($budget){
                if($name != $budget->name){
                    $this->db->where('id', $budget->id);
                    $this->db->update(db_prefix() . 'acc_budgets', ['name' => $name]);
                }

                $budget_id = $budget->id;
            }else{
                $this->db->insert(db_prefix() . 'acc_budgets', ['name' => $name, 'year' => $year, 'type' => $type]);
                $budget_id = $this->db->insert_id();
            }

            if (isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '') {
                $this->delete_error_file_day_before(1, ACCOUTING_IMPORT_ITEM_ERROR);

                // Get the temp file path
                $tmpFilePath = $_FILES['file_csv']['tmp_name'];
                // Make sure we have a filepath
                if (!empty($tmpFilePath) && $tmpFilePath != '') {
                    $rows = [];
                    $arr_insert = [];

                    $tmpDir = TEMP_FOLDER . '/' . time() . uniqid() . '/';

                    if (!file_exists(TEMP_FOLDER)) {
                        mkdir(TEMP_FOLDER, 0755);
                    }

                    if (!file_exists($tmpDir)) {
                        mkdir($tmpDir, 0755);
                    }

                    // Setup our new file path
                    $newFilePath = $tmpDir . $_FILES['file_csv']['name'];

                    if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                        //Writer file
                        
                        if($import_type == 'month'){
                            $writer_header = array(
                                _l('acc_account') => 'string',
                                _l('acc_month_1') => 'string',
                                _l('acc_month_2') => 'string',
                                _l('acc_month_3') => 'string',
                                _l('acc_month_4') => 'string',
                                _l('acc_month_5') => 'string',
                                _l('acc_month_6') => 'string',
                                _l('acc_month_7') => 'string',
                                _l('acc_month_8') => 'string',
                                _l('acc_month_9') => 'string',
                                _l('acc_month_10') => 'string',
                                _l('acc_month_11') => 'string',
                                _l('acc_month_12') => 'string',
                                _l('error') => 'string',
                            );
                        }elseif ($import_type == 'quarter') {
                            $writer_header = array(
                                _l('acc_account') => 'string',
                                _l('quarter').' 1' => 'string',
                                _l('quarter').' 2' => 'string',
                                _l('quarter').' 3' => 'string',
                                _l('quarter').' 4' => 'string',
                                _l('error') => 'string',
                            );
                        }else{
                            $writer_header = array(
                                _l('acc_account') => 'string',
                                _l('acc_amount') => 'string',
                                _l('error') => 'string',
                            );
                        }


                        $rowstyle[] = array('widths' => [10, 20, 30, 40]);

                        $writer = new XLSXWriter();
                        $writer->writeSheetHeader('Sheet1', $writer_header, $col_options = ['widths' => [40, 40, 40, 40, 40, 40, 40, 40, 40, 40, 40, 40, 40]]);

                        //Reader file
                        $xlsx = new XLSXReader_fin($newFilePath);
                        $sheetNames = $xlsx->getSheetNames();
                        $data = $xlsx->getSheetData($sheetNames[1]);

                        $arr_header = [];

                        if($import_type == 'month'){
                            $arr_header['account'] = 0;
                            $arr_header['month_1'] = 1;
                            $arr_header['month_2'] = 2;
                            $arr_header['month_3'] = 3;
                            $arr_header['month_4'] = 4;
                            $arr_header['month_5'] = 5;
                            $arr_header['month_6'] = 6;
                            $arr_header['month_7'] = 7;
                            $arr_header['month_8'] = 8;
                            $arr_header['month_9'] = 9;
                            $arr_header['month_10'] = 10;
                            $arr_header['month_11'] = 11;
                            $arr_header['month_12'] = 12;
                        }elseif ($import_type == 'quarter') {
                            $arr_header['account'] = 0;
                            $arr_header['quarter_1'] = 1;
                            $arr_header['quarter_2'] = 2;
                            $arr_header['quarter_3'] = 3;
                            $arr_header['quarter_4'] = 4;
                        }else{
                            $arr_header['account'] = 0;
                            $arr_header['amount'] = 1;
                        }


                        $total_rows = 0;
                        $total_row_false = 0;

                        for ($row = 1; $row < count($data); $row++) {

                            $total_rows++;

                            $rd = array();
                            $flag = 0;
                            $flag2 = 0;

                            $string_error = '';


                            if($import_type == 'month'){
                                $value_account = isset($data[$row][$arr_header['account']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['account']])) : '';
                                $value_month_1 = isset($data[$row][$arr_header['month_1']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_1']])) : '';
                                $value_month_2 = isset($data[$row][$arr_header['month_2']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_2']])) : '';
                                $value_month_3 = isset($data[$row][$arr_header['month_3']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_3']])) : '';
                                $value_month_4 = isset($data[$row][$arr_header['month_4']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_4']])) : '';
                                $value_month_5 = isset($data[$row][$arr_header['month_5']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_5']])) : '';
                                $value_month_6 = isset($data[$row][$arr_header['month_6']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_6']])) : '';
                                $value_month_7 = isset($data[$row][$arr_header['month_7']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_7']])) : '';
                                $value_month_8 = isset($data[$row][$arr_header['month_8']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_8']])) : '';
                                $value_month_9 = isset($data[$row][$arr_header['month_9']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_9']])) : '';
                                $value_month_10 = isset($data[$row][$arr_header['month_10']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_10']])) : '';
                                $value_month_11 = isset($data[$row][$arr_header['month_11']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_11']])) : '';
                                $value_month_12 = isset($data[$row][$arr_header['month_12']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['month_12']])) : '';
                            }elseif ($import_type == 'quarter') {
                                $value_account = isset($data[$row][$arr_header['account']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['account']])) : '';
                                $value_quarter_1 = isset($data[$row][$arr_header['quarter_1']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['quarter_1']])) : '';
                                $value_quarter_2 = isset($data[$row][$arr_header['quarter_2']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['quarter_2']])) : '';
                                $value_quarter_3 = isset($data[$row][$arr_header['quarter_3']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['quarter_3']])) : '';
                                $value_quarter_4 = isset($data[$row][$arr_header['quarter_4']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['quarter_4']])) : '';
                            }else{
                                $value_account = isset($data[$row][$arr_header['account']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['account']])) : '';
                                $value_amount = isset($data[$row][$arr_header['amount']]) ? trim(str_replace(' ',' ',$data[$row][$arr_header['amount']])) : '';
                            }

                        
                            if(is_null($value_account) == true || $value_account == ''){
                                if($string_error != ''){
                                    $string_error .= ', ';
                                }
                                $string_error .= _l('acc_account') .' '. _l('not_yet_entered');
                                $flag = 1;
                            }else {
                                if(is_numeric($value_account)){
                                    if(get_account_by_id($value_account) == false){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_account') .' '. _l('invalid');
                                        $flag = 1;
                                    }else{
                                        $value_account = get_account_by_id($value_account);
                                    }
                                }else{
                                    
                                    if(!array_key_exists($value_account, $account_name)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_account') .' '. _l('invalid');
                                        $flag = 1;
                                    }else{
                                        $value_account = $account_name[$value_account];
                                    }
                                }
                            }

                            if($import_type == 'month'){
                                if((is_null($value_month_1) || $value_month_1 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_1') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_1)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_1') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_2) || $value_month_2 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_2') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_2)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_2') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_3) || $value_month_3 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_3') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_3)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_3') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_4) || $value_month_4 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_4') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_4)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_4') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_5) || $value_month_5 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_5') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_5)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_5') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_6) || $value_month_6 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_6') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_6)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_6') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_7) || $value_month_7 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_7') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_7)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_7') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_8) || $value_month_8 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_8') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_8)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_8') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_9) || $value_month_9 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_9') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_9)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_9') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_10) || $value_month_10 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_10') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_10)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_10') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_11) || $value_month_11 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_11') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_11)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_11') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_month_12) || $value_month_12 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('acc_month_12') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_month_12)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('acc_month_12') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                            }elseif ($import_type == 'quarter') {
                                if((is_null($value_quarter_1) || $value_quarter_1 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('quarter').' 1' .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_quarter_1)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('quarter').' 1' .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_quarter_2) || $value_quarter_2 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('quarter').' 2' .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_quarter_2)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('quarter').' 2' .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_quarter_3) || $value_quarter_3 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('quarter').' 3' .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_quarter_3)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('quarter').' 3' .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }

                                if((is_null($value_quarter_4) || $value_quarter_4 == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('quarter').' 4' .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_quarter_4)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('quarter').' 4' .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }
                            }else{
                                if((is_null($value_amount) || $value_amount == '')){
                                    if($string_error != ''){
                                        $string_error .= ', ';
                                    }
                                    $string_error .= _l('amount') .' '. _l('not_yet_entered');
                                    $flag = 1;
                                }else{
                                    if(!is_numeric($value_amount)){
                                        if($string_error != ''){
                                            $string_error .= ', ';
                                        }
                                        $string_error .= _l('amount') .' '. _l('invalid');
                                        $flag = 1;
                                    }
                                }
                            }

                            if (($flag == 1) || $flag2 == 1) {
                                //write error file
                                if($import_type == 'month'){
                                    $writer->writeSheetRow('Sheet1', [
                                        $value_account,
                                        $value_month_1,
                                        $value_month_2,
                                        $value_month_3,
                                        $value_month_4,
                                        $value_month_5,
                                        $value_month_6,
                                        $value_month_7,
                                        $value_month_8,
                                        $value_month_9,
                                        $value_month_10,
                                        $value_month_11,
                                        $value_month_12,
                                        $string_error,
                                    ]);
                                }elseif ($import_type == 'quarter') {
                                    $writer->writeSheetRow('Sheet1', [
                                        $value_account,
                                        $value_quarter_1,
                                        $value_quarter_2,
                                        $value_quarter_3,
                                        $value_quarter_4,
                                        $string_error,
                                    ]);
                                }else{
                                    $writer->writeSheetRow('Sheet1', [
                                        $value_account,
                                        $value_amount,
                                        $string_error,
                                    ]);
                                }
                                
                                // $numRow++;
                                $total_row_false++;
                            }

                            if ($flag == 0 && $flag2 == 0) {
                                if($import_type == 'month'){
                                    $rd['account'] = $value_account;
                                    $rd['month_1'] = $value_month_1;
                                    $rd['month_2'] = $value_month_2;
                                    $rd['month_3'] = $value_month_3;
                                    $rd['month_4'] = $value_month_4;
                                    $rd['month_5'] = $value_month_5;
                                    $rd['month_6'] = $value_month_6;
                                    $rd['month_7'] = $value_month_7;
                                    $rd['month_8'] = $value_month_8;
                                    $rd['month_9'] = $value_month_9;
                                    $rd['month_10'] = $value_month_10;
                                    $rd['month_11'] = $value_month_11;
                                    $rd['month_12'] = $value_month_12;
                                }elseif ($import_type == 'quarter') {
                                    $rd['account'] = $value_account;
                                    $rd['quarter_1'] = $value_quarter_1;
                                    $rd['quarter_2'] = $value_quarter_2;
                                    $rd['quarter_3'] = $value_quarter_3;
                                    $rd['quarter_4'] = $value_quarter_4;
                                }else{
                                    $rd['account'] = $value_account;
                                    $rd['amount'] = $value_amount;
                                }

                                $rows[] = $rd;
                                array_push($arr_insert, $rd);

                            }

                        }

                        //insert batch
                        if (count($arr_insert) > 0) {
                            $this->accounting_model->insert_batch_budget($arr_insert, $budget_id, $import_type);
                        }

                        $total_rows = $total_rows;
                        $total_row_success = isset($rows) ? count($rows) : 0;
                        $dataerror = '';
                        $message = 'Not enought rows for importing';

                        if ($total_row_false != 0) {
                            $filename = 'Import_budget_error_' . get_staff_user_id() . '_' . strtotime(date('Y-m-d H:i:s')) . '.xlsx';
                            $writer->writeToFile(str_replace($filename, ACCOUTING_IMPORT_ITEM_ERROR . $filename, $filename));
                        }

                    }
                }
            }
        }

        if (file_exists($newFilePath)) {
            @unlink($newFilePath);
        }

        echo json_encode([
            'message' => $message,
            'total_row_success' => $total_row_success,
            'total_row_false' => $total_row_false,
            'total_rows' => $total_rows,
            'site_url' => site_url(),
            'staff_id' => get_staff_user_id(),
            'filename' => ACCOUTING_IMPORT_ITEM_ERROR . $filename,
        ]);
    }

    /**
     * update reset all data account detail type
     */
    public function reset_account_detail_types(){
        if (!has_permission('accounting_setting', '', 'delete') && !is_admin() ) {
            access_denied('accounting_setting');
        }

        $success = $this->accounting_model->reset_account_detail_types();
        if($success == true){
            $message = _l('reset_data_successfully');
            set_alert('success', $message);
        }
        redirect(admin_url('accounting/setting?group=account_type_details'));
    }

    /**
     * manage banking
     * @return view
     */
    public function banking()
    {
        if (!has_permission('accounting_banking', '', 'view')) {
            access_denied('banking');
        }

        $data          = [];
        $this->load->model('currencies_model');
        $data['currency'] = $this->currencies_model->get_base_currency();

        $data['tab_2'] = $this->input->get('tab');

        $data['group'] = $this->input->get('group');
        $data['tab'][] = 'banking_register';
        $data['tab'][] = 'posted_bank_transactions';
        $data['tab'][] = 'reconcile_bank_account';
      
        if ($data['group'] == '') {
            $data['group'] = 'banking_register';
        }

        $data['bank_accounts'] = $this->accounting_model->get_accounts('', ['account_detail_type_id' => 14]);

        if($data['group'] == 'reconcile_bank_account'){
            $data['bank_account'] = $this->input->get('bank_account');
            if($data['bank_account'] != ''){
                $data['accounts'] = $this->accounting_model->get_accounts();
                $data['account'] = $this->accounting_model->get_accounts($data['bank_account']);
                $data['reconcile'] = $this->accounting_model->get_reconcile_by_account($data['bank_account']);
                $data['reconcile_difference_info'] = $this->accounting_model->get_reconcile_difference_info($data['reconcile']->id);
                $this->load->model('currencies_model');
                $data['currency'] = $this->currencies_model->get_base_currency();
                $data['title'] = _l('reconcile');
                $data['account_adjust'] = $this->accounting_model->get_account_id_by_number('2110-000');
            }else{
                if ($this->input->post()) {
                    if (!has_permission('accounting_reconcile', '', 'create')) {
                        access_denied('accounting_reconcile');
                    }
                    $data = $this->input->post();
                    if ($data['resume'] == 0) {
                        unset($data['resume']);
                        $success = $this->accounting_model->add_bank_reconcile($data);
                    }
                    redirect(admin_url('accounting/banking?group=reconcile_bank_account&bank_account=' . $data['account']));

                }
                $this->load->model('currencies_model');
                $data['currency'] = $this->currencies_model->get_base_currency();

                $data['title'] = _l('reconcile');
                $data['beginning_balance'] = 0;
                $data['resume'] = 0;
                $data['approval'] = 0;

                //get default company

                $default_company='';
                $hide_restored=' hide';

                $closing_date = false;
                
                if(isset($data['bank_accounts'][0])){
                    $check_reconcile_restored = $this->accounting_model->check_reconcile_restored($data['bank_accounts'][0]['id'], $default_company);
                    if($check_reconcile_restored){
                        $hide_restored='';
                    }

                    $reconcile = $this->accounting_model->get_reconcile_by_account($data['bank_accounts'][0]['id'], $default_company);


                    if ($reconcile) {
                        if(get_option('acc_close_the_books') == 1){
                            $closing_date = (strtotime($reconcile->ending_balance) > strtotime(date('Y-m-d'))) ? true : false;
                        }
                        $data['beginning_balance'] = $reconcile->ending_balance;
                        //if ($reconcile->finish == 0 || $reconcile->approval == 0) {
                        if ($reconcile->finish == 0) {
                            $data['resume'] = 1;
                        }

                        // if ($reconcile->finish == 1 && $reconcile->approval != 0 && $reconcile->approval != '') {
                        //     $data['approval'] = 1;
                        // }

                    }
                }
                $data['accounts_to_select'] = $this->accounting_model->get_data_account_to_select();
                $data['hide_restored'] = $closing_date == false ? $hide_restored : 'hide';
            }
        }

        $data['_status'] = '';
        if ($this->input->get('status')) {
            $data['_status'] = [$this->input->get('status')];
        }

        $data['account_to_select'] = $this->accounting_model->get_data_account_to_select();
        $data['title']        = _l($data['group']);
        $data['tabs']['view'] = 'banking/' . $data['group'];
        $this->load->view('banking/manage', $data);
    }

    /**
     * banking table
     * @return json
     */
    public function banking_register_table() {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();

            $select = [
                'date',
                //'number',
                db_prefix().'pur_vendor.company as vendor_name',
                'description',
                'credit',
                'debit',
                // db_prefix() . 'acc_account_history.id as id',
                'cleared',
            ];
            $where = [];

            


            $from_date = '';
            $to_date = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            
            if ($this->input->post('bank_account')) {
                $bank_account = $this->input->post('bank_account');
                array_push($where, 'AND account ='. $bank_account);
            }else{
                array_push($where, 'AND account = "-1"');
            }

            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (date >= "' . $from_date . '" and date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (date <= "' . $to_date . '")');
            }

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if ($value == 'converted') {
                        if ($where_status != '') {
                            $where_status .= ' or (cleared > 0)';
                        } else {
                            $where_status .= '(cleared > 0)';
                        }
                    }

                    if ($value == 'has_not_been_converted') {
                        if ($where_status != '') {
                            $where_status .= ' or (cleared = 0)';
                        } else {
                            $where_status .= '(cleared = 0)';
                        }
                    }
                }

                if ($where_status != '') {
                    array_push($where, 'AND (' . $where_status . ')');
                }
            }

            $aColumns = $select;
            $sIndexColumn = 'id';
            $sTable = db_prefix() . 'acc_account_history';
            $join = [
                'LEFT JOIN ' . db_prefix() . 'pur_vendor ON ' . db_prefix() . 'pur_vendor.userid = ' . db_prefix() . 'acc_account_history.vendor',
            ];
            $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['customer', 'rel_type', 'rel_id', 'account', 'vendor']);

            $output = $result['output'];
            $rResult = $result['rResult'];

            $balance = 0;

            foreach ($rResult as $aRow) {
                $row = [];
                
                // $url = get_url_by_type_id($aRow['rel_type'], $aRow['rel_id']);

                // $row[] = '<a href="'.$url.'" class="text-default-bl">'. _d($aRow['date']).'</a>';
                $row[] = _d($aRow['date']);

                //if($aRow['rel_type'] == 'check' || $aRow['rel_type'] == 'payment'){
                // if($aRow['rel_type'] == 'check'){
                //     $row[] = '#'.str_pad($aRow['number'], 4, '0', STR_PAD_LEFT);
                // }else{
                //     $row[] = '';
                // }

                $credit = 0;
                $debit = 0;

                if($aRow['customer'] != '' && $aRow['customer'] != 0){
                    $row[] = get_company_name($aRow['customer']);
                }else{
                    $row[] = $aRow['vendor_name'];
                }

                $row[] = $aRow['description'];

                if($aRow['credit'] != 0){
                    $credit = $aRow['credit'];
                    $row[] = app_format_money($aRow['credit'], $currency->name);
                }else{
                    $row[] = '';
                }

                if($aRow['debit'] != 0){
                    $debit = $aRow['debit'];
                    $row[] = app_format_money($aRow['debit'], $currency->name);
                }else{
                    $row[] = '';
                }
               


              //   if($aRow['credit'] != 0){
                    // $row[] = app_format_money($aRow['credit'], $currency->name);
              //   }else{
              //    $row[] = '';
              //   }
                
              //   if($aRow['debit'] != 0){
                    // $row[] = app_format_money($aRow['debit'], $currency->name);
              //   }else{
              //    $row[] = '';
              //   }
                
                // $balance += round(($debit - $credit), 2);
                // $row[] = app_format_money(round($balance, 2), $currency->name);

                $status_name = _l('not_yet_match');
                $label_class = 'default';

                if ($aRow['cleared'] == 1) {
                    $row[] = '<i class="fa fa-check-circle text-success fa-lg" aria-hidden="true"></i>';
                }else{
                    $row[] = '';
                }

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    public function check_plaid_connect($bank_id = ''){
        $success = false;
        if($bank_id != ''){
            $account_data = $this->accounting_model->get_account_bank_data($bank_id);
            if(isset($account_data) && $account_data != NULL && $account_data[0]['plaid_status'] == 1){
                $success = true;
            }
        }

        echo json_encode($success);
        die();
    }

    /**
     * posted bank transactions table
     * @return json
     */
    public function posted_bank_transactions_table() {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            
            $select = [
                'date',
                //'check_number',
                'payee',
                'description',
                'withdrawals',
                'deposits',
                // 'id',
                'matched',
            ];
            $where = [];

            $from_date = '';
            $to_date = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            
            if ($this->input->post('bank_account')) {
                $bank_account = $this->input->post('bank_account');
                array_push($where, 'AND '.db_prefix().'acc_transaction_bankings.bank_id ='. $bank_account);
            }else{
                array_push($where, 'AND '.db_prefix().'acc_transaction_bankings.bank_id = "-1"');
            }

            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'acc_transaction_bankings.date >= "' . $from_date . '" and ' . db_prefix() . 'acc_transaction_bankings.date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'acc_transaction_bankings.date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'acc_transaction_bankings.date <= "' . $to_date . '")');
            }

            $company_id = $this->input->post('company_id');
            if ($company_id != '') {
                array_push($where, 'AND id IN (SELECT rel_id FROM '. db_prefix() . 'acc_account_history where company = ' . $company_id.' and rel_type = "banking")');
            }

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if ($value == 'converted') {
                        if ($where_status != '') {
                            $where_status .= ' or (matched > 0)';
                        } else {
                            $where_status .= '(matched > 0)';
                        }
                    }

                    if ($value == 'has_not_been_converted') {
                        if ($where_status != '') {
                            $where_status .= ' or (matched = 0)';
                        } else {
                            $where_status .= '(matched = 0)';
                        }
                    }
                }

                if ($where_status != '') {
                    array_push($where, 'AND (' . $where_status . ')');
                }
            }

            $aColumns = $select;
            $sIndexColumn = 'id';
            $sTable = db_prefix() . 'acc_transaction_bankings';
            $join = [];
            $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['matched']);

            $output = $result['output'];
            $rResult = $result['rResult'];
            $balance = 0;

            foreach ($rResult as $aRow) {
                $row = [];

                $row[] = _d($aRow['date']);

                //$row[] = $aRow['check_number'];

                $row[] = $aRow['payee'];
                $row[] = $aRow['description'];

                $row[] = $aRow['withdrawals'] != 0 ? app_format_money($aRow['withdrawals'], $currency->name) : '';
                $row[] = $aRow['deposits'] != 0 ? app_format_money($aRow['deposits'], $currency->name) : '';

                // $balance += round(($aRow['withdrawals'] - $aRow['deposits']), 2);
                // $row[] = app_format_money(round($balance, 2), $currency->name);

                if ($aRow['matched'] == 1) {
                    $row[] = '<i class="fa fa-check-circle text-success fa-lg" aria-hidden="true"></i>';
                }else{
                    $row[] = '';
                }


                //$row[] = '<span class="label label-' . $label_class . ' s-status payment-status-' . $aRow['id'] . '">' . $status_name . '</span>';

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }
    
    public function plaid_bank_new_transactions(){
        $data['last_updated'] = '';
        if(isset($_GET['id'])){
            $transactions = $this->accounting_model->get_plaid_transaction($_GET['id']);
            $data['transactions'] = $transactions;
            $account_data = $this->accounting_model->get_account_bank_data($_GET['id']);
            $data['account_data'] = $account_data;
            $refresh_data = $this->accounting_model->get_last_refresh_data($_GET['id']);
            $data['refresh_data'] = $refresh_data;
            $data['last_updated'] = $this->accounting_model->get_date_last_updated($_GET['id']);
        }
        $data['title'] = _l('acc_plaid_transaction');
        $data['status'] = '';
        if ($this->input->get('status')) {
            $data['status'] = [$this->input->get('status')];
        }


        $data['bank_accounts'] = $this->accounting_model->get_accounts('', ['account_detail_type_id' => 14]);
        $data['accounts'] = $this->accounting_model->get_accounts();
        $data['account_to_select'] = $this->accounting_model->get_data_account_to_select();
        $this->load->view('banking/plaid_new_transaction', $data);
    }

    //Create Plaid Link Token
    public function create_plaid_token(){
        $link_token = $this->accounting_model->get_plaid_link_token(); 

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(500)
            ->set_output(json_encode(array(
                    'link_token' => $link_token,
            )));
    }

    /**
     * update plaid environment
     */
    public function update_plaid_environment() {
        if (!has_permission('accounting_setting', '', 'edit') && !is_admin()) {
            access_denied('accounting_setting');
        }
        $data = $this->input->post();
        $success = $this->accounting_model->update_plaid_environment($data);

        if ($success == true) {
            $message = _l('updated_successfully', _l('setting'));
            set_alert('success', $message);
        }

        redirect(admin_url('accounting/setting?group=plaid_environment'));
    }

    public function update_plaid_bank_accounts(){ 
        $public_token = $_GET['public_token'];  
        $bank_id = $_GET['bankId'];

        $accessToken = $this->accounting_model->get_access_token($public_token); 
        $accounts = $this->accounting_model->plaid_get_account($accessToken); 

        $accountId = $accounts[0]->account_id;
        $accountName = $accounts[0]->name;

        $this->db->where('id', $bank_id);
        $this->db->update(db_prefix() . 'acc_accounts', [
            'access_token' => $accessToken,
            'account_id' => $accountId,
            'plaid_status' => 1,
            'plaid_account_name' => $accountName
        ]);
        
        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(500)
            ->set_output(json_encode(array(
                    'error' => '',
            )));
    }

    /**
     * banking table
     * @return json
     */
    public function import_banking_table() {
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            
            $select = [
                'date',
                //'check_number',
                'payee',
                'description',
                'withdrawals',
                'deposits',
                'datecreated',
            ];
            $where = [];

            $from_date = '';
            $to_date = '';
            if ($this->input->post('from_date')) {
                $from_date = $this->input->post('from_date');
                if (!$this->accounting_model->check_format_date($from_date)) {
                    $from_date = to_sql_date($from_date);
                }
            }

            if ($this->input->post('to_date')) {
                $to_date = $this->input->post('to_date');
                if (!$this->accounting_model->check_format_date($to_date)) {
                    $to_date = to_sql_date($to_date);
                }
            }
            
            if ($this->input->post('bank_account')) {
                $bank_account = $this->input->post('bank_account');
                array_push($where, 'AND '.db_prefix().'acc_transaction_bankings.bank_id ='. $bank_account);
            }else{
                array_push($where, 'AND '.db_prefix().'acc_transaction_bankings.bank_id = "-1"');
            }

            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'acc_transaction_bankings.date >= "' . $from_date . '" and ' . db_prefix() . 'acc_transaction_bankings.date <= "' . $to_date . '")');
            } elseif ($from_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'acc_transaction_bankings.date >= "' . $from_date . '")');
            } elseif ($to_date != '') {
                array_push($where, 'AND (' . db_prefix() . 'acc_transaction_bankings.date <= "' . $to_date . '")');
            }

            if ($this->input->post('status')) {
                $status = $this->input->post('status');
                $where_status = '';
                foreach ($status as $key => $value) {
                    if ($value == 'converted') {
                        if ($where_status != '') {
                            $where_status .= ' or (matched > 0)';
                        } else {
                            $where_status .= '(matched > 0)';
                        }
                    }

                    if ($value == 'has_not_been_converted') {
                        if ($where_status != '') {
                            $where_status .= ' or (matched = 0)';
                        } else {
                            $where_status .= '(matched = 0)';
                        }
                    }
                }

                if ($where_status != '') {
                    array_push($where, 'AND (' . $where_status . ')');
                }
            }
            $aColumns = $select;
            $sIndexColumn = 'id';
            $sTable = db_prefix() . 'acc_transaction_bankings';
            $join = [];
            $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['id']);

            $output = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row = [];

                $row[] = _d($aRow['date']);

                //$row[] = $aRow['check_number'];

                $row[] = $aRow['payee'];
                $row[] = $aRow['description'];

                $row[] = app_format_money($aRow['withdrawals'], $currency->name);
                $row[] = app_format_money($aRow['deposits'], $currency->name);

                $row[] = _d($aRow['datecreated']);

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    public function update_plaid_transaction(){
        if ($this->input->post()) { 
            $bank_id = $_POST['bank_id'];
            $end_date = date('Y-m-d');

            $start_date = to_sql_date($_POST['from_date']);
        
            //Make Entry of Transaction Log
            $logData = ['bank_id' => $_POST['bank_id'], 'last_updated' => date('Y-m-d'), 'addedFrom' => get_staff_user_id()];

            $this->db->insert(db_prefix() . 'acc_plaid_transaction_logs', $logData);
            
            //Call Curl function to get Transaction
            if($this->db->affected_rows() > 0){
                $this->transactionData($start_date, $end_date, $_POST['bank_id']);
                $transactions = $this->accounting_model->get_plaid_transaction($_POST['bank_id']);
                $data['transactions'] = $transactions;
                $data['bank_id'] = $_POST['bank_id'];
                $data['title'] = _l('acc_plaid_transaction');
                $data['status'] = '';
                if ($this->input->get('status')) {
                    $data['status'] = [$this->input->get('status')];
                }

                $data['bank_accounts'] = $this->accounting_model->get_accounts('', ['account_detail_type_id' => 14]);

                $data['accounts'] = $this->accounting_model->get_accounts();
                $data['account_to_select'] = $this->accounting_model->get_data_account_to_select();
                
            }

        }
    }

    public function update_plaid_status(){
        if ($this->input->post()) { 
            $bank_id = $_POST['bank_id'];   
            
            $this->db->where('id', $bank_id);
            $this->db->update(db_prefix() . 'acc_accounts', [
                'plaid_status' => 0
            ]);

            $this->db->where('bank_id', $bank_id);
            $this->db->delete(db_prefix() . 'acc_transaction_bankings');

            $this->db->where('bank_id', $bank_id);
            $this->db->delete(db_prefix() . 'acc_plaid_transaction_logs');
        }
    }

    public function transactionData($start_date, $end_date, $bank_id){
        //Get the Paid Key and Secret Key and also access token
        $accounts = $this->accounting_model->get_accounts($bank_id);
        $transactions = $this->accounting_model->plaid_get_transactions(['access_token' => $accounts->access_token, 'start_date' => $start_date, 'end_date' => $end_date]);

        if($transactions){
           //Call the transaction Insert Function in Table
           $success = $this->insertTransactionRecord($transactions, $bank_id);
           if($success){
                set_alert('success', _l('imported_successfully'));
           }else{
                set_alert('danger', _l('imported_fail'));
           }
        }else{
            set_alert('warning', _l('no_transaction'));
        }


    }

    public function insertTransactionRecord($datas, $bankId){
        $i = 0;
        foreach($datas as $data){
            $amount = $data->amount;
            $checkNumber = $data->check_number;
            $date = $data->date;
            $description = $data->original_description;
            $payment_status = $data->pending;
            $transaction_id = $data->transaction_id;
            $payee = $data->payment_meta->payee;

            if($payment_status == false){
               $paymentData = [];
               $paymentData['date'] = $date;
               $paymentData['datecreated'] = date('Y-m-d H:i:s');
               //$paymentData['check_number'] = $checkNumber;
               $paymentData['status'] = 1;
               $paymentData['transaction_id'] = $transaction_id;
               $paymentData['withdrawals'] = $amount < 0 ? 0 : abs($amount);
               $paymentData['deposits'] = $amount > 0 ? 0 : abs($amount);
               $paymentData['addedFrom'] = get_staff_user_id();
               $paymentData['description'] = $description;
               $paymentData['payee'] = $payee;
               $paymentData['bank_id'] = $bankId;
               //$paymentData['bank_account'] = $bankId;


               //Check if Transaction Id Already Exists or not
               $this->db->where('transaction_id', $transaction_id);
               $this->db->where('bank_id', $bankId);
                $query = $this->db->get(db_prefix() . 'acc_transaction_bankings')->row();
               
                if(!$query){
                    $this->db->insert(db_prefix() . 'acc_transaction_bankings', $paymentData);
                    $id = $this->db->insert_id();
                    if($id){
                        $i++;
                    }
                }
            }
        }

        if($i > 0){
            return true;
        }

        return false;
    }

    /**
     * { match transactions }
     *
     * @param        $reconcile_id  The reconcile identifier
     * @param        $account_id    The account identifier
     */
    public function match_transactions($reconcile_id, $account_id){

        $success = $this->accounting_model->match_transactions($reconcile_id, $account_id);
        $message = _l('match_fail');
        if($success == 1){
            $message = _l('matched_successfully');
        }

        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        die;
    }

    /**
     * { unmatch transactions }
     *
     * @param        $reconcile_id  The reconcile identifier
     * @param        $account_id  The bank account identifier
     */
    public function unmatch_transactions($reconcile_id, $account_id){

        $success = $this->accounting_model->unmatch_transactions($reconcile_id, $account_id);
        $message = _l('unmatch_fail');
        if($success == true){
            $message = _l('unmatched_successfully');
        }

        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        die;
    }

    /**
     * { reconcile transactions table }
     */
    public function reconcile_transactions_table(){
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            $purchase_module_status = acc_get_status_modules('purchase');
             

            $select = [
                'date',
                //'number',
                //db_prefix().'acc_vendor.company as vendor_name',
                'vendor',
                'description',
                'credit',
                'debit',
                'cleared',
            ];
            $where = [];

            $from_date = '';
            $to_date = '';

            $bank_account = '';
            if ($this->input->post('account')) {
                $bank_account = $this->input->post('account');
                array_push($where, 'AND account ='. $bank_account);
            }

            if($this->input->post('reconcile')){
                $reconcile_id = $this->input->post('reconcile');

                $reconcile = $this->accounting_model->get_reconcile($reconcile_id);
                if($reconcile){
                    $to_date = $reconcile->ending_date;
                }

                if($bank_account != ''){
                    $recently_reconcile = $this->accounting_model->get_recently_reconcile_by_account($bank_account, $reconcile_id);
                    if($recently_reconcile){
                        $from_date = $recently_reconcile->ending_date;
                    }
                }

                array_push($where, 'AND ('.db_prefix() . 'acc_account_history.reconcile ='. $reconcile_id.' or '.db_prefix() . 'acc_account_history.reconcile = 0)');

            }

            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (date > "' . $from_date . '" and date <= "' . $to_date . '")');
            } elseif ($to_date != '' && $from_date == '') {
                array_push($where, 'AND (date <= "' . $to_date . '")');
            }

            $aColumns = $select;
            $sIndexColumn = 'id';
            $sTable = db_prefix() . 'acc_account_history';
            $join = [
                // 'LEFT JOIN ' . db_prefix() . 'acc_vendor ON ' . db_prefix() . 'acc_vendor.userid = ' . db_prefix() . 'acc_account_history.vendor',
                    ];
            $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [db_prefix() . 'acc_account_history.id as id', 'account', 'description', 'customer', 'rel_type', 'cleared']);

            $output = $result['output'];
            $rResult = $result['rResult'];

            $balance = 0;

            foreach ($rResult as $aRow) {
                $row = [];
                
                $row[] = _d($aRow['date']);

                // if($aRow['rel_type'] == 'check'){
                //     $row[] = '#'.str_pad($aRow['number'], 4, '0', STR_PAD_LEFT);
                // }else{
                //     $row[] = '';
                // }

                if($aRow['vendor'] != 0){
                    if($purchase_module_status){
                        $row[] = get_vendor_company_name($purchase_order->vendor);
                    }else{
                        $row[] = '';
                    }
                }else{
                    $row[] = '';
                }

                $row[] = $aRow['description'];


                if($aRow['credit'] != 0){
                    $row[] = app_format_money($aRow['credit'], $currency->name);
                }else{
                    $row[] = '';
                }

                if($aRow['debit'] != 0){
                    $row[] = app_format_money($aRow['debit'], $currency->name);
                }else{
                    $row[] = '';
                }

                $status_name = _l('not_yet_match');
                $label_class = 'default';

                if ($aRow['cleared'] > 0) {
                    $row[] = '<i class="fa fa-check-circle text-success fa-lg" aria-hidden="true"></i>';
                }elseif($aRow['cleared'] == 0){
                    $row[] = '';
                }else{
                    $row[] = '<i class="fa fa-times-circle text-danger fa-lg" aria-hidden="true"></i>';
                }

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    /**
     * { reconcile posted bank table }
     */
    public function reconcile_posted_bank_table(){
        if ($this->input->is_ajax_request()) {
            $this->load->model('currencies_model');

            $currency = $this->currencies_model->get_base_currency();
            
            $select = [
                'id',
                'date',
                'payee',
                //'check_number',
                'withdrawals',
                'deposits',
                'bank_id',
            ];
            $where = [];

            $from_date = '';
            $to_date = '';

            $bank_account = '';
            if ($this->input->post('account')) {
                $bank_account = $this->input->post('account');
                array_push($where, 'AND bank_id ='. $bank_account);
            }

            if($this->input->post('reconcile')){
                $reconcile_id = $this->input->post('reconcile');
                array_push($where, 'AND (reconcile = 0 or reconcile = '.$reconcile_id.')');

                $reconcile = $this->accounting_model->get_reconcile($reconcile_id);


                if($reconcile){
                    $to_date = $reconcile->ending_date;
                }


                if($bank_account != ''){
                    $recently_reconcile = $this->accounting_model->get_recently_reconcile_by_account($bank_account, $reconcile_id);
                    if($recently_reconcile){
                        $from_date = $recently_reconcile->ending_date;
                    }
                }
            }

            if ($from_date != '' && $to_date != '') {
                array_push($where, 'AND (date > "' . $from_date . '" and date <= "' . $to_date . '")');
            } elseif ($to_date != '' && $from_date == '') {
                array_push($where, 'AND (date <= "' . $to_date . '")');
            }


            $aColumns = $select;
            $sIndexColumn = 'id';
            $sTable = db_prefix() . 'acc_transaction_bankings';
            $join = [];
            $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['description', 'datecreated', 'matched']);

            $output = $result['output'];
            $rResult = $result['rResult'];

            $balance = 0;

            foreach ($rResult as $aRow) {
                $row = [];
                
                $row[] = _d($aRow['date']);

                // if($aRow['check_number'] != 0){
                //     $row[] = '#'.str_pad($aRow['check_number'], 4, '0', STR_PAD_LEFT);
                // }else{
                //     $row[] = '';
                // }
                
                $row[] = $aRow['payee'];
                $row[] = $aRow['description'];

                if($aRow['withdrawals'] != 0){
                    $row[] = app_format_money($aRow['withdrawals'], $currency->name);
                }else{
                    $row[] = '';
                }

                if($aRow['deposits'] != 0){
                    $row[] = app_format_money($aRow['deposits'], $currency->name);
                }else{
                    $row[] = '';
                }

                $status_name = _l('not_yet_match');
                $label_class = 'default';

                if ($aRow['matched'] == 1) {
                    $row[] = '<i class="fa fa-check-circle text-success fa-lg" aria-hidden="true"></i>';
                }elseif($aRow['matched'] == 0){
                    $row[] = '';
                }else{
                    $row[] = '<i class="fa fa-times-circle text-danger fa-lg" aria-hidden="true"></i>';
                }

                $output['aaData'][] = $row;
            }

            echo json_encode($output);
            die();
        }
    }

    public function get_transaction_uncleared(){
        $data = $this->input->post();
        $transaction_bankings = $this->accounting_model->get_transaction_uncleared($data['reconcile_id']);
        $status = 0;
        $html = '';

        if(count($transaction_bankings) > 0){
            foreach($transaction_bankings as $transaction){
                if($transaction['adjusted'] == 1){
                    $html .= '<tr><td>'._d($transaction['date']).'</td><td>'.$transaction['payee'].'</td><td>'.$transaction['description'].'</td><td>'.$transaction['withdrawals'].'</td><td>'.$transaction['deposits'].'</td><td><i class="fa fa-check-circle text-success fa-2x" aria-hidden="true"></i></td></tr>';
                }else{
                    $status = 1;
                    $html .= '<tr><td>'._d($transaction['date']).'</td><td>'.$transaction['payee'].'</td><td>'.$transaction['description'].'</td><td>'.$transaction['withdrawals'].'</td><td>'.$transaction['deposits'].'</td><td><a href="#" class="btn btn-info" onclick="make_adjusting_entry('.$transaction['id'].'); return false;">'. _l('make_adjusting_entry').'</a><br><br><a href="#" class="btn btn-warning" onclick="leave_it_uncleared(this); return false;" data-id="'.$transaction['id'].'">'. _l('leave_it_uncleared').'</a></td></tr>';
                }
            }
        }

        echo json_encode([
            'status' => $status,
            'html' => $html,
        ]);
        die;
    }

    public function get_make_adjusting_entry(){
        $this->load->model('currencies_model');
        $purchase_module_status = acc_get_status_modules('purchase');

        $currency = $this->currencies_model->get_base_currency();
        $data = $this->input->post();

        $transaction_banking = $this->accounting_model->get_transaction_banking($data['transaction_bank_id']);


        $amount = app_format_money($transaction_banking->withdrawals, $currency->name);

        if($transaction_banking->deposits > 0){
            $amount = app_format_money(-$transaction_banking->deposits, $currency->name);
        }

        $transaction_uncleared = $this->accounting_model->get_bank_transaction_uncleared($data['reconcile_id']);
        $tran_html = '';
        $tran_withdrawals = 0;
        $tran_deposits = 0;
        foreach($transaction_uncleared as $key => $tran){
            $payee = '';
            if($purchase_module_status){
                $payee = get_vendor_company_name($tran['vendor']);
            }
            $date = _d($tran['date']);
            

            $selected = '';

            if($key < 1){
                $selected = 'selected';
            }

            $name = 'Date: '.$date.' Payee: '.$payee;
            if($tran['credit'] > 0){
                $withdrawals = number_format($tran['credit'],2);
                $name .= ' Withdrawals: '.$withdrawals;
                if($key < 1){
                    $tran_withdrawals = $withdrawals;
                }
            }else{
                $deposits = number_format($tran['debit'],2);
                $name .= ' Deposits: '.$deposits;
                if($key < 1){
                    $tran_deposits = $deposits;
                }
            }

            $tran_html .= '<option value="'.$tran['id'].'" '.$selected.'>'.$name.'</option>';
        }

        echo json_encode([
            'date' => date('m/d/Y', strtotime($transaction_banking->date)),
            'amount' => $amount,
            'payee' => $transaction_banking->payee ? $transaction_banking->payee : '',
            'tran_html' => $tran_html,
            'date_value' => _d($transaction_banking->date),
            'tran_deposit' => $tran_deposits,
            'tran_withdrawal' => $tran_withdrawals
        ]);
        die;
    }

    public function make_adjusting_entry_save(){
        $data = $this->input->post();
        
        $success = $this->accounting_model->make_adjusting_entry_save($data);

        echo json_encode([
            'success' => $success,
            'message' => _l('updated_successfully', _l('transaction'))
        ]);
        die;
    }

    public function leave_it_uncleared(){
        $data = $this->input->post();
        $success = $this->accounting_model->leave_it_uncleared($data['transaction_bank_id']);

        echo json_encode([
            'success' => $success,
            'message' => _l('updated_successfully', _l('transaction'))
        ]);
        die;
    }

    public function check_complete_reconcile(){
        $this->load->model('currencies_model');

        $currency = $this->currencies_model->get_base_currency();
        $data = $this->input->post();
        $leave_uncleared = 0;
        $transaction_bankings = $this->accounting_model->get_transaction_leave_uncleared($data['reconcile_id']);
        $reconcile_difference_info = $this->accounting_model->get_reconcile_difference_info($data['reconcile_id']);

        if(count($transaction_bankings) > 0){
            $leave_uncleared = 1;
        }


        $difference_withdrawals = abs($reconcile_difference_info['banking_register_withdrawals'] - $reconcile_difference_info['posted_bank_withdrawals']);
        $difference_deposits = abs($reconcile_difference_info['banking_register_deposits'] - $reconcile_difference_info['posted_bank_deposits']);

        $html = '';
        if($leave_uncleared == 1){
            $html .= '
            <table class="table table-checks-to-print scroll-responsive dataTable">
                 <tbody>
                 <tr>
                    <td colspan="3">'. _l('you_are_reconciling_with_uncleared_transactions') .'</td>
                  </tr>
                  <tr>
                    <td>'. _l('acc_banking_register') .'</td>
                    <td>'.app_format_money($reconcile_difference_info['banking_register_withdrawals'], $currency->name).'</td>
                    <td>'.app_format_money($reconcile_difference_info['banking_register_deposits'], $currency->name).'</td>
                  </tr>
                  <tr>
                    <td>'. _l('posted_bank_transactions') .'</td>
                    <td>'.app_format_money($reconcile_difference_info['posted_bank_withdrawals'], $currency->name).'</td>
                    <td>'.app_format_money($reconcile_difference_info['posted_bank_deposits'], $currency->name).'</td>
                  </tr>
                  <tr>
                    <td>'. _l('difference') .'</td>
                    <td>'.app_format_money($difference_withdrawals, $currency->name).'</td>
                    <td>'.app_format_money($difference_deposits, $currency->name).'</td>
                  </tr>
                  <tr>
                    <td>'. _l('total_difference') .'</td>
                    <td>'.app_format_money(($difference_withdrawals + $difference_deposits), $currency->name).'</td>
                    <td></td>
                  </tr>
                </tbody>
            </table>';
        }

        echo json_encode([
            'leave_uncleared' => $leave_uncleared,
            'html' => $html,
        ]);
        die;

    }

    /**
     *
     *  add adjustment
     *  @return view
     */
    public function bank_account_adjustment() {
        if (!has_permission('accounting_reconcile', '', 'create')) {
            access_denied('accounting');
        }
        if ($this->input->post()) {
            $data = $this->input->post();

            $message = '';
            $success = $this->accounting_model->add_bank_account_adjustment($data);

            if ($success === 'close_the_book') {
                $message = _l('has_closed_the_book');
            } elseif ($success) {
                $message = _l('added_successfully', _l('adjustment'));
            } else {
                $message = _l('add_failure');
            }

            echo json_encode(['success' => $success, 'message' => $message]);
            die();
        }
    }

    /**
     * finish reconcile bank account
     * @return view
     */
    public function finish_reconcile_bank_account() {
        if (!has_permission('accounting_reconcile', '', 'create') && !is_admin()) {
            access_denied('accounting_reconcile');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $message = '';
            $success = $this->accounting_model->finish_reconcile_bank_account($data);

            if ($success) {
                $message = _l('added_successfully', _l('reconcile'));
                set_alert('success', $message);
            } else {
                $message = _l('add_failure');
                set_alert('warning', $message);
            }
        }

        redirect(admin_url('accounting/banking?group=reconcile_bank_account'));
    }

    /**
     * reconcile restored
     * @param  [type] $account 
     * @param  [type] $company 
     * @return [type]          
     */
    public function reconcile_bank_account_restored($account) {
        if ($this->input->is_ajax_request()) {
            $success = false;
            $message = _l('acc_restored_failure');
            $hide_restored = true;
            
            $reconcile_restored = $this->accounting_model->reconcile_bank_account_restored($account);
            if($reconcile_restored){
                $success = true;
                $message = _l('acc_restored_successfully');
            }

            $check_reconcile_restored = $this->accounting_model->check_reconcile_restored($account);
            if($check_reconcile_restored){
                $hide_restored = false;
            }
            
            echo json_encode([
                'success' => $success,
                'hide_restored' => $hide_restored,
                'message' => $message,
            ]);
            die();
        }
    }
    
    /**
     * get info reconcile
     * @param  integer $account
     * @return json
     */
    public function get_info_reconcile_bank_account($account) {
        $reconcile = $this->accounting_model->get_reconcile_by_account($account);
        $beginning_balance = 0;
        $resume_reconciling = false;
        $approval_reconciling = false;
        $hide_restored = true;

        $edit_debits_for_period = 0;
        $edit_credits_for_period = 0;
        $edit_ending_date = '';
        $edit_ending_balance = 0;
        $edit_beginning_balance = 0;
        $edit_reconcile_id = 0;

        $check_reconcile_restored = $this->accounting_model->check_reconcile_restored($account);
        if($check_reconcile_restored){
            $hide_restored = false;
        }
        $closing_date = false;

        if ($reconcile) {
            if(get_option('acc_close_the_books') == 1){
                $closing_date = (strtotime($reconcile->ending_balance) > strtotime(date('Y-m-d'))) ? true : false;
            }
            $beginning_balance = $reconcile->ending_balance;
            if ($reconcile->finish == 0 || $reconcile->finish == null) {
                $resume_reconciling = true;
            }

            // if ($reconcile->finish == 1 && ($reconcile->approval == 0 || $reconcile->approval == null)) {
            //     $approval_reconciling = true;
            // }

            $edit_debits_for_period = $reconcile->debits_for_period;
            $edit_credits_for_period = $reconcile->credits_for_period;
            $edit_ending_date = _d($reconcile->ending_date);
            $edit_ending_balance = $reconcile->ending_balance;
            $edit_beginning_balance = $reconcile->beginning_balance;
            $edit_reconcile_id = $reconcile->id;

        }


        echo json_encode(['beginning_balance' => $beginning_balance, 'resume_reconciling' => $resume_reconciling, 'hide_restored' => $hide_restored, 'closing_date' => $closing_date, 'edit_debits_for_period' => $edit_debits_for_period, 'edit_credits_for_period' => $edit_credits_for_period, 'edit_ending_date' => $edit_ending_date, 'edit_ending_balance' => $edit_ending_balance, 'edit_beginning_balance' => $edit_beginning_balance, 'edit_reconcile_id' => $edit_reconcile_id, 'approval_reconciling' => $approval_reconciling ]);
        die();
    }

    /**
     * report bank reconciliation summary
     * @return view
     */
    public function rp_bank_reconciliation_summary() {
        $this->load->model('currencies_model');
        $data['title'] = _l('bank_reconciliation_summary');

        $data['from_date'] = date('Y-m-d');
        $data['to_date'] = date('Y-m-d');

        $data['bank_accounts'] = $this->accounting_model->get_accounts('', ['account_detail_type_id' => 14]);

        $data['default_account'] = '';
        if (isset($data['bank_accounts'][0])) {
            $data['default_account'] = $data['bank_accounts'][0]['id'];
        }

        $data['reconcile'] = $this->accounting_model->get_reconcile('', 'account = "'.$data['default_account'].'"');
        foreach($data['reconcile'] as $key => $reconcile){
            $data['reconcile'][$key]['ending_date'] = date('m/d/Y', strtotime($reconcile['ending_date']));
        }

        $data['default_reconcile'] = '';
        if (isset($data['reconcile'][0])) {
            $data['default_reconcile'] = $data['reconcile'][0]['id'];
        }

        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/bank_reconciliation_summary', $data);
    }

    /**
     * report bank reconciliation summary
     * @return view
     */
    public function rp_bank_reconciliation_detail() {
        $this->load->model('currencies_model');
        $data['title'] = _l('bank_reconciliation_detail');

        $data['from_date'] = date('Y-m-d');
        $data['to_date'] = date('Y-m-d');
     
        $data['bank_accounts'] = $this->accounting_model->get_accounts('', ['account_detail_type_id' => 14]);

        $data['default_account'] = '';
        if (isset($data['bank_accounts'][0])) {
            $data['default_account'] = $data['bank_accounts'][0]['id'];
        }

        $data['reconcile']= $this->accounting_model->get_reconcile('', 'account = "'.$data['default_account'].'"');

        foreach($data['reconcile'] as $key => $reconcile){
            $data['reconcile'][$key]['ending_date'] = date('m/d/Y', strtotime($reconcile['ending_date']));
        }

        $data['default_reconcile'] = '';
        if (isset($data['reconcile'][0])) {
            $data['default_reconcile'] = $data['reconcile'][0]['id'];
        }

        $data['currency'] = $this->currencies_model->get_base_currency();
        $this->load->view('report/includes/bank_reconciliation_detail', $data);
    }

    /**
     * { reconcile account change }
     *
     * @param      <string>  $type   The type
     */
     public function reconcile_account_change($account = ''){
        $html = '';

        $reconcile = $this->accounting_model->get_reconcile('', 'opening_balance = 0 and account = "'.$account.'"');

        $html = ''; 
        foreach($reconcile as $key => $value){
            $selected = '';

            if($key < 1){
                $selected = 'selected';
            }

            $html .= '<option value="'.$value['id'].'" '.$selected.'>'._d($value['ending_date']).'</option>';
        }

        echo json_encode($html);

     }

     /**
     * import xlsx banking
     * @return view
     */
    public function import_xlsx_posted_bank_transactions() {
        if (!has_permission('accounting_transaction', '', 'create')) {
            access_denied('accounting_transaction');
        }

        $this->load->model('staff_model');
        $data_staff = $this->staff_model->get(get_staff_user_id());

        /*get language active*/
        if ($data_staff) {
            if ($data_staff->default_language != '') {
                $data['active_language'] = $data_staff->default_language;

            } else {

                $data['active_language'] = get_option('active_language');
            }

        } else {
            $data['active_language'] = get_option('active_language');
        }
        $data['title'] = _l('import_excel');
        $data['bank_accounts'] = $this->accounting_model->get_accounts('', ['account_detail_type_id' => 14]);

        $this->load->view('banking/import_banking', $data);
    }

    /**
     * import file xlsx banking
     * @return json
     */
    public function import_file_xlsx_posted_bank_transactions(){
        if(!class_exists('XLSXReader_fin')){
            require_once(module_dir_path(ACCOUNTING_MODULE_NAME).'assets/plugins/XLSXReader/XLSXReader.php');
        }
        require_once(module_dir_path(ACCOUNTING_MODULE_NAME).'assets/plugins/XLSXWriter/xlsxwriter.class.php');

        $filename ='';
        if($this->input->post()){
            $data_filter = $this->input->post();
            if (isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '') {
                $this->delete_error_file_day_before(1, ACCOUTING_IMPORT_ITEM_ERROR);

                // Get the temp file path
                $tmpFilePath = $_FILES['file_csv']['tmp_name'];                
                // Make sure we have a filepath
                if (!empty($tmpFilePath) && $tmpFilePath != '') {
                    $rows          = [];
                    $arr_insert          = [];

                    $tmpDir = TEMP_FOLDER . '/' . time() . uniqid() . '/';

                    if (!file_exists(TEMP_FOLDER)) {
                        mkdir(TEMP_FOLDER, 0755);
                    }

                    if (!file_exists($tmpDir)) {
                        mkdir($tmpDir, 0755);
                    }

                    // Setup our new file path
                    $newFilePath = $tmpDir . $_FILES['file_csv']['name'];                    

                    if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                        //Writer file
                        $writer_header = array(
                            _l('invoice_payments_table_date_heading').' (dd/mm/YYYY)'            =>'string',
                            _l('withdrawals')     =>'string',
                            _l('deposits')    =>'string',
                            _l('payee')      =>'string',
                            _l('description')     =>'string',
                            _l('error')       =>'string',
                        );

                        $rowstyle[] =array('widths'=>[10,20,30,40]);

                        $writer = new XLSXWriter();
                        $writer->writeSheetHeader('Sheet1', $writer_header,  $col_options = ['widths'=>[40,40,40,40,50,50]]);

                        //Reader file
                        $xlsx = new XLSXReader_fin($newFilePath);
                        $sheetNames = $xlsx->getSheetNames();
                        $data = $xlsx->getSheetData($sheetNames[1]);

                        $arr_header = [];

                        $arr_header['date'] = 0;
                        $arr_header['withdrawals'] = 1;
                        $arr_header['deposits'] = 2;
                        $arr_header['payee'] = 3;
                        $arr_header['description'] = 4;

                        $total_rows = 0;
                        $total_row_false    = 0; 

                        for ($row = 1; $row < count($data); $row++) {

                            $total_rows++;

                            $rd = array();
                            $flag = 0;
                            $flag2 = 0;

                            $string_error ='';
                            $flag_position_group;
                            $flag_department = null;

                            $value_date  = isset($data[$row][$arr_header['date']]) ? $data[$row][$arr_header['date']] : '' ;
                            $value_withdrawals   = isset($data[$row][$arr_header['withdrawals']]) ? $data[$row][$arr_header['withdrawals']] : '' ;
                            $value_deposits     = isset($data[$row][$arr_header['deposits']]) ? $data[$row][$arr_header['deposits']] : '' ;
                            $value_payee    = isset($data[$row][$arr_header['payee']]) ? $data[$row][$arr_header['payee']] : '' ;
                            $value_description   = isset($data[$row][$arr_header['description']]) ? $data[$row][$arr_header['description']] : '' ;
                            
                            $reg_day = '/([0-9]{2})\/([0-9]{2})\/([0-9]{4})/'; /*yyyy-mm-dd*/

                            if(is_numeric($value_date)){
                                $value_date = $this->accounting_model->convert_excel_date($value_date);
                            }

                            if(is_null($value_date) != true){
                                if(preg_match($reg_day, $value_date, $match) != 1){
                                    $string_error .=_l('invoice_payments_table_date_heading'). _l('invalid');
                                    $flag = 1; 
                                }
                            }else{
                                $string_error .= _l('invoice_payments_table_date_heading') . _l('not_yet_entered');
                                $flag = 1;
                            }

                            if (is_null($value_withdrawals) == true) {
                                $string_error .= _l('withdrawals') . _l('not_yet_entered');
                                $flag = 1;
                            }else{
                                if(!is_numeric($value_withdrawals) && $value_deposits == ''){
                                    $string_error .= _l('withdrawals') . _l('invalid');
                                    $flag = 1;
                                }
                            }

                            if (is_null($value_deposits) == true) {
                                $string_error .= _l('deposits') . _l('not_yet_entered');
                                $flag = 1;
                            }else{
                                if(!is_numeric($value_deposits) && $value_withdrawals == ''){
                                    $string_error .= _l('deposits') . _l('invalid');
                                    $flag = 1;
                                }
                            }

                            if (is_null($value_payee) == true) {
                                $string_error .= _l('payee') . _l('not_yet_entered');
                                $flag = 1;
                            }
                            

                            if(($flag == 1) || $flag2 == 1 ){
                                //write error file
                                $writer->writeSheetRow('Sheet1', [
                                    $value_date,
                                    $value_withdrawals,
                                    $value_deposits,
                                    $value_payee,
                                    $value_description,
                                    $string_error,
                                ]);

                                // $numRow++;
                                $total_row_false++;
                            }

                            if($flag == 0 && $flag2 == 0){

                                $rd['date']       = $value_date;
                                $rd['withdrawals']         = $value_withdrawals;
                                $rd['deposits']        = $value_deposits;
                                $rd['payee']       = $value_payee;
                                $rd['bank_id']       = $data_filter['bank_account'];
                                $rd['description']               = $value_description;
                                $rd['datecreated']               = date('Y-m-d H:i:s');
                                $rd['addedfrom']               = get_staff_user_id();

                                $rows[] = $rd;
                                array_push($arr_insert, $rd);

                            }

                        }

                        //insert batch
                        if(count($arr_insert) > 0){
                            $this->accounting_model->insert_batch_banking($arr_insert);
                        }

                        $total_rows = $total_rows;
                        $total_row_success = isset($rows) ? count($rows) : 0;
                        $dataerror = '';
                        $message ='Not enought rows for importing';

                        if($total_row_false != 0){
                            $filename = 'Import_banking_error_'.get_staff_user_id().'_'.strtotime(date('Y-m-d H:i:s')).'.xlsx';
                            $writer->writeToFile(str_replace($filename, ACCOUTING_IMPORT_ITEM_ERROR.$filename, $filename));
                        }


                    }
                }
            }
        }


        if (file_exists($newFilePath)) {
            @unlink($newFilePath);
        }

        echo json_encode([
            'message'           => $message,
            'total_row_success' => $total_row_success,
            'total_row_false'   => $total_row_false,
            'total_rows'        => $total_rows,
            'site_url'          => site_url(),
            'staff_id'          => get_staff_user_id(),
            'filename'          => ACCOUTING_IMPORT_ITEM_ERROR.$filename,
        ]);
    }

    public function update_bank_reconcile() {
        if ($this->input->is_ajax_request()) {
            $data = $this->input->get();

            if(isset($data['csrf_token_name'])){
                unset($data['csrf_token_name']);
            }

            $id = 0;
            if(isset($data['reconcile_id'])){
                $id = $data['reconcile_id'];
                unset($data['reconcile_id']);
            }

            $success = false;
            $message = _l('accounting_no_data_changes');
            
            $update_reconcile = $this->accounting_model->ajax_update_reconcile($data, $id);
            if($update_reconcile){
                $success = true;
                $message = _l('saved_successfully');
            }

            echo json_encode([
                'success' => $success,
                'message' => $message,
            ]);
            die();
        }
    }
}