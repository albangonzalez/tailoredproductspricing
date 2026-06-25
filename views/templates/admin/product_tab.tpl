<div class="panel" id="tpp-panel">
  <div class="panel-heading">
    <i class="icon-resize-full"></i>
    {l s='Tailored Products Pricing' mod='tailoredproductspricing'}
  </div>

  <div class="form-group">
    <div class="col-lg-12">
      <div class="checkbox">
        <label>
          <input type="checkbox"
                 id="tpp_enabled"
                 name="tpp_enabled"
                 value="1"
                 {if $tpp_config}checked="checked"{/if} />
          {l s='Enable price-per-square-meter for this product' mod='tailoredproductspricing'}
        </label>
      </div>
    </div>
  </div>

  <div id="tpp_fields" {if !$tpp_config}style="display:none;"{/if}>

    <p class="text-muted">
      {l s='The price per m² is set per combination, in the Combinations tab.' mod='tailoredproductspricing'}
    </p>

    <div class="form-group">
      <label class="control-label col-lg-3">
        {l s='Dimension unit' mod='tailoredproductspricing'}
      </label>
      <div class="col-lg-4">
        <select name="tpp_unit" class="form-control">
          <option value="cm" {if !$tpp_config || $tpp_config.unit === 'cm'}selected="selected"{/if}>cm</option>
          <option value="mm" {if $tpp_config && $tpp_config.unit === 'mm'}selected="selected"{/if}>mm</option>
        </select>
      </div>
    </div>

    <hr />
    <p class="text-muted">{l s='Leave min/max blank to impose no constraint.' mod='tailoredproductspricing'}</p>

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Min width' mod='tailoredproductspricing'}</label>
      <div class="col-lg-3">
        <input type="number" name="tpp_min_width" class="form-control" step="0.01" min="0"
               value="{if $tpp_config && $tpp_config.min_width !== null}{$tpp_config.min_width|floatval}{/if}" />
      </div>
      <label class="control-label col-lg-1">{l s='Max width' mod='tailoredproductspricing'}</label>
      <div class="col-lg-3">
        <input type="number" name="tpp_max_width" class="form-control" step="0.01" min="0"
               value="{if $tpp_config && $tpp_config.max_width !== null}{$tpp_config.max_width|floatval}{/if}" />
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Min height' mod='tailoredproductspricing'}</label>
      <div class="col-lg-3">
        <input type="number" name="tpp_min_height" class="form-control" step="0.01" min="0"
               value="{if $tpp_config && $tpp_config.min_height !== null}{$tpp_config.min_height|floatval}{/if}" />
      </div>
      <label class="control-label col-lg-1">{l s='Max height' mod='tailoredproductspricing'}</label>
      <div class="col-lg-3">
        <input type="number" name="tpp_max_height" class="form-control" step="0.01" min="0"
               value="{if $tpp_config && $tpp_config.max_height !== null}{$tpp_config.max_height|floatval}{/if}" />
      </div>
    </div>

  </div>{* end #tpp_fields *}
</div>

<script>
(function () {
  var checkbox = document.getElementById('tpp_enabled');
  var fields   = document.getElementById('tpp_fields');
  if (!checkbox || !fields) { return; }
  checkbox.addEventListener('change', function () {
    fields.style.display = this.checked ? '' : 'none';
  });
}());
</script>
