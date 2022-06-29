<div id="importParticipant" class="crm-block crm-form-block crm-contribution-move-form-block">
  <div class="messages status">
    <div class="icon inform-icon"></div> {ts 1=$current_event}Create participant for the Event: %1.{/ts}
  </div>

  {foreach from=$elementNames item=elementName}
    <div class="crm-section">
      <div class="label center">{$form.$elementName.label}</div>
      <div class="content">{$form.$elementName.html}</div>
      <div class="clear"></div>
    </div>
  {/foreach}
</div>

<br><br>
  <div id="show_contact_details" class="crm-section" style="display: hidden">
    <table class="report-layout compact display" border="2">
      <tr role="row">
        <th>Contact ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Memberships</th>
        <th>Contributions</th>
        <th>Event Registrations</th>
        <th></th>
      </tr>
      <tbody>
      <tr role="row">
        <td id="selected_contact_id"></td>
        <td id="display_name_full"></td>
        <td id="email_id"></td>
        <td id="no_of_memberhips"></td>
        <td id="no_of_contributions"></td>
        <td id="no_of_event_registrations"></td>
        <td><a id="view_contact">View</a></td>
      </tr>
      </tbody>
    </table>
  </div>
  <div id="already_registered_msg_ctr" class="messages status"><span class="warning">This contact has been already registered for this event.</span></div>
<div>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>

{literal}
<script>

CRM.$(function($) {

  $('#show_contact_details').on( 'click', 'button', function (e) {
    e.preventDefault();
  });

  showContactDetails();
  $("#change_contact_id").on("change", function(){
    showContactDetails();
  });

  function emptyContactDetailsTable(){
    $("#selected_contact_id").text("");
    $("#display_name_full").text("");
    $("#email_id").text("");
    $("#no_of_memberhips").text("");
    $("#no_of_contributions").text("");
    $("#no_of_event_registrations").text("");
    $("#view_contact").removeAttr('href');
    $("#show_contact_details").hide();
    $("#already_registered_msg_ctr").hide();
    $('button[data-identifier="_qf_ImportParticipant_submit"]').show();
  }

  async function showContactDetails(){
    await emptyContactDetailsTable();
    var eId = '{/literal}{$event_id}{literal}';
    var cId = $("#change_contact_id").val();

    $("#selected_contact_id").text(cId);
    if(cId && cId != ''){
      var contactUrl = CRM.url('civicrm/contact/view', {reset: 1, cid: cId});
      var dataUrl = {/literal}"{crmURL p='civicrm/ajax/rest' h=0 q='className=CRM_NcnCiviZoom_Page_AJAX&fnName=getContactDetails'}"{literal}
      dataUrl += '&id='+cId + '&event_id='+eId;
      $.ajax({
        url: dataUrl,
        async: false,
        success: function(data) {
          $("#view_contact").attr("href", data.data.contactUrl);
          $("#view_contact").attr("target", "_blank");
          $("#display_name_full").text(data.data.display_name);
          $("#email_id").text(data.data.email);
          $("#no_of_memberhips").text(data.data.memerbships);
          $("#no_of_contributions").text(data.data.contributions);
          $("#no_of_event_registrations").text(data.data.event_registrations);
          if(data.data.already_registered){
            $("#already_registered_msg_ctr").show();
            $('button[data-identifier="_qf_ImportParticipant_submit"]').hide();
          }

        }
      });
      $('#show_contact_details').find('td').css('text-align', 'center');
      $('#show_contact_details').find('th').css('text-align', 'center');
      $("#show_contact_details").show();
    }
  }

});

</script>
{/literal}


{literal}
<style type="text/css">

.center {
  padding: 5px;
  min-width: 17%;
}

</style>
{/literal}