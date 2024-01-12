<?php

defined('BASEPATH') or exit('No direct script access allowed');

add_option('acc_first_month_of_financial_year', 'January');
add_option('acc_first_month_of_tax_year', 'same_as_financial_year');
add_option('acc_accounting_method', 'accrual');
add_option('acc_close_the_books', 0);
add_option('acc_allow_changes_after_viewing', 'allow_changes_after_viewing_a_warning');
add_option('acc_close_book_password');
add_option('acc_close_book_passwordr');
add_option('acc_enable_account_numbers', 0);
add_option('acc_show_account_numbers', 0);
add_option('acc_closing_date');

add_option('acc_add_default_account', 0);
add_option('acc_add_default_account_new', 0);
add_option('acc_invoice_automatic_conversion', 1);
add_option('acc_payment_automatic_conversion', 1);
add_option('acc_credit_note_automatic_conversion', 1);
add_option('acc_expense_automatic_conversion', 1);
add_option('acc_tax_automatic_conversion', 1);

add_option('acc_invoice_payment_account', 66);
add_option('acc_invoice_deposit_to', 1);
add_option('acc_payment_payment_account', 1);
add_option('acc_payment_deposit_to', 13);
add_option('acc_credit_note_payment_account', 1);
add_option('acc_credit_note_deposit_to', 13);
add_option('acc_expense_payment_account', 13);
add_option('acc_expense_deposit_to', 37);
add_option('acc_tax_payment_account', 29);
add_option('acc_tax_deposit_to', 1);
add_option('acc_expense_tax_payment_account', 13);
add_option('acc_expense_tax_deposit_to', 29);

add_option('acc_active_payment_mode_mapping', 1);
add_option('acc_active_expense_category_mapping', 1);

if (!$CI->db->table_exists(db_prefix() . 'acc_accounts')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_accounts` (
  	  `id` INT(11) NOT NULL AUTO_INCREMENT,
	  `name` VARCHAR(255) NOT NULL,
    `key_name` VARCHAR(255) NULL,
	  `number` VARCHAR(45) NULL,
	  `parent_account` INT(11) NULL,
	  `account_type_id` INT(11) NOT NULL,
	  `account_detail_type_id` INT(11) NOT NULL,
	  `balance` DECIMAL(15,2) NULL,
	  `balance_as_of` DATE NULL,
	  `description` TEXT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_account_history')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_account_history` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `account` INT(11) NOT NULL,
      `debit` DECIMAL(15,2) NOT NULL DEFAULT 0,
      `credit` DECIMAL(15,2) NOT NULL DEFAULT 0,
      `description` TEXT NULL,
      `rel_id` INT(11) NULL,
      `rel_type` VARCHAR(45) NULL,
      `datecreated` DATETIME NULL,
      `addedfrom` INT(11) NULL,
      `customer` INT(11) NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_transfers')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_transfers` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `transfer_funds_from` INT(11) NOT NULL,
      `transfer_funds_to` INT(11) NOT NULL,
      `transfer_amount` DECIMAL(15,2) NULL,
      `date` VARCHAR(45) NULL,
      `description` TEXT NULL,
      `datecreated` DATETIME NULL,
      `addedfrom` INT(11) NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_journal_entries')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_journal_entries` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `number` VARCHAR(45) NULL,
      `description` TEXT NULL,
      `journal_date` DATE NULL,
      `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
      `datecreated` DATETIME NULL,
      `addedfrom` INT(11) NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_transaction_bankings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_transaction_bankings` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `date` DATE NOT NULL,
      `withdrawals` DECIMAL(15,2) NOT NULL DEFAULT 0,
      `deposits` DECIMAL(15,2) NOT NULL DEFAULT 0,
      `payee` VARCHAR(255) NULL,
      `description` TEXT NULL,
      `datecreated` DATETIME NULL,
      `addedfrom` INT(11) NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_reconciles')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_reconciles` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `account` INT(11) NOT NULL,
      `beginning_balance` DECIMAL(15,2) NOT NULL,
      `ending_balance` DECIMAL(15,2) NOT NULL,
      `ending_date` DATE NOT NULL,
      `expense_date` DATE NULL,
      `service_charge` DECIMAL(15,2) NULL,
      `expense_account` INT(11) NULL,
      `income_date` DATE NULL,
      `interest_earned` DECIMAL(15,2) NULL,
      `income_account` INT(11) NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('reconcile' ,db_prefix() . 'acc_account_history')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_account_history`
    ADD COLUMN `reconcile` INT(11) NOT NULL DEFAULT 0;');
}

if (!$CI->db->field_exists('finish' ,db_prefix() . 'acc_reconciles')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_reconciles`
    ADD COLUMN `finish` INT(11) NOT NULL DEFAULT 0;');
}

if (!$CI->db->field_exists('split' ,db_prefix() . 'acc_account_history')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_account_history`
    ADD COLUMN `split` INT(11) NOT NULL DEFAULT 0;');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_banking_rules')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_banking_rules` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(255) NOT NULL,
      `transaction` VARCHAR(45) NULL,
      `following` VARCHAR(45) NULL,
      `then` VARCHAR(45) NULL,
      `payment_account` INT(11) NULL,
      `deposit_to` INT(11) NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_banking_rule_details')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_banking_rule_details` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `rule_id` INT(11) NOT NULL,
      `type` VARCHAR(45) NULL,
      `subtype` VARCHAR(45) NULL,
      `text` VARCHAR(255) NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('auto_add' ,db_prefix() . 'acc_banking_rules')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_banking_rules`
    ADD COLUMN `auto_add` INT(11) NOT NULL DEFAULT 0;');
}

if (!$CI->db->field_exists('subtype_amount' ,db_prefix() . 'acc_banking_rule_details')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_banking_rule_details`
    ADD COLUMN `subtype_amount` VARCHAR(45) NULL;');
}

if (!$CI->db->field_exists('default_account' ,db_prefix() . 'acc_accounts')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_accounts`
    ADD COLUMN `default_account` INT(11) NOT NULL DEFAULT 0,
    ADD COLUMN `active` INT(11) NOT NULL DEFAULT 1;');
}

if (!$CI->db->field_exists('item' ,db_prefix() . 'acc_account_history')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_account_history`
    ADD COLUMN `item` INT(11) NULL,
    ADD COLUMN `paid` INT(1) NOT NULL DEFAULT 0;');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_item_automatics')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_item_automatics` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `item_id` INT(11) NOT NULL,
      `inventory_asset_account` INT(11) NOT NULL DEFAULT 0,
      `income_account` INT(11) NOT NULL DEFAULT 0,
      `expense_account` INT(11) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_tax_mappings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_tax_mappings` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `tax_id` INT(11) NOT NULL,
      `payment_account` INT(11) NOT NULL DEFAULT 0,
      `deposit_to` INT(11) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('date' ,db_prefix() . 'acc_account_history')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_account_history`
    ADD COLUMN `date` DATE NULL;');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_expense_category_mappings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_expense_category_mappings` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `category_id` INT(11) NOT NULL,
      `payment_account` INT(11) NOT NULL DEFAULT 0,
      `deposit_to` INT(11) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('tax' ,db_prefix() . 'acc_account_history')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_account_history`
    ADD COLUMN `tax` INT(11) NULL;');
}


if (!$CI->db->field_exists('expense_payment_account' ,db_prefix() . 'acc_tax_mappings')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_tax_mappings`
    ADD COLUMN `expense_payment_account` INT(11) NOT NULL DEFAULT \'0\',
    ADD COLUMN `expense_deposit_to` INT(11) NOT NULL DEFAULT \'0\';');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_payment_mode_mappings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_payment_mode_mappings` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `payment_mode_id` INT(11) NOT NULL,
      `payment_account` INT(11) NOT NULL DEFAULT 0,
      `deposit_to` INT(11) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

add_option('acc_payment_expense_automatic_conversion', 1);
add_option('acc_payment_sale_automatic_conversion', 1);
add_option('acc_expense_payment_payment_account', 1);
add_option('acc_expense_payment_deposit_to', 1);

if (!$CI->db->table_exists(db_prefix() . 'acc_account_type_details')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_account_type_details` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `account_type_id` INT(11) NOT NULL,
      `name` VARCHAR(255) NOT NULL,
      `note` TEXT NULL,
      `statement_of_cash_flows` VARCHAR(255) NULL,
      PRIMARY KEY (`id`)
    ) AUTO_INCREMENT=200, ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('preferred_payment_method' ,db_prefix() . 'acc_expense_category_mappings')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_expense_category_mappings`
    ADD COLUMN `preferred_payment_method` INT(11) NOT NULL DEFAULT \'0\';');
}

if (!$CI->db->field_exists('expense_payment_account' ,db_prefix() . 'acc_payment_mode_mappings')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_payment_mode_mappings`
    ADD COLUMN `expense_payment_account` INT(11) NOT NULL DEFAULT \'0\',
    ADD COLUMN `expense_deposit_to` INT(11) NOT NULL DEFAULT \'0\';');
}

if (get_option('acc_expense_deposit_to') == 37){
  update_option('acc_expense_deposit_to', 80);
}

if (!$CI->db->field_exists('payslip_type' ,db_prefix() . 'acc_account_history')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_account_history`
    ADD COLUMN `payslip_type` VARCHAR(45) NULL;');
}

if (!acc_account_exists('acc_opening_balance_equity')) {
  $CI->db->query("INSERT INTO `". db_prefix() ."acc_accounts` (`name`, `key_name`, `account_type_id`, `account_detail_type_id`, `default_account`, `active`) VALUES ('', 'acc_opening_balance_equity', '10', '71', '1', '1');");
}

add_option('acc_pl_total_insurance_automatic_conversion', 1);
add_option('acc_pl_total_insurance_payment_account', 13);
add_option('acc_pl_total_insurance_deposit_to', 32);

add_option('acc_pl_tax_paye_automatic_conversion', 1);
add_option('acc_pl_tax_paye_payment_account', 13);
add_option('acc_pl_tax_paye_deposit_to', 28);

add_option('acc_pl_net_pay_automatic_conversion', 1);
add_option('acc_pl_net_pay_payment_account', 13);
add_option('acc_pl_net_pay_deposit_to', 56);

add_option('acc_wh_stock_import_automatic_conversion', 1);
add_option('acc_wh_stock_import_payment_account', 87);
add_option('acc_wh_stock_import_deposit_to', 37);

add_option('acc_wh_stock_export_automatic_conversion', 1);
add_option('acc_wh_stock_export_payment_account', 37);
add_option('acc_wh_stock_export_deposit_to', 1);

add_option('acc_wh_loss_adjustment_automatic_conversion', 1);
add_option('acc_wh_decrease_payment_account', 37);
add_option('acc_wh_decrease_deposit_to', 1);

add_option('acc_wh_increase_payment_account', 87);
add_option('acc_wh_increase_deposit_to', 37);

add_option('acc_wh_opening_stock_automatic_conversion', 1);

if (acc_account_exists('acc_opening_balance_equity')) {
    add_option('acc_wh_opening_stock_payment_account', acc_account_exists('acc_opening_balance_equity'));
}
add_option('acc_wh_opening_stock_deposit_to', 37);

add_option('acc_pur_order_automatic_conversion', 1);
add_option('acc_pur_order_payment_account', 13);
add_option('acc_pur_order_deposit_to', 80);

add_option('acc_pur_payment_automatic_conversion', 1);
add_option('acc_pur_payment_payment_account', 16);
add_option('acc_pur_payment_deposit_to', 37);

//Version 1.0.8

if (!$CI->db->table_exists(db_prefix() . 'acc_budgets')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_budgets` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `year` INT(11) NOT NULL,
      `name` VARCHAR(200) NULL,
      `type` VARCHAR(45) NULL,
      `data_source` VARCHAR(45) NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_budget_details')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_budget_details` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `budget_id` INT(11) NOT NULL,
      `month` INT(11) NOT NULL,
      `year` INT(11) NOT NULL,
      `account` INT(11) NULL,
      `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('vendor' ,db_prefix() . 'acc_account_history')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_account_history`
    ADD COLUMN `vendor` INT(11) NULL;');
}

if (!$CI->db->field_exists('itemable_id' ,db_prefix() . 'acc_account_history')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_account_history`
    ADD COLUMN `itemable_id` INT(11) NULL;');
}


//-------------------------

if (!$CI->db->field_exists('cleared' ,db_prefix() . 'acc_account_history')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_account_history`
    ADD COLUMN `cleared` INT(11) NOT NULL DEFAULT 0;');
}

if (!$CI->db->field_exists('access_token' ,db_prefix() . 'acc_accounts')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_accounts`
    ADD COLUMN `access_token` TEXT NULL,
    ADD COLUMN `account_id` VARCHAR(255) NULL,
    ADD COLUMN `plaid_status` TINYINT(5) NOT NULL DEFAULT 0 COMMENT "1=>verified, 0=>not verified",
    ADD COLUMN `plaid_account_name` VARCHAR(255) NULL;');
}

if (!$CI->db->field_exists('transaction_id' ,db_prefix() . 'acc_transaction_bankings')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_transaction_bankings`
    ADD COLUMN `transaction_id` varchar(150) NULL,
    ADD COLUMN `bank_id` INT(11) NULL,
    ADD COLUMN `status` TINYINT(5) NOT NULL DEFAULT 0 COMMENT "1=>posted, 2=>pending";');
}

if (!$CI->db->field_exists('matched' ,db_prefix() . 'acc_transaction_bankings')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_transaction_bankings`
    ADD COLUMN `matched` INT(11) NOT NULL DEFAULT 0;');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_plaid_transaction_logs')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "acc_plaid_transaction_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `bank_id` int(11) DEFAULT NULL,
        `last_updated` date DEFAULT NULL,
        `transaction_count` int(11) DEFAULT NULL,
        `created_at` datetime DEFAULT NULL,
        `addedFrom` int(11) DEFAULT NULL,
        `company` int(11) DEFAULT NULL,
        `status` int(11) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('opening_balance' ,db_prefix() . 'acc_reconciles')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_reconciles`
    ADD COLUMN `opening_balance` INT(11) NOT NULL DEFAULT 0;');
}


if (!$CI->db->field_exists('debits_for_period' ,db_prefix() . 'acc_reconciles')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_reconciles`
  ADD COLUMN `debits_for_period` DECIMAL(15,2) NULL');
}

if (!$CI->db->field_exists('credits_for_period' ,db_prefix() . 'acc_reconciles')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_reconciles`
  ADD COLUMN `credits_for_period`  DECIMAL(15,2) NULL');
}

if (!$CI->db->field_exists('dateadded' ,db_prefix() . 'acc_reconciles')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_reconciles`
    ADD COLUMN `dateadded` DATETIME NULL,
    ADD COLUMN `addedfrom` INT(11) NULL
    ');
}

if (!$CI->db->field_exists('reconcile' ,db_prefix() . 'acc_transaction_bankings')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_transaction_bankings`
    ADD COLUMN `reconcile` INT(11) NOT NULL DEFAULT 0;');
}

if (!$CI->db->field_exists('adjusted' ,db_prefix() . 'acc_transaction_bankings')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_transaction_bankings`
    ADD COLUMN `adjusted` INT(11) NOT NULL DEFAULT 0;');
}

if (!$CI->db->table_exists(db_prefix() . 'acc_matched_transactions')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'acc_matched_transactions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `account_history_id` INT(11) NULL,
        `history_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `rel_id` INT(11) NULL,
        `rel_type` VARCHAR(255) NULL,
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `company` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('reconcile' ,db_prefix() . 'acc_matched_transactions')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'acc_matched_transactions`
    ADD COLUMN `reconcile` INT(11) NOT NULL DEFAULT 0;');
}