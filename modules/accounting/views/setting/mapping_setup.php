<div class="horizontal-scrollable-tabs">
   <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
   <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
   <div class="horizontal-tabs">
      <ul class="nav nav-tabs profile-tabs row customer-profile-tabs nav-tabs-horizontal" role="tablist">
         <li role="presentation" class="<?php if($tab_2 == 'general_mapping_setup'){echo 'active';}; ?>">
            <a href="<?php echo admin_url('accounting/setting?group=mapping_setup&tab=general_mapping_setup'); ?>">
              <i class="fa fa-th"></i>&nbsp;<?php echo _l('general'); ?>
            </a>
         </li>
         <?php 
            if(acc_get_status_modules('hr_payroll')){ ?>
               <li role="presentation" class="<?php if($tab_2 == 'payslip'){echo 'active';}; ?>">
                  <a href="<?php echo admin_url('accounting/setting?group=mapping_setup&tab=payslip'); ?>">
                    <i class="fa fa-money"></i>&nbsp;<?php echo _l('payslips'); ?>
                  </a>
               </li>
         <?php } ?>
         <?php if(acc_get_status_modules('purchase')){ ?>
               <li role="presentation" class="<?php if($tab_2 == 'purchase'){echo 'active';}; ?>">
                  <a href="<?php echo admin_url('accounting/setting?group=mapping_setup&tab=purchase'); ?>">
                    <i class="fa fa-shopping-cart"></i>&nbsp;<?php echo _l('purchase'); ?>
                  </a>
               </li>
         <?php } ?>

         <?php if(acc_get_status_modules('warehouse')){ ?>
               <li role="presentation" class="<?php if($tab_2 == 'warehouse'){echo 'active';}; ?>">
                  <a href="<?php echo admin_url('accounting/setting?group=mapping_setup&tab=warehouse'); ?>">
                    <i class="fa fa-snowflake-o"></i>&nbsp;<?php echo _l('warehouse'); ?>
                  </a>
               </li>
         <?php } ?>
         
         
      </ul>
   </div>
  <?php $this->load->view($tab_2,array('bulk_actions'=>true)); ?>
</div>