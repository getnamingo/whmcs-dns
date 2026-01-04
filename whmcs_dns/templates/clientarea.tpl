{* modules/addons/whmcs_dns/templates/clientarea.tpl *}

{if $message}
    <div class="alert alert-{if $message.type=='success'}success{else}danger{/if} mb-3" role="alert" style="position:relative;padding-right:3rem;">
        {$message.text|escape}

        <button type="button"
                aria-label="Close"
                onclick="this.closest('.alert').remove();"
                style="position:absolute;top:.5rem;right:.75rem;border:0;background:transparent;font-size:1.25rem;line-height:1;cursor:pointer;">
            &times;
        </button>
    </div>
{/if}

<div class="d-flex align-items-center mb-3">
    <div class="col">
        <h2 class="mb-0">DNS Manager</h2>
    </div>

    <div class="col-auto ms-auto">
        {if $zone}
            <form method="post"
                  action="index.php?m=whmcs_dns&domain={$selectedDomain|escape:'url'}"
                  onsubmit="return confirm('Disable DNS and delete the zone for this domain?');"
                  class="m-0">
                <input type="hidden" name="action" value="disable_dns" />
                <input type="hidden" name="domain_name" value="{$selectedDomain|escape}" />
                <button type="submit" class="btn btn-outline-danger">
                    Disable DNS
                </button>
            </form>
        {else}
            <form method="post"
                  action="index.php?m=whmcs_dns&domain={$selectedDomain|escape:'url'}"
                  class="m-0">
                <input type="hidden" name="action" value="enable_dns" />
                <input type="hidden" name="domain_name" value="{$selectedDomain|escape}" />
                <button type="submit" class="btn btn-outline-primary">
                    Enable DNS
                </button>
            </form>
        {/if}
    </div>
</div>

{if !$zone}
    <div class="alert alert-info mb-3">
        DNS is not enabled for this domain yet. Click <b>Enable DNS</b> to create the zone.
    </div>
{/if}

{if $zone}
<div class="card mb-4 mt-4">
    <div class="card-header">
        <h2 class="h4 mb-0">
            DNS Hosting for {$zone.domain_name|escape}
        </h2>
    </div>
    <div class="card-body">
        <h3 class="h5 mb-3">DNS Record Management</h3>

        <form class="mb-4" method="post"
              action="index.php?m=whmcs_dns&domain={$selectedDomain|escape:'url'}">
            <input type="hidden" name="action" value="add_record" />
            <input type="hidden" name="domain_name" value="{$zone.domain_name|escape}" />

            <div class="row g-3">
                <div class="col-md-2">
                    <select class="form-control" name="record_type" required>
                        <option value="" disabled selected>Select Type</option>
                        <option value="A">A</option>
                        <option value="AAAA">AAAA</option>
                        <option value="CNAME">CNAME</option>
                        <option value="MX">MX</option>
                        <option value="TXT">TXT</option>
                        <option value="SPF">SPF</option>
                        <option value="DS">DS</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="text" class="form-control" placeholder="example." name="record_name" required>
                </div>

                <div class="col-md-3">
                    <input type="text" class="form-control" placeholder="127.0.0.1" name="record_value" required>
                </div>

                <div class="col-sm">
                    <input type="number" class="form-control" placeholder="TTL" name="record_ttl" value="600" required>
                </div>

                <div class="col-sm">
                    <input type="number" class="form-control" placeholder="Priority" name="record_priority">
                </div>

                <div class="col-md-auto d-flex align-items-end">
                    <button class="btn btn-outline-primary" type="submit" title="Add Record" style="display:flex;gap:6px;align-items:center;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M12 5l0 14" />
                            <path d="M5 12l14 0" />
                        </svg>
                        Add
                    </button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-bordered text-nowrap">
                <thead>
                    <tr>
                        <th scope="col" style="width:110px;">Type</th>
                        <th scope="col" style="width:220px;">Name</th>
                        <th scope="col">Value</th>
                        <th scope="col" style="width:110px;">TTL</th>
                        <th scope="col" style="width:110px;">Priority</th>
                        <th scope="col" style="width:170px;">Actions</th>
                    </tr>
                </thead>

                <tbody id="recordsTableBody">
                {if !$records}
                    <tr>
                        <td colspan="6">No DNS records found.</td>
                    </tr>
                {else}
                    {foreach $records as $r}
                        <tr>
                            <td>
                                {assign var="dns_type" value=$r.type|upper}
                                {if $dns_type == 'A'}
                                    {assign var="badgeBg" value="#e7f1ff"}{assign var="badgeFg" value="#0b5ed7"}
                                {elseif $dns_type == 'AAAA'}
                                    {assign var="badgeBg" value="#efe7ff"}{assign var="badgeFg" value="#5f3dc4"}
                                {elseif $dns_type == 'CNAME'}
                                    {assign var="badgeBg" value="#e6fff2"}{assign var="badgeFg" value="#198754"}
                                {elseif $dns_type == 'MX'}
                                    {assign var="badgeBg" value="#fff4e6"}{assign var="badgeFg" value="#fd7e14"}
                                {elseif $dns_type == 'TXT'}
                                    {assign var="badgeBg" value="#e6fffb"}{assign var="badgeFg" value="#0f766e"}
                                {elseif $dns_type == 'SPF'}
                                    {assign var="badgeBg" value="#ffe6e6"}{assign var="badgeFg" value="#dc3545"}
                                {elseif $dns_type == 'DS'}
                                    {assign var="badgeBg" value="#f3e8ff"}{assign var="badgeFg" value="#6f42c1"}
                                {else}
                                    {assign var="badgeBg" value="#f1f3f5"}{assign var="badgeFg" value="#495057"}
                                {/if}

                                <span style="display:inline-block;padding:.25rem .5rem;border-radius:999px;background:{$badgeBg};color:{$badgeFg};font-weight:600;">
                                    {$dns_type|escape}
                                </span>
                            </td>

                            <td><strong>{$r.host|escape}</strong></td>

                            <td style="min-width:240px;">
                                <form class="whmcsdns-update-form m-0" method="post"
                                      action="index.php?m=whmcs_dns&domain={$selectedDomain|escape:'url'}">
                                    <input type="hidden" name="action" value="update_record" />
                                    <input type="hidden" name="domain_name" value="{$zone.domain_name|escape}" />
                                    <input type="hidden" name="row_id" value="{$r.id}" />
                                    <input type="hidden" name="old_value" value="{$r.value|escape}" />
                                    <input type="hidden" name="record_type" value="{$r.type|escape}" />
                                    <input type="hidden" name="record_name" value="{$r.host|escape}" />

                                    <input type="text" class="form-control" placeholder="127.0.0.1"
                                           name="record_value" value="{$r.value|escape}" required />
                            </td>

                            <td>
                                    <input type="number" class="form-control" placeholder="600"
                                           name="record_ttl" value="{if $r.ttl}{$r.ttl}{else}600{/if}" required />
                            </td>

                            <td>
                                    {if $dns_type == 'MX'}
                                        <input type="number" class="form-control" placeholder="Priority"
                                               name="record_priority" value="{$r.priority|escape}" />
                                    {else}
                                        <input type="number" class="form-control" placeholder="-"
                                               name="record_priority" value="" />
                                    {/if}
                            </td>

                            <td class="text-end" style="white-space:nowrap;">
                                    <button type="submit" class="btn btn-outline-primary btn-sm" title="Update Record" style="display:inline-flex;align-items:center;gap:6px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                                            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                                            <path d="M16 5l3 3" />
                                        </svg>
                                        Update
                                    </button>
                                </form>

                                <form class="whmcsdns-delete-form d-inline" method="post"
                                      action="index.php?m=whmcs_dns&domain={$selectedDomain|escape:'url'}">
                                    <input type="hidden" name="action" value="delete_record" />
                                    <input type="hidden" name="domain_name" value="{$zone.domain_name|escape}" />
                                    <input type="hidden" name="row_id" value="{$r.id}" />

                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                            title="Delete Record" onclick="whmcsDnsConfirmDelete(this);"
                                            style="display:inline-flex;align-items:center;gap:6px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                            <path d="M10 10l4 4m0 -4l-4 4" />
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    {/foreach}
                {/if}
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function whmcsDnsConfirmDelete(btn) {
    var form = btn.closest('form');
    if (!form) return;

    if (confirm("Are you sure you want to delete this record?")) {
        form.submit();
    }
}
</script>
{/if}