<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

class DpdClassicShippingMethod extends AbstractDpdCourierShippingMethod
{
    protected const METHOD_ID = 'wc_dpd_classic';
    protected const PRODUCT_CODE = 1;
    protected const METHOD_TITLE = 'DPD Classic';
    protected const METHOD_DESCRIPTION = 'Courier delivery via DPD Classic.';
    protected const DEFAULT_TITLE = 'DPD Classic';
}
