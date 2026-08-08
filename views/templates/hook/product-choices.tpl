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
