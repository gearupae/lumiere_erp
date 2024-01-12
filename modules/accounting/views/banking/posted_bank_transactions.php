<div class="row">
    <div class="col-md-3">
      <?php echo render_select('bank_account',$bank_accounts,array('id','name', 'account_type_name'),'acc_bank_account'); ?>
    </div>
    <div class="col-md-3">
      <?php $status = [ 
            1 => ['id' => 'converted', 'name' => _l('cleared')],
            2 => ['id' => 'has_not_been_converted', 'name' => _l('uncleared')],
          ]; 
          ?>
          <?php echo render_select('status',$status,array('id','name'),'status', $_status, array('multiple' => true, 'data-actions-box' => true), array(), '', '', false); ?>
    </div>
    <div class="col-md-3">
      <?php echo render_date_input('from_date','from_date'); ?>
    </div>
    <div class="col-md-3">
      <?php echo render_date_input('to_date','to_date'); ?>
    </div>
  </div>

  <hr>
  <a href="<?php echo admin_url('accounting/import_xlsx_posted_bank_transactions'); ?>" class="btn btn-success mr-4 button-margin-r-b" title="<?php echo _l('import_excel') ?> ">
    <?php echo _l('import_excel'); ?>
  </a>
  <a href="<?php echo admin_url('accounting/plaid_bank_new_transactions'); ?>" id="set_up_your_bank_account" class="btn btn-info"><?php echo _l('set_up_your_bank_account'); ?></a>
  <a href="#" id="update_bank_transactions" class="btn btn-info" disabled><?php echo _l('update_bank_transactions'); ?></a>

  <div class="mbot25 text-center"><h4><?php echo _l('posted_transactions_from_your_bank_account'); ?></h4></div>
  <table class="table table-banking">
    <thead>
      <th><?php echo _l('invoice_payments_table_date_heading'); ?></th>
      <!-- <th><?php echo _l('check_#'); ?></th> -->
      <th><?php echo _l('payee'); ?></th>
      <th><?php echo _l('description'); ?></th>
      <th><?php echo _l('withdrawals'); ?></th>
      <th><?php echo _l('deposits'); ?></th>
      <!-- <th><?php echo _l('balance'); ?></th> -->
      <th><?php echo _l('cleared'); ?></th>
    </thead>
    <tbody>
      
    </tbody>
  </table>
