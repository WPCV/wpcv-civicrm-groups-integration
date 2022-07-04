{* template block that contains the new field *}
<table>
  <tr class="wpcv_cgi_edit_block">
    <td class="label"><label for="wpcv_cgi_edit">{$wpcv_cgi_edit_label}</label></td>
    <td><span class="description">{$wpcv_cgi_edit_description}</span></td>
  </tr>
</table>

{* reposition the above block after #someOtherBlock *}
<script type="text/javascript">
  // jQuery will not move an item unless it is wrapped.
  cj('tr.wpcv_cgi_edit_block').insertBefore('.crm-group-form-block .crm-group-form-block-group_type');
</script>
