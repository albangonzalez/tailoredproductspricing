<div
    id="tpp-dimensions-form"
    class="tpp-dimensions-form"
    data-ajax-url="{$ajaxUrl|escape:'html'}"
>
    <fieldset class="tpp-dimensions">
        <div class="tpp-dimensions-label">
            <label class="form-label">
                {l s='Dimensions' d='Modules.Tailoredproducts.Shop'}
            </label>
        </div>
        <div class="form-group tpp-dimension-field">
            <label for="tpp_width">
                {l s='Width' d='Modules.Tailoredproducts.Shop'} ({$unit|escape:'html'})
            </label>
            <input
                type="number"
                id="tpp_width"
                name="tpp_width"
                class="form-control"
                step="{$step|escape:'html'}"
                min="{$minWidth|floatval}"
                max="{$maxWidth|floatval}"
            >
        </div>
        <div class="form-group tpp-dimension-field">
            <label for="tpp_height">
                {l s='Height' d='Modules.Tailoredproducts.Shop'} ({$unit|escape:'html'})
            </label>
            <input
                type="number"
                id="tpp_height"
                name="tpp_height"
                class="form-control"
                step="{$step|escape:'html'}"
                min="{$minHeight|floatval}"
                max="{$maxHeight|floatval}"
            >
        </div>
    </fieldset>
</div>
