<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div id="customers-report" class="hide">
<div class="row">
      <div class="col-md-4">
         <div class="form-group">
            <label for="customer_filter">Customer</label>
            <select name="customer_filter" class="selectpicker"  data-width="100%" data-none-selected-text="">
              
               <option value=""> All </option>
               <option value="1">Paid</option>
               <option value="2">Pending</option>
            
         </select>
      </div>
   </div></div>
  <?php render_datatable(array(
   _l('reports_sales_dt_customers_client'),
   _l('reports_sales_dt_customers_total_invoices'),
   _l('reports_sales_dt_items_customers_amount'),
   _l('reports_sales_dt_items_customers_amount_with_tax'),
   ),'customers-report'); ?>
</div>
