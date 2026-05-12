<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

class DpdExpress1000ShippingMethod extends AbstractDpdCourierShippingMethod
{
    protected const METHOD_ID = 'wc_dpd_1000';
    protected const PRODUCT_CODE = 3;
    protected const METHOD_TITLE = 'DPD 10:00';
    protected const METHOD_DESCRIPTION = 'Courier delivery via DPD 10:00.';
    protected const DEFAULT_TITLE = 'DPD 10:00';
}
