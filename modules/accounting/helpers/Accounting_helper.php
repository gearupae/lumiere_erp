<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * get status modules wh
 * @param  string $module_name 
 * @return boolean             
 */
function acc_get_status_modules($module_name){
	$CI             = &get_instance();

	$sql = 'select * from '.db_prefix().'modules where module_name = "'.$module_name.'" AND active =1 ';
	$module = $CI->db->query($sql)->row();
	if($module){
		return true;
	}else{
		return false;
	}
}

/**
 * check account exists
 * @param  string $key_name 
 * @return boolean or integer           
 */
function acc_account_exists($key_name){
	$CI             = &get_instance();

	$CI->load->model('accounting/accounting_model');

	if(get_option('acc_add_default_account') == 0){
        $CI->accounting_model->add_default_account();
    }

    if(get_option('acc_add_default_account_new') == 0){
        $CI->accounting_model->add_default_account_new();
    }

	$sql = 'select * from '.db_prefix().'acc_accounts where key_name = "'.$key_name.'"';
	$account = $CI->db->query($sql)->row();

	if($account){
		return $account->id;
	}else{
		return false;
	}
}

/**
 * Gets the account type by name.
 *
 * @param        $name   The name
 */
function get_account_type_by_name($name){
	$CI             = &get_instance();
	$CI->load->model('accounting/accounting_model');
	$account_types = $CI->accounting_model->get_account_types();
	
	foreach($account_types as $type){
		if($type['name'] == $name){
			return $type['id'];
		}
	}

	return false;
}

/**
 * Gets the account type by name.
 *
 * @param        $name   The name
 */
function get_account_sub_type_by_name($name){
	$CI             = &get_instance();
	$CI->load->model('accounting/accounting_model');
	$account_sub_types = $CI->accounting_model->get_account_type_details();

	foreach($account_sub_types as $type){
		if($type['name'] == $name){
			return $type['id'];
		}
	}

	return false;
}

/**
 * Gets the account by name.
 *
 * @param        $name     The name
 */
function get_account_by_name($name){
	$CI             = &get_instance();
	$CI->db->where('name', $name);
	$CI->db->where('name IS NOT NULL');
	$CI->db->where('name <> ""');

	$account = $CI->db->get(db_prefix().'acc_accounts')->row();

	if($account){
		return $account->id;
	}
	return false;
}

/**
 * Gets the account type by id.
 *
 * @param        $id   The id
 */
function get_account_type_by_id($id){
	$CI             = &get_instance();
	$CI->load->model('accounting/accounting_model');
	$account_types = $CI->accounting_model->get_account_types();

	foreach($account_types as $type){
		if($type['id'] == $id){
			return $type['id'];
		}
	}

	return false;
}
/**
 * Gets the account type by id.
 *
 * @param        $id   The id
 */
function get_account_sub_type_by_id($id){
	$CI             = &get_instance();
	$CI->load->model('accounting/accounting_model');
	$account_sub_types = $CI->accounting_model->get_account_type_details();

	foreach($account_sub_types as $type){
		if($type['id'] == $id){
			return $type['id'];
		}
	}

	return false;
}

/**
 * Gets the account by identifier.
 *
 * @param        $id     The identifier
 */
function get_account_by_id($id){
	$CI             = &get_instance();
	$CI->db->where('id', $id);
	$account = $CI->db->get(db_prefix().'acc_accounts')->row();

	if($account){
		return $id;
	}
	return false;
}


/**
 * Gets the url by type identifier.
 */
function get_url_by_type_id($rel_type, $rel_id){
	$url = '';
	switch ($rel_type) {
        case 'invoice':
            $url = admin_url('invoices/list_invoices/'.$rel_id);
        break;

        case 'bill':
            $url = admin_url('accounting/bills#'.$rel_id);
        break;

        case 'expense':
            $url = admin_url('expenses/list_expenses/'.$rel_id);
        break;

        case 'pay_bill':
            $url = admin_url('accounting/pay_bill/'.$rel_id);
        break;

        case 'payment':
            $url = admin_url('payments/payment/'.$rel_id);
        break;

        case 'journal_entry':
            $url = admin_url('accounting/new_journal_entry/'.$rel_id);
        break;

        case 'user_register_transaction':
            $url = admin_url('accounting/user_register_view/'.$rel_id);
        break;

        case 'transfer':
            $url = admin_url('accounting/transfer?transfer_id='.$rel_id);
        break;
        
        case 'check':
            $url = admin_url('accounting/checks#'.$rel_id);
        break;
    }

    return $url;
}
