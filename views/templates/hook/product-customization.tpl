{*
 * Module-owned front-office partial rendered via Module::display() from
 * hookDisplayProductCustomization. Display-only: two number inputs (width,
 * height), no <form>, no submit control, no JS/CSS this iteration.
 *}
<div id="tpp-dimensions-form" class="tpp-dimensions-form">
    <fieldset class="tpp-dimensions">
        <div class="tpp-dimensions-label">
            <label class="form-label">
                {l s='Dimensions' d='Modules.Tailoredproductspricing.Shop'}
            </label>
        </div>
        <div class="form-group tpp-dimension-field">
            <label for="tpp_width">
                {l s='Width' d='Modules.Tailoredproductspricing.Shop'} ({$unit|escape:'html'})
            </label>
            <input
                type="number"
                id="tpp_width"
                name="tpp_width"
                class="form-control"
                step="{$step|escape:'html'}"
                {if $minWidth !== null && $minWidth !== ''} min="{$minWidth|floatval}"{/if}
                {if $maxWidth !== null && $maxWidth !== ''} max="{$maxWidth|floatval}"{/if}
            >
        </div>
        <div class="form-group tpp-dimension-field">
            <label for="tpp_height">
                {l s='Height' d='Modules.Tailoredproductspricing.Shop'} ({$unit|escape:'html'})
            </label>
            <input
                type="number"
                id="tpp_height"
                name="tpp_height"
                class="form-control"
                step="{$step|escape:'html'}"
                {if $minHeight !== null && $minHeight !== ''} min="{$minHeight|floatval}"{/if}
                {if $maxHeight !== null && $maxHeight !== ''} max="{$maxHeight|floatval}"{/if}
            >
        </div>    
    </fieldset>

</div>
