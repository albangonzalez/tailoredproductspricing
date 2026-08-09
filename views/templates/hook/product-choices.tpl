<fieldset class="tpp-choices">
    <legend class="form-label tpp-choices__legend">
        {l s='Control side' d='Modules.Tailoredproductspricing.Shop'}
    </legend>

    <div class="btn-group tpp-choices__group" role="group">
        <input type="radio" class="btn-check" name="tpp_cord_side"
               id="tpp_cord_side_left_{$idProduct|intval}" value="left" autocomplete="off">
        <label class="btn btn-outline-primary" for="tpp_cord_side_left_{$idProduct|intval}">
            {l s='Izquierdo' d='Modules.Tailoredproductspricing.Shop'}
        </label>

        <input type="radio" class="btn-check" name="tpp_cord_side"
               id="tpp_cord_side_right_{$idProduct|intval}" value="right" autocomplete="off" checked>
        <label class="btn btn-outline-primary" for="tpp_cord_side_right_{$idProduct|intval}">
            {l s='Derecho' d='Modules.Tailoredproductspricing.Shop'}
        </label>
    </div>
</fieldset>

{if $cassetteEnabled}
    <fieldset class="tpp-choices">
        <legend class="form-label tpp-choices__legend">
            {l s='Cassette' d='Modules.Tailoredproductspricing.Shop'}
        </legend>

        <div class="btn-group tpp-choices__group" role="group">
            <input type="radio" class="btn-check" name="tpp_cassette"
                   id="tpp_cassette_without_{$idProduct|intval}" value="without" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="tpp_cassette_without_{$idProduct|intval}">
                {l s='Sin cenefa' d='Modules.Tailoredproductspricing.Shop'}
            </label>

            <input type="radio" class="btn-check" name="tpp_cassette"
                   id="tpp_cassette_with_{$idProduct|intval}" value="with" autocomplete="off">
            <label class="btn btn-outline-primary" for="tpp_cassette_with_{$idProduct|intval}">
                {l s='Con cenefa' d='Modules.Tailoredproductspricing.Shop'}
            </label>
        </div>
    </fieldset>
{/if}

{if $mechanismColorOptions|@count}
    <fieldset class="tpp-choices">
        <legend class="form-label tpp-choices__legend">
            {l s='Mechanism & cord color' d='Modules.Tailoredproductspricing.Shop'}
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
            {l s='Roll direction' d='Modules.Tailoredproductspricing.Shop'}
        </legend>

        <div class="btn-group tpp-choices__group" role="group">
            <input type="radio" class="btn-check" name="tpp_roll_direction"
                   id="tpp_roll_direction_standard_{$idProduct|intval}" value="standard" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="tpp_roll_direction_standard_{$idProduct|intval}">
                {l s='Enrollado estándar' d='Modules.Tailoredproductspricing.Shop'}
            </label>

            <input type="radio" class="btn-check" name="tpp_roll_direction"
                   id="tpp_roll_direction_reverse_{$idProduct|intval}" value="reverse" autocomplete="off">
            <label class="btn btn-outline-primary" for="tpp_roll_direction_reverse_{$idProduct|intval}">
                {l s='Enrollado invertido' d='Modules.Tailoredproductspricing.Shop'}
            </label>
        </div>
    </fieldset>
{/if}
