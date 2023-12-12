{crmScope extensionKey="ncn-civi-zoom"}
<div class="crm-block crm-form-block">

{if $deleteAction}
  <div><h2>Delete {$zoomName} zoom account settings?</h2></div>
{/if}

{if ($act eq 1) || ($act eq 2)}
  <h2>{ts 1=$zoomName}Zoom Account: %1{/ts}</h2>
  <table class="form-layout-compressed">
    <tbody>
      <tr class="custom_field-row">
        <td class="label">{$form.name.label}</td>
        <td class="">{$form.name.html}</td>
      </tr>
      <tr class="custom_field-row">
        <td class="label">{$form.user_id.label}</td>
        <td class="">{$form.user_id.html}
          <div class="description">Please enter valid account user email if you know, otherwise leave it blank. <br /> If user id presents, then additional validation check that given Meeting/Webinar id is belong to given user account.</div>
        </td>
      </tr>
      <tr class="custom_field-row">
        <td class="label">{$form.oauth_client_id.label}</td>
        <td>{$form.oauth_client_id.html}
          <div class="description">{ts}The OAuth Clients are managed on the CiviCRM OAuth Client Settings screen. Register the client there, then select it here. This is admittedly odd, but we wanted to avoid duplicate interfaces for managing credentials.{/ts}</div>
        </td>
      </tr>
      <tr class="custom_field-row">
        <td class="label">{$form.account_id.label}</td>
        <td>{$form.account_id.html}
          <div class="description">{ts}The Zoom Account ID is visible on the App Credentials section. Go to: https://marketplace.zoom.us/user/build then click on the app you created.{/ts}</div>
        </td>
      </tr>
    </tbody>
  </table>
{else}
  <br>
  <br>
  {if !$id}
    {if !$deleteAction}
      <div class="crm-submit-buttons">
         <a class="button crm-button" href="{crmURL p='civicrm/admin/zoomaccounts' q='act=1&reset=1'}" id="newAccount"><i class="crm-i fa-plus-circle"></i> {ts}Add New zoom account{/ts}</a>
      </div>
      <h1>List of configured zoom accounts</h1>
    {/if}

    <div>
      <table class="selector row-highlight" id="settings_table">
        <thead>
          <tr>
            {foreach from=$headers item=header}
              <th>{$header}</th>
            {/foreach}
          </tr>
        </thead>
        <tbody>
            {foreach from=$rows item=row}
              <tr>
                {foreach from=$columnNames item=columnName}
                  <td>{$row.$columnName}</td>
                {/foreach}
              </tr>
            {/foreach}
        </tbody>
      </table>
    </div>
  {/if}
{/if}

{if !$act && !$id}
  </br></br><h2>Common zoom account settings</h2>
  <div class="crm-section">
    <div class="label">{$form.custom_field_id_webinar.label}</div>
    <div class="content">{$form.custom_field_id_webinar.html}
      <span class="description">
      {ts}Select the event custom field which holds the Zoom Webinar ID{/ts}
      </span>
    </div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.custom_field_id_meeting.label}</div>
    <div class="content">{$form.custom_field_id_meeting.html}
      <span class="description">
      </br>
      {ts}Select the event custom field which holds the Zoom Meeting ID{/ts}
      </span>
    </div>
    </br>
    <div class="label">{$form.custom_field_account_id.label}</div>
    <div class="content">{$form.custom_field_account_id.html}
      <span class="description">
      </br>
      {ts}Select the event custom field which holds the Zoom Account ID{/ts}
      </span>
    </div>
    </br>
    <div class="label">{$form.import_email_location_type.label}</div>
    <div class="content">{$form.import_email_location_type.html}
      <span class="description">
      </br>
      {ts}Email location type to be used while importing the zoom registrant{/ts}
      </span>
    </div>
    <div class="clear"></div>
  </div>
{/if}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
</div>

{/crmScope}
