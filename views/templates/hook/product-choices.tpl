<fieldset class="tpp-choices">
    <legend class="form-label tpp-choices__legend">
        {l s='Control side' d='Modules.Tailoredproducts.Shop'}
    </legend>

    <div class="btn-group tpp-choices__group" role="group">
        <input type="radio" class="btn-check" name="tpp_cord_side"
               id="tpp_cord_side_left_{$idProduct|intval}" value="left" autocomplete="off">
        <label class="btn btn-outline-primary" for="tpp_cord_side_left_{$idProduct|intval}">
            {l s='Left' d='Modules.Tailoredproducts.Shop'}
        </label>

        <input type="radio" class="btn-check" name="tpp_cord_side"
               id="tpp_cord_side_right_{$idProduct|intval}" value="right" autocomplete="off" checked>
        <label class="btn btn-outline-primary" for="tpp_cord_side_right_{$idProduct|intval}">
            {l s='Right' d='Modules.Tailoredproducts.Shop'}
        </label>
    </div>
</fieldset>

{if $cassetteEnabled}
    <fieldset class="tpp-choices">
        <legend class="form-label tpp-choices__legend">
            {l s='Cassette' d='Modules.Tailoredproducts.Shop'}
        </legend>

        <div class="btn-group tpp-choices__group" role="group">
            <input type="radio" class="btn-check" name="tpp_cassette"
                   id="tpp_cassette_without_{$idProduct|intval}" value="without" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="tpp_cassette_without_{$idProduct|intval}">
                {l s='Without cassette' d='Modules.Tailoredproducts.Shop'}
            </label>

            <input type="radio" class="btn-check" name="tpp_cassette"
                   id="tpp_cassette_with_{$idProduct|intval}" value="with" autocomplete="off">
            <label class="btn btn-outline-primary" for="tpp_cassette_with_{$idProduct|intval}">
                {l s='With cassette' d='Modules.Tailoredproducts.Shop'}
            </label>
        </div>
    </fieldset>
{/if}

{if $mechanismColorOptions|@count}
    <fieldset class="tpp-choices">
        <legend class="form-label tpp-choices__legend">
            {l s='Mechanism & cord color' d='Modules.Tailoredproducts.Shop'}
        </legend>

        <div class="tpp-choices__group tpp-swatches" role="group">
            {foreach from=$mechanismColorOptions item=option name=colors}
                <input type="radio" class="tpp-swatch-input" name="tpp_mechanism_color"
                       id="tpp_mechanism_color_{$option.id|intval}_{$idProduct|intval}"
                       value="{$option.id|intval}" autocomplete="off"
                       {if $smarty.foreach.colors.first} checked{/if}>
                <label class="tpp-swatch{if !$option.color} tpp-swatch--unknown{/if}"
                       for="tpp_mechanism_color_{$option.id|intval}_{$idProduct|intval}"
                       title="{$option.name|escape:'htmlall':'UTF-8'}"
                       {if $option.color} style="--tpp-swatch-color: {$option.color|escape:'htmlall':'UTF-8'};"{/if}>
                    <span class="visually-hidden">{$option.name|escape:'htmlall':'UTF-8'}</span>
                </label>
            {/foreach}
        </div>
    </fieldset>
{/if}

{if $rollDirectionEnabled}
    <fieldset class="tpp-choices">
        <legend class="form-label tpp-choices__legend">
            {l s='Roll direction' d='Modules.Tailoredproducts.Shop'}
        </legend>

        <div class="btn-group tpp-choices__group" role="group">
            <input type="radio" class="btn-check" name="tpp_roll_direction"
                   id="tpp_roll_direction_standard_{$idProduct|intval}" value="standard" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="tpp_roll_direction_standard_{$idProduct|intval}">
                {l s='Standard roll' d='Modules.Tailoredproducts.Shop'}
            </label>

            <input type="radio" class="btn-check" name="tpp_roll_direction"
                   id="tpp_roll_direction_reverse_{$idProduct|intval}" value="reverse" autocomplete="off">
            <label class="btn btn-outline-primary" for="tpp_roll_direction_reverse_{$idProduct|intval}">
                {l s='Reverse roll' d='Modules.Tailoredproducts.Shop'}
            </label>
        </div>
    </fieldset>
{/if}

{if $mountTypeEnabled}
    <fieldset class="tpp-choices">
        <legend class="form-label tpp-choices__legend">
            {l s='Mount type' d='Modules.Tailoredproducts.Shop'}
        </legend>

        <div class="btn-group tpp-choices__group" role="group">
            <input type="radio" class="btn-check" name="tpp_mount_type"
                   id="tpp_mount_type_wall_{$idProduct|intval}" value="wall" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="tpp_mount_type_wall_{$idProduct|intval}">
                {l s='Wall mount' d='Modules.Tailoredproducts.Shop'}
            </label>

            <input type="radio" class="btn-check" name="tpp_mount_type"
                   id="tpp_mount_type_ceiling_{$idProduct|intval}" value="ceiling" autocomplete="off">
            <label class="btn btn-outline-primary" for="tpp_mount_type_ceiling_{$idProduct|intval}">
                {l s='Ceiling mount' d='Modules.Tailoredproducts.Shop'}
            </label>
        </div>
    </fieldset>
{/if}
