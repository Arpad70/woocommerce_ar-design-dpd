<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

class DpdHomeShippingMethod extends AbstractDpdCourierShippingMethod
{
    protected const METHOD_ID = 'wc_dpd_home';
    protected const PRODUCT_CODE = 9;
    protected const METHOD_TITLE = 'DPD Home';
    protected const METHOD_DESCRIPTION = 'Courier delivery via DPD Home.';
    protected const DEFAULT_TITLE = 'DPD Home';
}
